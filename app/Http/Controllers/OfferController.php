<?php

namespace App\Http\Controllers;

use App\Models\{Batch, Channel};
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

    public function zip(Request $req, Batch $batch, Channel $channel)
    {
        abort_unless($req->hasValidSignature(), 403);

        $items = $this->assignments->fetchPending($batch, $channel);

        abort_if($items->isEmpty(), 404);

        $filename = sprintf('videos_%s_%s.zip', $batch->id, Str::slug($channel->name));
        $zip = new ZipStream($filename);

        // 4.1 CSV-Inhalt bauen
        $rows = [];
        $rows[] = ['filename', 'hash', 'size_mb', 'start', 'end', 'note', 'bundle', 'role'];
        foreach ($items as $a) {
            $v = $a->video;
            // Clips (können 0..n sein), dann je Clip eine Zeile; sonst eine Default-Zeile
            $clips = $v->clips()->get();
            if ($clips->isEmpty()) {
                $rows[] = [
                    $v->original_name ?: basename($v->path),
                    $v->hash,
                    number_format($v->bytes / 1048576, 1, '.', ''),
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
                        number_format($v->bytes / 1048576, 1, '.', ''),
                        $c->start_sec !== null ? gmdate('i:s', $c->start_sec) : null,
                        $c->end_sec !== null ? gmdate('i:s', $c->end_sec) : null,
                        $c->note,
                        $c->bundle_key,
                        $c->role,
                    ];
                }
            }
        }
// CSV in String schreiben
        $fp = fopen('php://temp', 'w+');
        fwrite($fp, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($fp, $r, ';');
        } // Semikolon-getrennt (DE-Excel freundlich)
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

// 4.2 CSV dem ZIP hinzufügen
        $zip->addFile('info.csv', $csv);

// 4.3 Videos hinzufügen
        foreach ($items as $a) {
            $rel = $a->video->path;
            if (!Storage::exists($rel)) {
                continue;
            }
            $s = Storage::readStream($rel);
            if (!is_resource($s)) {
                continue;
            }
            $nameInZip = ($a->video->original_name ?: basename($rel)); // schöner Name
            $zip->addFileFromStream($nameInZip, $s);
            fclose($s);
        }

        $zip->finish();
        return response()->noContent();
    }
}
