<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\BatchTypeEnum;
use App\Models\Batch;
use App\Repository\DownloadRepository;
use App\Repository\VideoRepository;
use Illuminate\Support\Carbon;

class VideoCleanupService
{
    public function __construct(
        private DownloadRepository $downloads,
        private VideoRepository $videos,
    ) {
    }

    public function cleanup(): int
    {
        $batch = Batch::query()->create([
            'type' => BatchTypeEnum::REMOVE->value,
            'started_at' => now(),
        ]);

        $threshold = Carbon::now()->subWeek();
        $candidates = $this->downloads->fetchDownloadedVideoIds($threshold);
        $deletable = $this->videos->filterDeletableVideoIds($candidates, $threshold);
        $names = $this->videos->fetchOriginalNames($deletable);
        $deleted = $this->videos->deleteVideosByIds($deletable);

        $batch->update([
            'finished_at' => now(),
            'stats' => [
                'removed' => $deleted,
                'original_names' => $names->values()->all(),
            ],
        ]);

        return $deleted;
    }
}

