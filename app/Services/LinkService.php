<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Batch;
use App\Models\Channel;
use Illuminate\Support\Facades\URL;

class LinkService
{
    public function getOfferUrl(Batch $batch, Channel $channel, int $ttlDays = 6): string
    {
        return URL::temporarySignedRoute(
            'offer.show',
            now()->addDays($ttlDays),
            ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]
        );
    }

    public function getUnusedUrl(Batch $batch, Channel $channel, int $ttlDays = 6): string
    {
        return URL::temporarySignedRoute(
            'offer.unused.show',
            now()->addDays($ttlDays),
            ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]
        );
    }
}