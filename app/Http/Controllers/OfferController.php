<?php

namespace App\Http\Controllers;

use App\Models\{Assignment, Batch, Channel};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

        foreach ($items as $a) {
            if ($a->status === 'queued') {
                $plain = Str::random(40);
                $a->download_token = hash('sha256', $plain);
                $a->expires_at = now()->addDays(6);
                $a->last_notified_at = now();
                $a->status = 'notified';
                $a->save();

                $a->temp_url = URL::temporarySignedRoute(
                    'assignments.download', $a->expires_at, ['assignment' => $a->id, 't' => $plain]
                );
            } else {
                $a->temp_url = URL::temporarySignedRoute(
                    'assignments.download', $a->expires_at, ['assignment' => $a->id, 't' => 'reuse']
                );
            }
        }

        return view('offer.show', [
            'batch' => $batch,
            'channel' => $channel,
            'items' => $items,
            'zipUrl' => URL::temporarySignedRoute('offer.zip', now()->addHours(6), [
                'batch' => $batch->id,
                'channel' => $channel->id
            ]),
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

        if ($items->isEmpty()) {
            abort(404);
        }

        $filename = sprintf('videos_%s_%s.zip', $batch->id, Str::slug($channel->name));
        $response = new StreamedResponse(function () use ($items, $filename) {
            $opt = new ZipOptions();
            $opt->setSendHttpHeaders(false);
            $zip = new ZipStream($filename, $opt);

            foreach ($items as $a) {
                $path = $a->video->path;
                if (!Storage::exists($path)) {
                    continue;
                }
                $stream = Storage::readStream($path);
                $zip->addFileFromStream(basename($path), $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $zip->finish();
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        return $response;
    }
}
