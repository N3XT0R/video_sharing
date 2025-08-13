<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Batch;
use App\Models\Channel;
use App\Services\ZipService;
use Illuminate\Support\Collection;

/**
 * Simple spy for ZipService:
 * - Does not call parent logic.
 * - Captures the arguments passed to build() so the test can assert them.
 */
class SpyZipService extends ZipService
{
    public ?int $seenBatchId = null;
    public ?int $seenChannelId = null;
    /** @var int[] */
    public array $seenAssignmentIds = [];
    public ?string $seenIp = null;
    public ?string $seenUserAgent = null;

    // Intentionally do not call the parent constructor; we don't need its dependencies.
    public function __construct()
    {
    }

    public function build(Batch $batch, Channel $channel, Collection $items, string $ip, ?string $userAgent): string
    {
        $this->seenBatchId = $batch->getKey();
        $this->seenChannelId = $channel->getKey();
        $this->seenAssignmentIds = $items->pluck('id')->all();
        $this->seenIp = $ip;
        $this->seenUserAgent = $userAgent;

        // We don't create an actual ZIP in tests.
        return 'zips/'.$batch->getKey().'_'.$channel->getKey().'.zip';
    }
}