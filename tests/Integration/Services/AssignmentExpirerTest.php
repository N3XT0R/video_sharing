<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\ChannelVideoBlock;
use App\Models\Video;
use App\Services\AssignmentExpirer;
use Illuminate\Support\Carbon;
use Tests\DatabaseTestCase;

class AssignmentExpirerTest extends DatabaseTestCase
{
    public function testExpireMarksPastTtlNotifiedAsExpiredUpdatesExistingBlocksAndCreatesNewOnes(): void
    {
        // Freeze time for deterministic assertions
        Carbon::setTestNow('2025-08-12 12:00:00');

        $cooldownDays = 5;
        $now = now();
        $past = $now->copy()->subDay();
        $future = $now->copy()->addDay();

        // One channel, two distinct videos (unique constraint prohibits duplicate (video, channel) pairs)
        $ch = Channel::factory()->create();
        $vid1 = Video::factory()->create();
        $vid2 = Video::factory()->create();

        // Ensure assignments have a non-null batch_id
        $assignBatch = Batch::factory()->type('assign')->create(['started_at' => $now]);

        // Pre-create an existing block for (ch, vid1) to exercise the "update" path (not a new row)
        $existingBlock = ChannelVideoBlock::factory()
            ->for($ch, 'channel')
            ->for($vid1, 'video')
            ->until($now->copy()->addDay()) // will be updated to now + cooldownDays
            ->create();

        // Expired (status=notified, expires_at in the past) for (ch, vid1) and (ch, vid2)
        $a1 = Assignment::factory()
            ->for($ch, 'channel')->for($vid1, 'video')->for($assignBatch, 'batch')
            ->create(['status' => 'notified', 'expires_at' => $past]);

        $a2 = Assignment::factory()
            ->for($ch, 'channel')->for($vid2, 'video')->for($assignBatch, 'batch')
            ->create(['status' => 'notified', 'expires_at' => $past]);

        // Not expired yet (future) -> should remain 'notified'
        $a3 = Assignment::factory()
            ->for($ch, 'channel')->for(Video::factory(), 'video')->for($assignBatch, 'batch')
            ->create(['status' => 'notified', 'expires_at' => $future]);

        // Different status -> should be ignored even if past
        $a4 = Assignment::factory()
            ->for($ch, 'channel')->for(Video::factory(), 'video')->for($assignBatch, 'batch')
            ->create(['status' => 'queued', 'expires_at' => $past]);

        // Act
        $expiredCount = app(AssignmentExpirer::class)->expire($cooldownDays);

        // Assert: two items expired
        $this->assertSame(2, $expiredCount);

        // Reload and assert statuses
        $this->assertSame('expired', $a1->fresh()->status);
        $this->assertSame('expired', $a2->fresh()->status);
        $this->assertSame('notified', $a3->fresh()->status);
        $this->assertSame('queued', $a4->fresh()->status);

        // Assert: exactly two blocks exist (one updated for vid1, one newly created for vid2)
        $this->assertDatabaseCount('channel_video_blocks', 2);

        // (ch, vid1) block was updated in-place (same id) and has the new "until"
        $updatedBlock1 = ChannelVideoBlock::query()
            ->where('channel_id', $ch->id)
            ->where('video_id', $vid1->id)
            ->first();
        $this->assertNotNull($updatedBlock1);
        $this->assertSame($existingBlock->getKey(), $updatedBlock1->getKey());
        $this->assertTrue($updatedBlock1->until->equalTo($now->copy()->addDays($cooldownDays)));

        // (ch, vid2) block was created with proper "until"
        $block2 = ChannelVideoBlock::query()
            ->where('channel_id', $ch->id)
            ->where('video_id', $vid2->id)
            ->first();
        $this->assertNotNull($block2);
        $this->assertTrue($block2->until->equalTo($now->copy()->addDays($cooldownDays)));

        // Assert: a new "assign" batch was created and finalized with stats ['expired' => 2]
        $createdBatch = Batch::query()->latest('id')->first();
        $this->assertNotNull($createdBatch);
        $this->assertSame('assign', $createdBatch->type);
        $this->assertNotNull($createdBatch->started_at);
        $this->assertNotNull($createdBatch->finished_at);
        $this->assertEquals(['expired' => 2], $createdBatch->stats);
    }

    public function testExpireWhenNoMatchesCreatesBatchWithZeroStats(): void
    {
        Carbon::setTestNow('2025-08-12 12:00:00');

        $ch = Channel::factory()->create();
        $vid1 = Video::factory()->create();
        $vid2 = Video::factory()->create();
        $assignBatch = Batch::factory()->type('assign')->create(['started_at' => now()]);

        // Future expiry -> not expired
        Assignment::factory()
            ->for($ch, 'channel')->for($vid1, 'video')->for($assignBatch, 'batch')
            ->create(['status' => 'notified', 'expires_at' => now()->addDay()]);

        // Past expiry but status != notified -> ignored
        Assignment::factory()
            ->for($ch, 'channel')->for($vid2, 'video')->for($assignBatch, 'batch')
            ->create(['status' => 'queued', 'expires_at' => now()->subDay()]);

        // Act
        $expiredCount = app(AssignmentExpirer::class)->expire(3);

        // Assert: nothing expired
        $this->assertSame(0, $expiredCount);
        $this->assertDatabaseCount('channel_video_blocks', 0);

        // A batch was still created with zero stats
        $createdBatch = Batch::query()->latest('id')->first();
        $this->assertNotNull($createdBatch);
        $this->assertSame('assign', $createdBatch->type);
        $this->assertEquals(['expired' => 0], $createdBatch->stats);
        $this->assertNotNull($createdBatch->started_at);
        $this->assertNotNull($createdBatch->finished_at);
    }
}
