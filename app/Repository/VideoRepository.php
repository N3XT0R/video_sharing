<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\StatusEnum;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VideoRepository
{
    public function filterDeletableVideoIds(Collection $candidateIds, Carbon $threshold): Collection
    {
        if ($candidateIds->isEmpty()) {
            return collect();
        }

        return Video::query()
            ->whereIn('id', $candidateIds)
            ->whereDoesntHave('assignments', function ($q) use ($threshold) {
                $q->where('status', '!=', StatusEnum::PICKEDUP->value)
                    ->orWhere('expires_at', '>=', $threshold)
                    ->orWhereDoesntHave('downloads');
            })
            ->pluck('id');
    }

    public function deleteVideosByIds(Collection $videoIds): int
    {
        if ($videoIds->isEmpty()) {
            return 0;
        }

        return Video::query()
            ->whereIn('id', $videoIds)
            ->delete();
    }

    public function fetchOriginalNames(Collection $videoIds): Collection
    {
        if ($videoIds->isEmpty()) {
            return collect();
        }

        return Video::query()
            ->whereIn('id', $videoIds)
            ->pluck('original_name')
            ->filter();
    }
}
