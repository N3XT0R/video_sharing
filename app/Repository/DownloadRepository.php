<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\StatusEnum;
use App\Models\Download;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DownloadRepository
{
    public function latestPerVideo(): Builder
    {
        return Download::query()
            ->selectRaw('MAX(downloads.id) AS id')
            ->join('assignments', 'downloads.assignment_id', '=', 'assignments.id')
            ->groupBy('assignments.video_id');
    }

    public function fetchDownloadedVideoIds(Carbon $threshold): Collection
    {
        return Download::query()
            ->join('assignments', 'downloads.assignment_id', '=', 'assignments.id')
            ->joinSub($this->latestPerVideo(), 'latest', function ($join) {
                $join->on('downloads.id', '=', 'latest.id');
            })
            ->where('assignments.status', StatusEnum::PICKEDUP->value)
            ->where('assignments.expires_at', '<', $threshold)
            ->pluck('assignments.video_id');
    }
}
