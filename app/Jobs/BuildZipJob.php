<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\Channel;
use App\Services\ZipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildZipJob implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable, InteractsWithQueue;

    public function __construct(
        private int $jobId,
        private int $channelId,
        public array $absolutePaths,     // absolute Pfade (oder du nimmst Storage-Disk + relative Paths)
    )
    {
    }

    public function handle(ZipService $svc): void
    {
        $batch = Batch::query()->whereKey($this->jobId)->firstOrFail();
        $channel = Channel::query()->whereKey($this->channelId)->firstOrFail();
        $svc->build($batch, $channel, $this->absolutePaths);
    }
}
