<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\ChannelVideoBlock;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Tests\DatabaseTestCase;

/**
 * Feature tests for the "assign:expire" console command using the real AssignmentExpirer.
 */
final class AssignExpireTest extends DatabaseTestCase
{
    /**
     * Happy path: two notified assignments are past TTL and must be marked "expired",
     * cooldown blocks are created for (channel, video), and a batch with stats is persisted.
     */
    public function testExpiresPastTtlAndCreatesCooldownBlocksAndBatchStats(): void
    {
        Carbon::setTestNow('2025-08-12 12:00:00');

        // Arrange: channels & videos (ensure unique (channel_id, video_id) pairs)
        $ch1 = Channel::factory()->create();
        $ch2 = Channel::factory()->create();
        $ch3 = Channel::factory()->create();

        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();
        $v3 = Video::factory()->create();

        // Use an already finished "assign" batch to satisfy NOT NULL constraints in factories
        $baseBatch = Batch::factory()->state(['type' => 'assign'])
            ->create(['started_at' => now()->subHour(), 'finished_at' => now()->subMinute()]);

        // Past-TTL notified → should expire
        $a1 = Assignment::factory()
            ->for($baseBatch, 'batch')->for($ch1, 'channel')->for($v1, 'video')
            ->create(['status' => 'notified', 'expires_at' => now()->subDay()]);

        $a2 = Assignment::factory()
            ->for($baseBatch, 'batch')->for($ch2, 'channel')->for($v2, 'video')
            ->create(['status' => 'notified', 'expires_at' => now()->subMinutes(5)]);

        // Not yet expired → must remain notified
        $a3 = Assignment::factory()
            ->for($baseBatch, 'batch')->for($ch3, 'channel')->for($v3, 'video')
            ->create(['status' => 'notified', 'expires_at' => now()->addHour()]);

        // Different status → must be ignored
        $queued = Assignment::factory()
            ->for($baseBatch, 'batch')->for($ch1, 'channel')->for(Video::factory(), 'video')
            ->create(['status' => 'queued', 'expires_at' => now()->subDay()]);

        // Act: run the real command with a custom cooldown
        $cooldownDays = 10;
        $this->artisan("assign:expire --cooldown-days={$cooldownDays}")
            ->expectsOutput('Expired: 2')
            ->assertExitCode(Command::SUCCESS);

        // Assert: expired statuses
        $this->assertDatabaseHas('assignments', ['id' => $a1->getKey(), 'status' => 'expired']);
        $this->assertDatabaseHas('assignments', ['id' => $a2->getKey(), 'status' => 'expired']);

        // Assert: unaffected items
        $this->assertDatabaseHas('assignments', ['id' => $a3->getKey(), 'status' => 'notified']);
        $this->assertDatabaseHas('assignments', ['id' => $queued->getKey(), 'status' => 'queued']);

        // Assert: cooldown blocks created/updated with correct "until"
        $until = now()->addDays($cooldownDays);
        $blk1 = ChannelVideoBlock::query()
            ->where('channel_id', $ch1->getKey())->where('video_id', $v1->getKey())->first();
        $blk2 = ChannelVideoBlock::query()
            ->where('channel_id', $ch2->getKey())->where('video_id', $v2->getKey())->first();

        $this->assertNotNull($blk1);
        $this->assertNotNull($blk2);
        $this->assertTrue($blk1->until->equalTo($until));
        $this->assertTrue($blk2->until->equalTo($until));

        // Assert: a new assign batch was created and finalized with stats
        $newBatch = Batch::query()->where('type', 'assign')->latest('id')->first();
        $this->assertNotNull($newBatch);
        $this->assertNotNull($newBatch->started_at);
        $this->assertNotNull($newBatch->finished_at);
        $this->assertIsArray($newBatch->stats);
        $this->assertSame(['expired' => 2], $newBatch->stats);
    }

    /** When nothing is past TTL, the command still succeeds and reports zero. */
    public function testNoItemsToExpireStillSucceedsAndReportsZero(): void
    {
        Carbon::setTestNow('2025-08-12 12:00:00');

        $ch = Channel::factory()->create();
        $v = Video::factory()->create();
        $batch = Batch::factory()->state(['type' => 'assign'])
            ->create(['started_at' => now()->subHour(), 'finished_at' => now()->subMinute()]);

        Assignment::factory()
            ->for($batch, 'batch')->for($ch, 'channel')->for($v, 'video')
            ->create(['status' => 'notified', 'expires_at' => now()->addHour()]); // not expired

        $this->artisan('assign:expire')
            ->expectsOutput('Expired: 0')
            ->assertExitCode(Command::SUCCESS);

        // Latest batch should have stats expired=0
        $latest = Batch::query()->where('type', 'assign')->latest('id')->first();
        $this->assertNotNull($latest);
        $this->assertIsArray($latest->stats);
        $this->assertSame(['expired' => 0], $latest->stats);
    }
}
