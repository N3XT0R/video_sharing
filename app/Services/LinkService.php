<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Batch;
use App\Models\Channel;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;

class LinkService
{
    public function getOfferUrl(Batch $batch, Channel $channel, Carbon $expireDate): string
    {
        return URL::temporarySignedRoute(
            'offer.show',
            $expireDate,
            ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]
        );
    }

    public function getUnusedUrl(Batch $batch, Channel $channel, Carbon $expireDate): string
    {
        return URL::temporarySignedRoute(
            'offer.unused.show',
            $expireDate,
            ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]
        );
    }

    public function getStoreUnusedUrl(Batch $batch, Channel $channel, Carbon $expireDate): string
    {
        return URL::temporarySignedRoute(
            'offer.unused.store',
            $expireDate,
            ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]
        );
    }

    public function getZipSelectedUrl(Batch $batch, Channel $channel, Carbon $expireDate): string
    {
        return URL::temporarySignedRoute(
            'offer.zip.selected',
            $expireDate,
            ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);
    }
}