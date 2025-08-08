<?php

namespace App\Http\Controllers;

use App\Models\{Assignment, Batch, Channel};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage, URL};
use Illuminate\Support\Str;
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

        // Wichtig: Vor ZipStream KEINE Ausgabe erzeugen!
        $filename = sprintf('videos_%s_%s.zip', $batch->id, \Str::slug($channel->name));
        $zip = new ZipStream($filename); // v3.2 sendet die nötigen Header selbst

        foreach ($items as $a) {
            $rel = $a->video->path;
            if (!Storage::exists($rel)) {
                continue;
            }

            $stream = Storage::readStream($rel);
            if (!is_resource($stream)) {
                continue;
            }

            $zip->addFileFromStream(basename($rel), $stream);
            fclose($stream);
        }

        $zip->finish(); // schließt den Stream + sendet Footer
        // ZipStream hat bereits alles an den Client geschickt.
        // Laravel erwartet eine Response – gib "leer" zurück, ohne noch Inhalte zu senden:
        return response()->noContent();
    }
}
