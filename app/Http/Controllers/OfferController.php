<?php

namespace App\Http\Controllers;

use App\Models\{Assignment, Batch, Channel, Download};
use App\Services\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Storage, URL};
use Illuminate\Support\Str;
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

    public function zipSelected(Request $req, Batch $batch, Channel $channel)
    {
        $this->ensureValidSignature($req);

        $ids = collect($req->input('assignment_ids', []))
            ->filter(fn($v) => ctype_digit((string)$v))
            ->map('intval')
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['nothing' => 'Bitte wähle mindestens ein Video aus.']);
        }

        $items = $this->assignments->fetchForZip($batch, $channel, $ids);

        if ($items->isEmpty()) {
            return back()->withErrors(['invalid' => 'Die Auswahl ist nicht mehr verfügbar.']);
        }

        $filename = sprintf('videos_%s_%s_selected.zip', $batch->id, Str::slug($channel->name));
        return response()->streamDownload(function () use ($items, $req, $filename) {
            $zip = new ZipStream(
                sendHttpHeaders: true, // sendet Content-Disposition & Co.
                outputName: $filename
            );

            // Info.csv
            $zip->addFile('info.csv', $this->buildInfoCsv($items));

            // Videos hinzufügen
            $this->addVideosToZip($zip, $items, $req);

            $zip->finish(); // ganz am Ende
        }, $filename);
    }

    public function showUnused(Request $req, Batch $batch, Channel $channel)
    {
        $this->ensureValidSignature($req);
        $items = $this->assignments->fetchPickedUp($batch, $channel);

        $postUrl = URL::temporarySignedRoute(
            'offer.unused.store',
            now()->addHours(6),
            ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]
        );

        return view('offer.unused', compact('batch', 'channel', 'items', 'postUrl'));
    }

    public function storeUnused(Request $req, Batch $batch, Channel $channel): RedirectResponse
    {
        $this->ensureValidSignature($req);

        $ids = collect($req->input('assignment_ids', []))
            ->filter(fn($v) => ctype_digit((string)$v))
            ->map('intval')
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['nothing' => 'Bitte wähle mindestens ein Video aus.']);
        }

        if ($this->assignments->markUnused($batch, $channel, $ids)) {
            return back()->with('success', 'Die ausgewählten Videos wurden wieder freigegeben.');
        }

        return back()->with('error', 'Fehler: Die ausgewählten Videos konnten nicht freigegeben werden.');
    }

    private function ensureValidSignature(Request $req): void
    {
        abort_unless($req->hasValidSignature(), 403);
    }

    /**
     * @param  Collection<Assignment>  $items
     * @return string
     */
    private function buildInfoCsv(Collection $items): string
    {
        $rows = [];
        $rows[] = ['filename', 'hash', 'size_mb', 'start', 'end', 'note', 'bundle', 'role', 'submitted_by'];

        foreach ($items as $assignment) {
            $video = $assignment->video;
            $clips = $video->clips ?? collect();

            if ($clips->isEmpty()) {
                $rows[] = [
                    $video->original_name ?: basename($video->path),
                    $video->hash,
                    number_format(($video->bytes ?? 0) / 1048576, 1, '.', ''),
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                ];
            } else {
                foreach ($clips as $clip) {
                    $rows[] = [
                        $video->original_name ?: basename($video->path),
                        $video->hash,
                        number_format(($video->bytes ?? 0) / 1048576, 1, '.', ''),
                        isset($clip->start_sec) ? gmdate('i:s', (int)$clip->start_sec) : null,
                        isset($clip->end_sec) ? gmdate('i:s', (int)$clip->end_sec) : null,
                        $clip->note,
                        $clip->bundle_key,
                        $clip->role,
                        $clip->submitted_by,
                    ];
                }
            }
        }

        $fp = fopen('php://temp', 'w+');
        fwrite($fp, "\xEF\xBB\xBF"); // BOM
        foreach ($rows as $row) {
            fputcsv($fp, $row, ';');
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    /**
     * @param  ZipStream  $zip
     * @param  Collection  $items
     * @param  Request  $req
     * @return void
     */
    private function addVideosToZip(ZipStream $zip, Collection $items, Request $req): void
    {
        foreach ($items as $a) {
            $v = $a->video;
            $disk = Storage::disk($v->disk ?? 'local');

            if (!$disk->exists($v->path)) {
                continue;
            }

            $s = $disk->readStream($v->path);
            if (!is_resource($s)) {
                continue;
            }

            $nameInZip = $v->original_name ?: basename($v->path);
            $nameInZip = preg_replace('/[\\\\\/:*?"<>|]+/', '_', $nameInZip);

            $zip->addFileFromStream($nameInZip, $s);
            fclose($s);

            $a->update(['status' => 'picked_up']);

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
