<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Assignment, Batch, Channel, ChannelVideoBlock, Clip, Video};
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

        $bundleKeys = Clip::query()
            ->whereIn('video_id', $poolVideos->pluck('id'))
            ->whereNotNull('bundle_key')
            ->pluck('bundle_key')
            ->unique();

        if ($bundleKeys->isNotEmpty()) {
            $bundleVideoIds = Clip::query()
                ->whereIn('bundle_key', $bundleKeys)
                ->pluck('video_id')
                ->unique();
            $bundleVideos = Video::query()->whereIn('id', $bundleVideoIds)->get();
            $poolVideos = $poolVideos->concat($bundleVideos)->unique('id')->values();
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

        $groups = collect();
        $handled = [];
        $bundleMap = Clip::query()
            ->whereIn('video_id', $poolVideos->pluck('id'))
            ->whereNotNull('bundle_key')
            ->get()
            ->groupBy('bundle_key')
            ->map(fn($g) => $g->pluck('video_id')->unique());

        foreach ($poolVideos as $v) {
            if (in_array($v->id, $handled, true)) {
                continue;
            }
            $bundleIds = $bundleMap->first(fn($ids) => $ids->contains($v->id));
            if ($bundleIds) {
                $group = $poolVideos->whereIn('id', $bundleIds)->values();
                $handled = array_merge($handled, $bundleIds->all());
            } else {
                $group = collect([$v]);
                $handled[] = $v->id;
            }
            $groups->push($group);
        }

        $assigned = 0;
        $skipped = 0;
        foreach ($groups as $group) {
            $blockedChannelIds = ChannelVideoBlock::query()
                ->whereIn('video_id', $group->pluck('id'))
                ->where('until', '>', now())
                ->pluck('channel_id')
                ->unique()
                ->all();

            $target = null;
            $rotations = 0;
            while ($rotations < $pool->count()) {
                $candidate = $pool->first();
                $pool->push($pool->shift());
                $rotations++;

                if ($quota[$candidate->id] < $group->count()) {
                    continue;
                }
                if (in_array($candidate->id, $blockedChannelIds, true)) {
                    continue;
                }
                $exists = Assignment::query()
                    ->whereIn('video_id', $group->pluck('id'))
                    ->where('channel_id', $candidate->id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $target = $candidate;
                break;
            }

            if (!$target) {
                $skipped += $group->count();
                continue;
            }

            foreach ($group as $v) {
                Assignment::query()->create([
                    'video_id' => $v->id,
                    'channel_id' => $target->id,
                    'batch_id' => $batch->id,
                    'status' => 'queued',
                ]);
                $quota[$target->id] = $quota[$target->id] - 1;
                $assigned++;
            }

            if (collect($quota->all())->every(fn($q) => $q <= 0)) {
                break;
            }
        }

        $batch->update(['finished_at' => now(), 'stats' => ['assigned' => $assigned, 'skipped' => $skipped]]);
        return ['assigned' => $assigned, 'skipped' => $skipped];
    }
}

