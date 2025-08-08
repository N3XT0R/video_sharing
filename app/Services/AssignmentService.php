<?php

namespace App\Services;

use App\Models\{Assignment, Batch, Channel};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AssignmentService
{
    /**
     * Retrieve assignments that are ready for offering to a channel.
     */
    public function fetchPending(Batch $batch, Channel $channel): Collection
    {
        return Assignment::with(['video.clips']) // wichtig für ZIP + Offer-View
        ->where('batch_id', $batch->getKey())
            ->where('channel_id', $channel->getKey())
            ->whereIn('status', ['queued', 'notified'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Prepare an assignment for download and return a temporary URL.
     */
    public function prepareDownload(Assignment $assignment, int $ttlHours = 144): string
    {
        $plain = Str::random(40);
        $expiry = $assignment->expires_at
            ? min($assignment->expires_at, now()->addHours($ttlHours))
            : now()->addHours($ttlHours);

        if ($assignment->status === 'queued') {
            $assignment->status = 'notified';
            $assignment->last_notified_at = now();
        }

        $assignment->download_token = hash('sha256', $plain);
        $assignment->expires_at = $expiry;
        $assignment->save();

        return URL::temporarySignedRoute(
            'assignments.download',
            $expiry,
            ['assignment' => $assignment->id, 't' => $plain]
        );
    }
}

