<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\Channel;
use App\Services\{AssignmentService, ZipService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildZipJob implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable, InteractsWithQueue;

    public function __construct(
        private int $batchId,
        private int $channelId,
        private array $assignmentIds,
        private string $ip,
        private ?string $userAgent,
    ) {
    }

    public function handle(AssignmentService $assignments, ZipService $svc): void
    {
        $batch = Batch::query()->whereKey($this->batchId)->firstOrFail();
        $channel = Channel::query()->whereKey($this->channelId)->firstOrFail();
        $items = $assignments->fetchForZip($batch, $channel, collect($this->assignmentIds));
        $svc->build($batch, $channel, $items, $this->ip, $this->userAgent ?? '');
    }
}
