<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Channel;
use App\Models\ChannelVideoBlock;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Tests\DatabaseTestCase;

/**
 * Unit tests for the App\Models\ChannelVideoBlock model.
 *
 * We validate:
 *  - mass assignment and datetime cast for "until"
 *  - belongsTo relationships (channel, video)
 *  - the "active" scope filters blocks with until > now()
 */
final class ChannelVideoBlockTest extends DatabaseTestCase
{
    public function testMassAssignmentAndCast(): void
    {
        $channel = Channel::factory()->create();
        $video = Video::factory()->create();

        $until = '2025-08-20 12:00:00';

        $block = ChannelVideoBlock::query()->create([
            'channel_id' => $channel->getKey(),
            'video_id' => $video->getKey(),
            'until' => $until,
        ])->fresh();

        // Persisted attributes
        $this->assertSame($channel->getKey(), $block->channel_id);
        $this->assertSame($video->getKey(), $block->video_id);

        // Cast: until is Carbon and equals timestamp
        $this->assertInstanceOf(Carbon::class, $block->until);
        $this->assertTrue($block->until->equalTo(Carbon::parse($until)));
    }

    public function testBelongsToRelationshipsResolveParents(): void
    {
        $channel = Channel::factory()->create();
        $video = Video::factory()->create();

        $block = ChannelVideoBlock::factory()->create([
            'channel_id' => $channel->getKey(),
            'video_id' => $video->getKey(),
            'until' => Carbon::now()->addDays(3),
        ]);

        $this->assertNotNull($block->channel);
        $this->assertNotNull($block->video);

        $this->assertSame($channel->getKey(), $block->channel->getKey());
        $this->assertSame($video->getKey(), $block->video->getKey());
    }

    public function testActiveScopeReturnsOnlyFutureBlocks(): void
    {
        Carbon::setTestNow('2025-08-13 10:00:00');

        $channel = Channel::factory()->create();
        $video1 = Video::factory()->create();
        $video2 = Video::factory()->create();

        // Past block (excluded)
        ChannelVideoBlock::factory()->create([
            'channel_id' => $channel->getKey(),
            'video_id' => $video1->getKey(),
            'until' => Carbon::parse('2025-08-12 23:59:59'),
        ]);

        // Future block (included)
        $future = ChannelVideoBlock::factory()->create([
            'channel_id' => $channel->getKey(),
            'video_id' => $video2->getKey(),
            'until' => Carbon::parse('2025-08-20 00:00:00'),
        ]);

        $activeIds = ChannelVideoBlock::query()->active()->pluck('id')->all();

        $this->assertContains($future->getKey(), $activeIds);
        $this->assertCount(1, $activeIds);
    }
}
