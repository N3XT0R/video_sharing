<?php

namespace App\Http\Controllers;

use App\Models\{Assignment, Batch, Channel, Download};
use App\Services\AssignmentService;
use Illuminate\Http\Request;
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
        abort_unless($req->hasValidSignature(), 403);

        $items = $this->assignments->fetchPending($batch, $channel);

        foreach ($items as $assignment) {
            $assignment->temp_url = $this->assignments->prepareDownload($assignment);
        }

        return view('offer.show', [
            'batch' => $batch,
            'channel' => $channel,
            'items' => $items,
            'zipUrl' => URL::temporarySignedRoute(
                'offer.zip', now()->addHours(6), ['batch' => $batch->id, 'channel' => $channel->id]
            ),
        ]);
    }

    public function zipSelected(Request $req, Batch $batch, Channel $channel)
    {
        abort_unless($req->hasValidSignature(), 403);
        $ids = collect($req->input('assignment_ids', []))
            ->filter(fn($v) => ctype_digit((string)$v))
            ->map('intval')
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['nothing' => 'Bitte wähle mindestens ein Video aus.']);
        }

        // Nur Assignments dieses Batches & Kanals zulassen
        $items = Assignment::with('video')
            ->where('batch_id', $batch->id)
            ->where('channel_id', $channel->id)
            ->whereIn('id', $ids)
            ->whereIn('status', ['queued', 'notified']) // noch nicht gepickt
            ->get();

        if ($items->isEmpty()) {
            return back()->withErrors(['invalid' => 'Die Auswahl ist nicht mehr verfügbar.']);
        }

        // ZIP starten – keine Ausgabe vorher!
        $filename = sprintf('videos_%s_%s_selected.zip', $batch->id, Str::slug($channel->name));
        $zip = new ZipStream($filename);

        try {
            // info.csv nur für die ausgewählten Elemente
            $rows = [];
            $rows[] = ['filename', 'hash', 'size_mb', 'start', 'end', 'note', 'bundle', 'role'];

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
                        null
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
                        ];
                    }
                }
            }

            $fp = fopen('php://temp', 'w+');
            fwrite($fp, "\xEF\xBB\xBF"); // BOM für Excel
            foreach ($rows as $r) {
                fputcsv($fp, $r, ';');
            }
            rewind($fp);
            $csv = stream_get_contents($fp);
            fclose($fp);
            $zip->addFile('info.csv', $csv);

            // Videos hinzufügen
            foreach ($items as $a) {
                $rel = $a->video->path;
                if (!Storage::exists($rel)) {
                    continue;
                }
                $s = Storage::readStream($rel);
                if (!is_resource($s)) {
                    continue;
                }

                $nameInZip = $a->video->original_name ?: basename($rel);
                $nameInZip = preg_replace('/[\\\\\/:*?"<>|]+/', '_', $nameInZip);

                $zip->addFileFromStream($nameInZip, $s);
                fclose($s);

                // Flag pro Video: jetzt als "abgeholt" markieren
                $a->status = 'picked_up';
                $a->save();

                // Download-Log (Typ zip)
                Download::query()->create([
                    'assignment_id' => $a->id,
                    'downloaded_at' => now(),
                    'ip' => $req->ip(),
                    'user_agent' => $req->userAgent(),
                    'bytes_sent' => null, // unbekannt bei Stream
                ]);
            }
        } finally {
            $zip->finish();
        }

        return response()->noContent();
    }
}
