<?php

namespace App\Http\Controllers;

use App\Models\{Assignment, Batch, Channel, Download};
use App\Services\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Storage, URL};
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

class OfferController extends Controller
{
    public function __construct(private AssignmentService $assignments)
    {
    }

    public function show(Request $req, Batch $batch, Channel $channel)
    {
        $this->ensureValidSignature($req);

        $items = $this->assignments
            ->fetchPending($batch, $channel)
            ->loadMissing('video.clips');

        foreach ($items as $assignment) {
            $assignment->temp_url = $this->assignments->prepareDownload($assignment);
        }

        $zipPostUrl = URL::temporarySignedRoute(
            'offer.zip.selected',
            now()->addHours(6),
            ['batch' => $batch->id, 'channel' => $channel->id]
        );

        return view('offer.show', compact('batch', 'channel', 'items', 'zipPostUrl'));
    }

    public function zipSelected(
        Request $req,
        Batch $batch,
        Channel $channel
    ): StreamedResponse|RedirectResponse {
        $this->ensureValidSignature($req);

        $ids = collect($req->input('assignment_ids', []))
            ->filter(fn($v) => ctype_digit((string)$v))
            ->map('intval')
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['nothing' => 'Bitte wähle mindestens ein Video aus.']);
        }

        $items = $this->fetchAssignmentsForZip($batch, $channel, $ids);

        if ($items->isEmpty()) {
            return back()->withErrors(['invalid' => 'Die Auswahl ist nicht mehr verfügbar.']);
        }

        $filename = sprintf('videos_%s_%s_selected.zip', $batch->id, Str::slug($channel->name));

        // 🔧 Wichtig: Output-Buffer leeren, Kompression aus (sonst kaputte ZIPs / nur 1 File)
        @ini_set('zlib.output_compression', '0');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        return response()->streamDownload(function () use ($items, $req, $filename) {
            $zip = new ZipStream(
                sendHttpHeaders: true,
                outputName: $filename
            );

            // info.csv zuerst
            $zip->addFile('info.csv', $this->buildInfoCsv($items));

            // Videos hinzufügen (ohne Status-Update)
            $this->addVideosToZip($zip, $items, $req);

            // ZIP sauber schließen
            $zip->finish();
            
            \App\Models\Assignment::whereIn('id', $items->pluck('id'))->update(['status' => 'picked_up']);
        }, $filename);
    }

    public function showUnused(Request $req, Batch $batch, Channel $channel)
    {
        $this->ensureValidSignature($req);

        $items = Assignment::with('video')
            ->where('batch_id', $batch->id)
            ->where('channel_id', $channel->id)
            ->where('status', 'picked_up')
            ->get();

        $postUrl = URL::temporarySignedRoute(
            'offer.unused.store',
            now()->addHours(6),
            ['batch' => $batch->id, 'channel' => $channel->id]
        );

        return view('offer.unused', compact('batch', 'channel', 'items', 'postUrl'));
    }

    public function storeUnused(Request $req, Batch $batch, Channel $channel)
    {
        $this->ensureValidSignature($req);

        $ids = collect($req->input('assignment_ids', []))
            ->filter(fn($v) => ctype_digit((string)$v))
            ->map('intval')
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['nothing' => 'Bitte wähle mindestens ein Video aus.']);
        }

        Assignment::query()
            ->where('batch_id', $batch->id)
            ->where('channel_id', $channel->id)
            ->whereIn('id', $ids)
            ->where('status', 'picked_up')
            ->update([
                'status' => 'queued',
                'download_token' => null,
                'expires_at' => null,
                'last_notified_at' => null,
            ]);

        return back()->with('success', 'Die ausgewählten Videos wurden wieder freigegeben.');
    }

    private function ensureValidSignature(Request $req): void
    {
        abort_unless($req->hasValidSignature(), 403);
    }

    private function fetchAssignmentsForZip(Batch $batch, Channel $channel, Collection $ids)
    {
        return Assignment::with('video.clips')
            ->where('batch_id', $batch->id)
            ->where('channel_id', $channel->id)
            ->whereIn('id', $ids)
            ->whereIn('status', ['queued', 'notified'])
            ->get();
    }

    private function buildInfoCsv(Collection $items): string
    {
        $rows = [];
        $rows[] = ['filename', 'hash', 'size_mb', 'start', 'end', 'note', 'bundle', 'role', 'submitted_by'];

        foreach ($items as $a) {
            $v = $a->video;
            $clips = $v->clips ?? collect();

            if ($clips->isEmpty()) {
                $rows[] = [
                    $v->original_name ?: basename($v->path),
                    $v->hash,
                    number_format(($v->bytes ?? 0) / 1048576, 1, '.', ''),
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                ];
            } else {
                foreach ($clips as $c) {
                    $rows[] = [
                        $v->original_name ?: basename($v->path),
                        $v->hash,
                        number_format(($v->bytes ?? 0) / 1048576, 1, '.', ''),
                        isset($c->start_sec) ? gmdate('i:s', (int)$c->start_sec) : null,
                        isset($c->end_sec) ? gmdate('i:s', (int)$c->end_sec) : null,
                        $c->note,
                        $c->bundle_key,
                        $c->role,
                        $c->submitted_by,
                    ];
                }
            }
        }

        $fp = fopen('php://temp', 'w+');
        fwrite($fp, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($fp, $r, ';');
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    private function addVideosToZip(ZipStream $zip, Collection $items, Request $req): void
    {
        foreach ($items as $a) {
            $v = $a->video;
            $disk = Storage::disk($v->disk ?? 'local');

            if (!$disk->exists($v->path)) {
                \Log::warning("ZIP: Datei fehlt", ['assignment' => $a->id, 'path' => $v->path]);
                continue;
            }

            $s = $disk->readStream($v->path);
            if (!is_resource($s)) {
                \Log::warning("ZIP: Kein Stream", ['assignment' => $a->id, 'path' => $v->path]);
                continue;
            }

            $nameInZip = $v->original_name ?: basename($v->path);
            $nameInZip = preg_replace('/[\\\\\/:*?"<>|]+/', '_', $nameInZip);

            // Stream in die ZIP schreiben
            $zip->addFileFromStream($nameInZip, $s);
            fclose($s);

            // Audit-Log ja, Status erst nach finish()
            Download::query()->create([
                'assignment_id' => $a->id,
                'downloaded_at' => now(),
                'ip' => $req->ip(),
                'user_agent' => $req->userAgent(),
                'bytes_sent' => null,
            ]);
        }
    }
}
