<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Assignment, Batch, Channel, ChannelVideoBlock, Video};
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssignmentDistributor
{
    /**
     * Distribute new and expired videos across channels.
     *
     * @param int|null $quotaOverride optional quota per channel
     * @return array{assigned:int, skipped:int}
     */
    public function distribute(?int $quotaOverride = null): array
    {
        $batch = Batch::query()->create(['type' => 'assign', 'started_at' => now()]);

        $last = Batch::query()->where('type', 'assign')->whereNotNull('finished_at')->latest()->first();
        $newVideos = Video::query()->when($last, fn($q) => $q->where('created_at', '>', $last->finished_at))
            ->orderBy('id')
            ->get();

        $expiredVideoIds = Assignment::query()->where('status', 'expired')->pluck('video_id')->unique();
        $expiredVideos = Video::query()->whereIn('id', $expiredVideoIds)->get();

        $poolVideos = $newVideos->concat($expiredVideos)->unique('id')->values();
        if ($poolVideos->isEmpty()) {
            $batch->update(['finished_at' => now(), 'stats' => ['assigned' => 0]]);
            throw new RuntimeException('Nichts zu verteilen.');
        }

        $channels = Channel::query()->orderBy('id')->get();
        if ($channels->isEmpty()) {
            $batch->update(['finished_at' => now(), 'stats' => ['assigned' => 0]]);
            throw new RuntimeException('Keine Kanäle konfiguriert.');
        }
        $pool = collect();
        foreach ($channels as $c) {
            $pool = $pool->merge(array_fill(0, max(1, (int)$c->weight), $c));
        }

        $quota = $channels->mapWithKeys(fn($c) => [$c->id => (int)($quotaOverride ?: $c->weekly_quota)]);

        $assigned = 0;
        $skipped = 0;
        foreach ($poolVideos as $v) {
            $blockedChannelIds = ChannelVideoBlock::query()->where('video_id', $v->id)
                ->where('until', '>', now())
                ->pluck('channel_id')->all();

            $target = null;
            $rotations = 0;
            while ($rotations < $pool->count()) {
                $candidate = $pool->first();
                $pool->push($pool->shift());
                $rotations++;

                if ($quota[$candidate->id] <= 0) {
                    continue;
                }
                if (in_array($candidate->id, $blockedChannelIds, true)) {
                    continue;
                }
                $exists = Assignment::query()->where('video_id', $v->id)
                    ->where('channel_id', $candidate->id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $target = $candidate;
                break;
            }

            if (!$target) {
                $skipped++;
                continue;
            }

            Assignment::query()->create([
                'video_id' => $v->id,
                'channel_id' => $target->id,
                'batch_id' => $batch->id,
                'status' => 'queued',
                'attempts' => DB::raw('attempts'),
            ]);
            $quota[$target->id]--;
            $assigned++;

            if (collect($quota->all())->every(fn($q) => $q <= 0)) {
                break;
            }
        }

        $batch->update(['finished_at' => now(), 'stats' => ['assigned' => $assigned, 'skipped' => $skipped]]);
        return ['assigned' => $assigned, 'skipped' => $skipped];
    }
}

