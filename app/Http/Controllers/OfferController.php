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

        $zip->finish();

        return response()->noContent();
    }
}
