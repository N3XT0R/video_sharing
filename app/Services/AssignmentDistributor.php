<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\ChannelVideoBlock;
use App\Models\Clip;
use App\Models\Video;
use Illuminate\Support\Collection;
use RuntimeException;

class AssignmentDistributor
{
    /** @var string[] Status-Werte, die Videos erneut in den Pool bringen dürfen */
    private const REQUEUE_STATUSES = ['expired', 'rejected']; // ggf. erweitern: 'returned', 'rejected', ...

    /**
     * Distribute new and requeueable videos across channels.
     *
     * @param  int|null  $quotaOverride  optional quota per channel
     * @return array{assigned:int, skipped:int}
     */
    public function distribute(?int $quotaOverride = null): array
    {
        $batch = $this->startBatch();

        $lastFinished = $this->lastFinishedAssignBatch();

        // 1) Kandidaten einsammeln (neu, unzugewiesen, requeue)
        $poolVideos = $this->collectPoolVideos($lastFinished);

        if ($poolVideos->isEmpty()) {
            $batch->update(['finished_at' => now(), 'stats' => ['assigned' => 0, 'skipped' => 0]]);
            throw new RuntimeException('Nichts zu verteilen.');
        }

        // 2) Bundles vollständig machen
        $poolVideos = $this->expandBundles($poolVideos)->values();

        // 3) Kanäle + Rotationspool + Quotas
        [$channels, $rotationPool, $quota] = $this->prepareChannelsAndPool($quotaOverride);

        if ($channels->isEmpty()) {
            $batch->update(['finished_at' => now(), 'stats' => ['assigned' => 0, 'skipped' => 0]]);
            throw new RuntimeException('Keine Kanäle konfiguriert.');
        }

        // 4) Gruppenbildung (Videos, die zu einem Bundle gehören, bleiben zusammen)
        $groups = $this->buildGroups($poolVideos);

        // 5) Preloads zur Minimierung von N+1
        $blockedByVideo = $this->preloadActiveBlocks($poolVideos);
        $assignedChannelsByVideo = $this->preloadAssignedChannels($poolVideos);

        // 6) Verteilung
        $assigned = 0;
        $skipped = 0;

        foreach ($groups as $group) {
            // Blockierte Kanäle für diese Gruppe ermitteln (union über alle Videos der Gruppe)
            $blockedChannelIds = $group
                ->flatMap(fn(Video $v) => $blockedByVideo[$v->id] ?? collect())
                ->unique()
                ->all();

            $target = $this->pickTargetChannel(
                $group,
                $rotationPool,
                $quota,
                $blockedChannelIds,
                $assignedChannelsByVideo
            );

            if (!$target) {
                $skipped += $group->count();
                continue;
            }

            foreach ($group as $video) {
                Assignment::query()->create([
                    'video_id' => $video->id,
                    'channel_id' => $target->id,
                    'batch_id' => $batch->id,
                    'status' => 'queued',
                ]);

                // Für Folgerunden merken, dass dieses Video diesem Kanal nun zugeordnet ist
                $assignedChannelsByVideo[$video->id] = ($assignedChannelsByVideo[$video->id] ?? collect())
                    ->push($target->id)
                    ->unique();

                $quota[$target->id] = $quota[$target->id] - 1;
                $assigned++;
            }

            // Abbruch, wenn alle Quotas aufgebraucht sind
            if (collect($quota)->every(fn(int $q) => $q <= 0)) {
                break;
            }
        }

        $batch->update([
            'finished_at' => now(),
            'stats' => ['assigned' => $assigned, 'skipped' => $skipped],
        ]);

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }

    /* ===================== Helpers ===================== */

    private function startBatch(): Batch
    {
        return Batch::query()->create([
            'type' => 'assign',
            'started_at' => now(),
        ]);
    }

    private function lastFinishedAssignBatch(): ?Batch
    {
        return Batch::query()
            ->where('type', 'assign')
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at') // semantisch korrekter als latest() auf created_at
            ->first();
    }

    /**
     * Sammle Videos für den Verteilungspool:
     *  - unzugewiesene EVER
     *  - oder neu seit letztem fertigen Assign-Batch
     *  - plus requeue-fähige (expired/returned/...)
     */
    private function collectPoolVideos(?Batch $lastFinished): Collection
    {
        // Unassigned EVER ODER neuer als letzter Batch
        $newOrUnassigned = Video::query()
            ->whereDoesntHave('assignments')
            ->when($lastFinished, function ($q) use ($lastFinished) {
                $q->orWhere('created_at', '>', $lastFinished->finished_at);
            })
            ->orderBy('id')
            ->get();

        // Requeue-Fälle (z. B. expired)
        $requeueIds = Assignment::query()
            ->whereIn('status', self::REQUEUE_STATUSES)
            ->pluck('video_id')
            ->unique();

        $requeueVideos = $requeueIds->isNotEmpty()
            ? Video::query()->whereIn('id', $requeueIds)->get()
            : collect();

        return $newOrUnassigned->concat($requeueVideos)->unique('id');
    }

    /**
     * Stelle sicher, dass alle Videos aus Bundles mitkommen, wenn eines im Pool ist.
     */
    private function expandBundles(Collection $poolVideos): Collection
    {
        $videoIds = $poolVideos->pluck('id');

        $bundleKeys = Clip::query()
            ->whereIn('video_id', $videoIds)
            ->whereNotNull('bundle_key')
            ->pluck('bundle_key')
            ->unique();

        if ($bundleKeys->isEmpty()) {
            return $poolVideos;
        }

        $bundleVideoIds = Clip::query()
            ->whereIn('bundle_key', $bundleKeys)
            ->pluck('video_id')
            ->unique();

        if ($bundleVideoIds->isEmpty()) {
            return $poolVideos;
        }

        $bundleVideos = Video::query()->whereIn('id', $bundleVideoIds)->get();

        return $poolVideos->concat($bundleVideos)->unique('id');
    }

    /**
     * Liefert:
     *  - sortierte Kanalliste
     *  - Rotationspool (Gewichtung über "weight")
     *  - Quota je Kanal
     *
     * @return array{0: Collection<Channel>, 1: Collection<Channel>, 2: array<int,int>}
     */
    private function prepareChannelsAndPool(?int $quotaOverride): array
    {
        $channels = Channel::query()->orderBy('id')->get();

        $rotationPool = collect();
        foreach ($channels as $channel) {
            $rotationPool = $rotationPool->merge(
                array_fill(0, max(1, (int)$channel->weight), $channel)
            );
        }

        /** @var array<int,int> $quota */
        $quota = $channels
            ->mapWithKeys(fn(Channel $c) => [$c->id => (int)($quotaOverride ?: $c->weekly_quota)])
            ->all();

        return [$channels, $rotationPool, $quota];
    }

    /**
     * Gruppiert Videos so, dass Bundle-Mitglieder zusammen bleiben.
     *
     * @return Collection<int, Collection<int, Video>>
     */
    private function buildGroups(Collection $poolVideos): Collection
    {
        $handled = [];
        $groups = collect();

        $bundleMap = Clip::query()
            ->whereIn('video_id', $poolVideos->pluck('id'))
            ->whereNotNull('bundle_key')
            ->get()
            ->groupBy('bundle_key')
            ->map(fn(Collection $g) => $g->pluck('video_id')->unique());

        foreach ($poolVideos as $video) {
            if (in_array($video->id, $handled, true)) {
                continue;
            }

            $bundleIds = $bundleMap->first(fn(Collection $ids) => $ids->contains($video->id));

            if ($bundleIds) {
                $group = $poolVideos->whereIn('id', $bundleIds)->values();
                $handled = array_merge($handled, $bundleIds->all());
            } else {
                $group = collect([$video]);
                $handled[] = $video->id;
            }

            $groups->push($group);
        }

        return $groups;
    }

    /**
     * Lade alle aktiven Blocks (bis "until" in der Zukunft) für den gesamten Pool vor.
     *
     * @return array<int, Collection<int,int>> video_id => collection(channel_id)
     */
    private function preloadActiveBlocks(Collection $poolVideos): array
    {
        return ChannelVideoBlock::query()
            ->whereIn('video_id', $poolVideos->pluck('id'))
            ->where('until', '>', now())
            ->get()
            ->groupBy('video_id')
            ->map(fn(Collection $rows) => $rows->pluck('channel_id')->unique())
            ->all();
    }

    /**
     * Lade alle bereits (irgendwann) zugewiesenen Kanäle je Video vor,
     * damit wir nicht doppelt an denselben Kanal verteilen.
     *
     * @return array<int, Collection<int,int>> video_id => collection(channel_id)
     */
    private function preloadAssignedChannels(Collection $poolVideos): array
    {
        return Assignment::query()
            ->whereIn('video_id', $poolVideos->pluck('id'))
            ->get()
            ->groupBy('video_id')
            ->map(fn(Collection $rows) => $rows->pluck('channel_id')->unique())
            ->all();
    }

    /**
     * Wählt einen Zielkanal im Round-Robin über den gewichteten Rotationspool.
     *
     * @param  Collection<int,Video>  $group
     * @param  Collection<int,Channel>  $rotationPool
     * @param  array<int,int>  $quota  (by reference, wird nicht verändert – nur gelesen)
     * @param  array<int,int>  $blockedChannelIds
     * @param  array<int, Collection<int,int>>  $assignedChannelsByVideo
     */
    private function pickTargetChannel(
        Collection $group,
        Collection $rotationPool,
        array $quota,
        array $blockedChannelIds,
        array $assignedChannelsByVideo
    ): ?Channel {
        $rotations = 0;
        $poolCount = $rotationPool->count();

        while ($rotations < $poolCount) {
            /** @var Channel $candidate */
            $candidate = $rotationPool->first();
            // rotate
            $rotationPool->push($rotationPool->shift());
            $rotations++;

            // Genügend Quota verfügbar?
            if (($quota[$candidate->id] ?? 0) < $group->count()) {
                continue;
            }

            // Kandidat blockiert?
            if (in_array($candidate->id, $blockedChannelIds, true)) {
                continue;
            }

            // Bereits (irgendwann) an diesen Kanal vergeben?
            $alreadyAssignedToCandidate = $group->some(function (Video $v) use ($candidate, $assignedChannelsByVideo) {
                $assigned = $assignedChannelsByVideo[$v->id] ?? collect();
                return $assigned->contains($candidate->id);
            });
            if ($alreadyAssignedToCandidate) {
                continue;
            }

            return $candidate;
        }

        return null;
    }
}
