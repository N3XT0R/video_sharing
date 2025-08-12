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
    public function testExpireMarksPastTtlNotifiedAsExpiredCreatesBlocksAndBatchStats(): void
    {
        // Freeze time for deterministic assertions
        Carbon::setTestNow('2025-08-12 12:00:00');

        $cooldownDays = 5;
        $now = now();
        $past = $now->copy()->subDay();
        $future = $now->copy()->addDay();

        // One channel & video shared by two "expired" assignments (to test updateOrCreate dedup)
        $ch = Channel::factory()->create();
        $vid = Video::factory()->create();

        // Ensure assignments have a non-null batch_id (attach any batch via factory/relation)
        $assignBatch = Batch::factory()->type('assign')->create(['started_at' => $now]);

        // Expired (status=notified, expires_at in the past) -> should be set to 'expired'
        $a1 = Assignment::factory()
            ->for($ch, 'channel')->for($vid, 'video')->for($assignBatch, 'batch')
            ->create(['status' => 'notified', 'expires_at' => $past]);

        // Another expired for the same (channel, video) -> should NOT create a second block row
        $a2 = Assignment::factory()
            ->for($ch, 'channel')->for($vid, 'video')->for($assignBatch, 'batch')
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

        // Assert: return value equals number of actually expired assignments
        $this->assertSame(2, $expiredCount);

        // Reload and assert statuses
        $this->assertSame('expired', $a1->fresh()->status);
        $this->assertSame('expired', $a2->fresh()->status);
        $this->assertSame('notified', $a3->fresh()->status);
        $this->assertSame('queued', $a4->fresh()->status);

        // Assert: exactly one ChannelVideoBlock for the (channel, video) pair, with correct "until"
        $this->assertDatabaseCount('channel_video_blocks', 1);
        $block = ChannelVideoBlock::query()
            ->where('channel_id', $ch->id)
            ->where('video_id', $vid->id)
            ->first();
        $this->assertNotNull($block);
        $this->assertTrue($block->until->equalTo($now->copy()->addDays($cooldownDays)));

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

        // Create some assignments that should not match the filter
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
