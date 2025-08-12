<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enum\StatusEnum;
use App\Mail\ChannelAssignmentMail;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use App\Services\AssignmentService;
use App\Services\ChannelNotifier;
use Illuminate\Support\Facades\Mail;
use Tests\DatabaseTestCase;

/**
 * Integration tests for ChannelNotifier::notify().
 *
 * - Uses real DB (DatabaseTestCase).
 * - Mails are faked (no real delivery).
 * - AssignmentService is mocked to return deterministic URLs and to assert call count.
 */
class ChannelNotifierTest extends DatabaseTestCase
{
    public function testNotifyQueuesOneEmailPerChannelAndUpdatesBatch(): void
    {
        // Arrange: two channels
        $ch1 = Channel::factory()->create(['email' => 'ch1@example.test']);
        $ch2 = Channel::factory()->create(['email' => 'ch2@example.test']);

        // Arrange: videos
        $v1 = Video::factory()->create(['hash' => 'h1', 'bytes' => 111, 'ext' => 'mp4']);
        $v2 = Video::factory()->create(['hash' => 'h2', 'bytes' => 222, 'ext' => 'mp4']);
        $v3 = Video::factory()->create(['hash' => 'h3', 'bytes' => 333, 'ext' => 'avi']);

        // IMPORTANT: assignments.batch_id is NOT NULL -> attach a batch to each assignment
        $assignBatch = Batch::factory()->create(['type' => 'assign', 'started_at' => now()]);

        // Arrange: queued assignments (ch1 gets two, ch2 gets one)
        $a1 = Assignment::factory()->for($ch1, 'channel')->for($v1, 'video')->for($assignBatch, 'batch')->create([
            'status' => StatusEnum::QUEUED->value,
        ]);
        $a2 = Assignment::factory()->for($ch1, 'channel')->for($v2, 'video')->for($assignBatch, 'batch')->create([
            'status' => StatusEnum::QUEUED->value,
        ]);
        $a3 = Assignment::factory()->for($ch2, 'channel')->for($v3, 'video')->for($assignBatch, 'batch')->create([
            'status' => StatusEnum::QUEUED->value,
        ]);

        // Fake mail delivery
        Mail::fake();

        // Mock AssignmentService: return URL per assignment and assert call count
        $ttlHours = 48;
        $this->mock(AssignmentService::class, function ($mock) use ($ttlHours) {
            $mock->shouldReceive('prepareDownload')
                ->andReturnUsing(function (Assignment $a, int $ttl) use ($ttlHours) {
                    if ($ttl !== $ttlHours) {
                        throw new \RuntimeException('Unexpected TTL');
                    }
                    return "https://example.test/dl/{$a->getKey()}";
                })
                ->times(3); // 3 queued assignments total
        });

        // Act
        $sentCount = app(ChannelNotifier::class)->notify($ttlHours);

        // Assert: service returns number of distinct channels with queued items
        $this->assertSame(2, $sentCount);

        // Assert: exactly one mail per channel queued, to the correct recipient
        Mail::assertQueued(ChannelAssignmentMail::class, function ($mail) use ($ch1) {
            return $mail->hasTo($ch1->email);
        });
        Mail::assertQueued(ChannelAssignmentMail::class, function ($mail) use ($ch2) {
            return $mail->hasTo($ch2->email);
        });

        // Assert: a Batch (type=notify) was created and finalized with correct stats
        $batch = Batch::query()->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame('notify', $batch->type);
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->finished_at);
        $this->assertEquals(['emails' => 2], $batch->stats);
    }

    public function testNotifyWithNoQueuedAssignmentsSendsNoEmailsAndCreatesBatchWithZeroStats(): void
    {
        Mail::fake();

        // Mock service but expect zero calls when nothing is queued
        $this->mock(AssignmentService::class, function ($mock) {
            $mock->shouldReceive('prepareDownload')->never();
        });

        // Act
        $sentCount = app(ChannelNotifier::class)->notify(24);

        // Assert
        $this->assertSame(0, $sentCount);
        Mail::assertNothingQueued();

        $batch = Batch::query()->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame('notify', $batch->type);
        $this->assertEquals(['emails' => 0], $batch->stats);
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->finished_at);
    }
}
