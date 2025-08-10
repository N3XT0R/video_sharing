<?php

namespace App\Services;

use App\Enum\StatusEnum;
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
        return Assignment::with(['video.clips'])
            ->where('batch_id', $batch->getKey())
            ->where('channel_id', $channel->getKey())
            ->whereIn('status', StatusEnum::getReadyStatus())
            ->orderBy('id')
            ->get();
    }

    public function fetchForZip(Batch $batch, Channel $channel, Collection $ids): Collection
    {
        return Assignment::with('video.clips')
            ->where('batch_id', $batch->getKey())
            ->where('channel_id', $channel->getKey())
            ->whereIn('id', $ids)
            ->whereIn('status', StatusEnum::getReadyStatus())
            ->get();
    }

    public function fetchPickedUp(Batch $batch, Channel $channel): Collection
    {
        return Assignment::with('video')
            ->where('batch_id', $batch->getKey())
            ->where('channel_id', $channel->getKey())
            ->where('status', StatusEnum::PICKEDUP->value)
            ->get();
    }

    public function markUnused(Batch $batch, Channel $channel, array $ids): bool
    {
        return Assignment::query()
                ->where('batch_id', $batch->getKey())
                ->where('channel_id', $channel->getKey())
                ->whereIn('id', $ids)
                ->where('status', StatusEnum::PICKEDUP->value)
                ->update([
                    'status' => StatusEnum::QUEUED->value,
                    'download_token' => null,
                    'expires_at' => null,
                    'last_notified_at' => null,
                ]) > 0;
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

        if ($assignment->status === StatusEnum::QUEUED->value) {
            $assignment->status = StatusEnum::NOTIFIED->value;
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

