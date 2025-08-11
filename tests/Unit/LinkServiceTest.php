<?php

namespace Tests\Unit;

use App\Models\Batch;
use App\Models\Channel;
use App\Services\LinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LinkServiceTest extends TestCase
{
    public function test_get_offer_url_uses_signed_route(): void
    {
        $batch = new Batch();
        $batch->id = 10;
        $channel = new Channel();
        $channel->id = 5;
        $expire = Carbon::now()->addMinutes(5);

        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with('offer.show', $expire, ['batch' => 10, 'channel' => 5])
            ->andReturn('signed-offer-url');

        $service = new LinkService();
        $url = $service->getOfferUrl($batch, $channel, $expire);

        $this->assertSame('signed-offer-url', $url);
    }

    public function test_get_unused_url_uses_signed_route(): void
    {
        $batch = new Batch();
        $batch->id = 12;
        $channel = new Channel();
        $channel->id = 3;
        $expire = Carbon::now()->addMinutes(5);

        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with('offer.unused.show', $expire, ['batch' => 12, 'channel' => 3])
            ->andReturn('signed-unused-url');

        $service = new LinkService();
        $url = $service->getUnusedUrl($batch, $channel, $expire);

        $this->assertSame('signed-unused-url', $url);
    }

    public function test_get_store_unused_url_uses_signed_route(): void
    {
        $batch = new Batch();
        $batch->id = 7;
        $channel = new Channel();
        $channel->id = 2;
        $expire = Carbon::now()->addMinutes(5);

        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with('offer.unused.store', $expire, ['batch' => 7, 'channel' => 2])
            ->andReturn('signed-store-unused-url');

        $service = new LinkService();
        $url = $service->getStoreUnusedUrl($batch, $channel, $expire);

        $this->assertSame('signed-store-unused-url', $url);
    }

    public function test_get_zip_selected_url_uses_signed_route(): void
    {
        $batch = new Batch();
        $batch->id = 15;
        $channel = new Channel();
        $channel->id = 9;
        $expire = Carbon::now()->addMinutes(5);

        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with('zips.start', $expire, ['batch' => 15, 'channel' => 9])
            ->andReturn('signed-zip-url');

        $service = new LinkService();
        $url = $service->getZipSelectedUrl($batch, $channel, $expire);

        $this->assertSame('signed-zip-url', $url);
    }
}
