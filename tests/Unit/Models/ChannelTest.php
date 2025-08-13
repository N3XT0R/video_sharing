<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\ChannelVideoBlock;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Tests\DatabaseTestCase;

/**
 * Unit tests for App\Models\Channel.
 *
 * We validate:
 *  - mass assignment of fillable attributes
 *  - hasMany relations: assignments, videoBlocks
 *  - scoped relation: activeVideoBlocks() (until > now)
 *  - belongsToMany: blockedVideos() with pivot "until"
 */
final class ChannelTest extends DatabaseTestCase
{
    public function testMassAssignmentPersistsFillableAttributes(): void
    {
        $channel = Channel::query()->create([
            'name' => 'RoadWatch',
            'creator_name' => 'Alice',
            'email' => 'alice@example.test',
            'weight' => 5,
            'weekly_quota' => 10,
        ])->fresh();

        $this->assertSame('RoadWatch', $channel->getAttribute('name'));
        $this->assertSame('Alice', $channel->getAttribute('creator_name'));
        $this->assertSame('alice@example.test', $channel->getAttribute('email'));
        $this->assertSame(5, $channel->getAttribute('weight'));
        $this->assertSame(10, $channel->getAttribute('weekly_quota'));
    }

    public function testAssignmentsRelationReturnsRelatedModels(): void
    {
        $channel = Channel::factory()->create();
        $batch = Batch::factory()->type('assign')->finished()->create();
        $video1 = Video::factory()->create();
        $video2 = Video::factory()->create();

        $a1 = Assignment::factory()->for($video1, 'video')->for($channel, 'channel')->for($batch, 'batch')->create();
        $a2 = Assignment::factory()->for($video2, 'video')->for($channel, 'channel')->for($batch, 'batch')->create();

        $relatedIds = $channel->assignments()->pluck('id')->all();

        $this->assertContains($a1->getKey(), $relatedIds);
        $this->assertContains($a2->getKey(), $relatedIds);
        $this->assertCount(2, $channel->assignments()->get());
    }

    public function testVideoBlocksRelationReturnsBlocks(): void
    {
        $channel = Channel::factory()->create();
        $video1 = Video::factory()->create();
        $video2 = Video::factory()->create();

        ChannelVideoBlock::factory()->create([
            'channel_id' => $channel->getKey(),
            'video_id' => $video1->getKey(),
            'until' => Carbon::now()->addDays(7),
        ]);
        ChannelVideoBlock::factory()->create([
            'channel_id' => $channel->getKey(),
            'video_id' => $video2->getKey(),
            'until' => Carbon::now()->addDays(1),
        ]);

        $this->assertCount(2, $channel->videoBlocks()->get());
    }

    public function testActiveVideoBlocksFiltersByUntilGreaterThanNow(): void
    {
        Carbon::setTestNow('2025-08-13 12:00:00');

        $channel = Channel::factory()->create();
        $videoPast = Video::factory()->create();
        $videoFuture = Video::factory()->create();

        // Past block (should NOT be included)
        ChannelVideoBlock::factory()->create([
            'channel_id' => $channel->getKey(),
            'video_id' => $videoPast->getKey(),
            'until' => Carbon::parse('2025-08-12 23:59:59'),
        ]);

        // Future block (should be included)
        $futureBlock = ChannelVideoBlock::factory()->create([
            'channel_id' => $channel->getKey(),
            'video_id' => $videoFuture->getKey(),
            'until' => Carbon::parse('2025-08-20 00:00:00'),
        ]);

        $activeIds = $channel->activeVideoBlocks()->pluck('id')->all();

        $this->assertContains($futureBlock->getKey(), $activeIds);
        $this->assertCount(1, $channel->activeVideoBlocks()->get());
    }

    public function testBlockedVideosBelongsToManyIncludesPivotUntil(): void
    {
        $channel = Channel::factory()->create();
        $video = Video::factory()->create();

        $until = Carbon::now()->addDays(3);
        ChannelVideoBlock::factory()->create([
            'channel_id' => $channel->getKey(),
            'video_id' => $video->getKey(),
            'until' => $until,
        ]);

        $fetched = $channel->blockedVideos()->first();
        $this->assertNotNull($fetched);
        $this->assertSame($video->getKey(), $fetched->getKey());

        // Pivot exists and carries "until"
        $this->assertNotNull($fetched->pivot);
        $this->assertTrue($until->equalTo(Carbon::parse($fetched->pivot->until)));
    }
}
