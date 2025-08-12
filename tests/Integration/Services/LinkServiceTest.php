<?php

namespace Tests\Integration\Services;

use App\Models\Batch;
use App\Models\Channel;
use App\Services\LinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_url_allows_access_with_valid_signature(): void
    {
        $batch = Batch::create(['type' => 'assign']);
        $channel = Channel::create([
            'name' => 'TestChannel',
            'creator_name' => null,
            'email' => 'testchannel@example.com',
            'weight' => 1,
            'weekly_quota' => 5,
        ]);

        $service = new LinkService();
        $url = $service->getOfferUrl($batch, $channel, now()->addMinutes(5));

        $response = $this->get($url);

        $response->assertStatus(200);
    }
}
