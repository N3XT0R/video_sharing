<?php

namespace App\Services;

use App\Enum\StatusEnum;
use App\Models\{Assignment, Batch, Channel, Download};
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AssignmentService
{
    /**
     * Retrieve assignments that are ready for offering to a channel.
     */
    public function fetchPending(Batch $batch, Channel $channel): EloquentCollection
    {
        return Assignment::with(['video.clips'])
            ->where('batch_id', $batch->getKey())
            ->where('channel_id', $channel->getKey())
            ->whereIn('status', StatusEnum::getReadyStatus())
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Batch  $batch
     * @param  Channel  $channel
     * @param  Collection  $ids
     * @return EloquentCollection<Assignment>
     */
    public function fetchForZip(Batch $batch, Channel $channel, Collection $ids): EloquentCollection
    {
        return Assignment::with('video.clips')
            ->where('batch_id', $batch->getKey())
            ->where('channel_id', $channel->getKey())
            ->whereIn('id', $ids)
            ->whereIn('status', StatusEnum::getReadyStatus())
            ->get();
    }

    public function fetchPickedUp(Batch $batch, Channel $channel): EloquentCollection
    {
        return Assignment::with('video')
            ->where('batch_id', $batch->getKey())
            ->where('channel_id', $channel->getKey())
            ->where('status', StatusEnum::PICKEDUP->value)
            ->get();
    }

    public function markUnused(Batch $batch, Channel $channel, Collection $ids): bool
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

    public function markDownloaded(Assignment $assignment, string $ip, ?string $userAgent): void
    {
        $assignment->update(['status' => StatusEnum::PICKEDUP->value]);

        Download::query()->create([
            'assignment_id' => $assignment->getKey(),
            'downloaded_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent,
            'bytes_sent' => null,
        ]);
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
            ['assignment' => $assignment->getKey(), 't' => $plain]
        );
    }
}

