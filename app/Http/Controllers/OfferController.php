<?php

namespace App\Http\Controllers;

use App\Models\{Assignment, Batch, Channel};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage, URL};
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\Option\Archive as ZipOptions;
use ZipStream\ZipStream;

class OfferController extends Controller
{
    public function show(Request $req, Batch $batch, Channel $channel)
    {
        abort_unless($req->hasValidSignature(), 403);

        $items = Assignment::with('video')
            ->where('batch_id', $batch->id)
            ->where('channel_id', $channel->id)
            ->whereIn('status', ['queued', 'notified'])
            ->get();

        // Frische (kanalspezifische) Download-Links erzeugen
        foreach ($items as $a) {
            $plain = Str::random(40);
            $expiry = $a->expires_at ? min($a->expires_at, now()->addDays(6)) : now()->addDays(6);

            if ($a->status === 'queued') {
                $a->status = 'notified';
                $a->last_notified_at = now();
            }

            $a->download_token = hash('sha256', $plain);
            $a->expires_at = $expiry;
            $a->save();

            $a->temp_url = URL::temporarySignedRoute(
                'assignments.download', $expiry, ['assignment' => $a->id, 't' => $plain]
            );
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

        $items = Assignment::with('video')
            ->where('batch_id', $batch->id)
            ->where('channel_id', $channel->id)
            ->whereIn('status', ['queued', 'notified'])
            ->get();

        abort_if($items->isEmpty(), 404);

        $filename = sprintf('videos_%s_%s.zip', $batch->id, Str::slug($channel->name));

        $response = new StreamedResponse(function () use ($items, $filename) {
            // ZipStream v3.2 – Optionen setzen
            $opt = new ZipOptions();
            $opt->setSendHttpHeaders(false);                // Header kommen von Symfony
            $opt->setOutputStream(fopen('php://output', 'wb'));
            $opt->setFlushOutput(true);                     // sofortiges Flushing erlaubt
            // $opt->setEnableZip64(true);                  // optional, falls >4GB Gesamtgröße möglich

            $zip = new ZipStream($filename, $opt);

            foreach ($items as $a) {
                $rel = $a->video->path;
                if (!Storage::exists($rel)) {
                    continue;
                }
                $stream = Storage::readStream($rel);
                if (!is_resource($stream)) {
                    continue;
                }

                // Dateiname im ZIP: <hash>.<ext>
                $nameInZip = basename($rel);
                $zip->addFileFromStream($nameInZip, $stream);

                fclose($stream);
            }

            $zip->finish(); // wichtig: schließt das Archiv sauber ab
        });

        // Header selbst setzen (da ZipStream sie nicht sendet)
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="'.$filename.'"'
        );
        // Kein Content-Length, weil gestreamt wird (chunked)

        return $response;
    }
}
