<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enum\StatusEnum;
use App\Jobs\BuildZipJob;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use App\Services\AssignmentService;
use Tests\DatabaseTestCase;

final class BuildZipJobTest extends DatabaseTestCase
{
    public function testHandleFiltersAssignmentsAndCallsZipServiceWithExpectedArgs(): void
    {
        // Arrange: batch & channels
        $batch = Batch::factory()->create([
            'type' => 'assign',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        $channel = Channel::factory()->create();
        $otherChannel = Channel::factory()->create();

        // Video
        $video = Video::factory()->create([
            'hash' => 'abc',
            'ext' => 'mp4',
            'bytes' => 1234,
            'path' => 'videos/ab/cd/abcdef.mp4',
            'disk' => 'local',
        ]);

        // Assignments:
        // a1: ready (QUEUED)
        $a1 = Assignment::factory()->create([
            'batch_id' => $batch->getKey(),
            'channel_id' => $channel->getKey(),
            'video_id' => $video->getKey(),
            'status' => StatusEnum::QUEUED->value,
        ]);

        // a2: ready (NOTIFIED)
        $a2 = Assignment::factory()->create([
            'batch_id' => $batch->getKey(),
            'channel_id' => $channel->getKey(),
            'video_id' => $video->getKey(),
            'status' => StatusEnum::NOTIFIED->value,
        ]);

        // a3: not ready (PICKED_UP)
        $a3 = Assignment::factory()->create([
            'batch_id' => $batch->getKey(),
            'channel_id' => $channel->getKey(),
            'video_id' => $video->getKey(),
            'status' => StatusEnum::PICKEDUP->value,
        ]);

        // a4: ready but different channel (should be filtered out)
        $a4 = Assignment::factory()->create([
            'batch_id' => $batch->getKey(),
            'channel_id' => $otherChannel->getKey(),
            'video_id' => $video->getKey(),
            'status' => StatusEnum::QUEUED->value,
        ]);

        // Spy ZipService and real AssignmentService
        $zipSpy = new SpyZipService();
        $assignmentService = app(AssignmentService::class);

        // Provide all IDs; fetchForZip must filter them
        $ids = [$a1->getKey(), $a2->getKey(), $a3->getKey(), $a4->getKey()];

        $job = new BuildZipJob(
            batchId: $batch->getKey(),
            channelId: $channel->getKey(),
            assignmentIds: $ids,
            ip: '203.0.113.10',
            userAgent: null, // job normalizes to ''
        );

        // Act
        $job->handle($assignmentService, $zipSpy);

        // Assert: batch/channel forwarded correctly
        $this->assertSame($batch->getKey(), $zipSpy->seenBatchId);
        $this->assertSame($channel->getKey(), $zipSpy->seenChannelId);

        // Only ready assignments for the correct batch & channel make it through
        $this->assertEqualsCanonicalizing(
            [$a1->getKey(), $a2->getKey()],
            $zipSpy->seenAssignmentIds
        );

        // IP & UA forwarded correctly (UA becomes empty string)
        $this->assertSame('203.0.113.10', $zipSpy->seenIp);
        $this->assertSame('', $zipSpy->seenUserAgent);
    }

    public function testHandleWithNoMatchingAssignmentsStillCallsZipServiceWithEmptyCollection(): void
    {
        // Arrange: batch/channel without matching assignments
        $batch = Batch::factory()->create([
            'type' => 'assign',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        $channel = Channel::factory()->create();

        // Irrelevant assignment in different batch
        $otherBatch = Batch::factory()->create([
            'type' => 'assign',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $video = Video::factory()->create([
            'hash' => 'def',
            'ext' => 'mp4',
            'bytes' => 5678,
            'path' => 'videos/de/f0/def0.mp4',
            'disk' => 'local',
        ]);

        Assignment::factory()->create([
            'batch_id' => $otherBatch->getKey(),
            'channel_id' => $channel->getKey(),
            'video_id' => $video->getKey(),
            'status' => StatusEnum::QUEUED->value,
        ]);

        $zipSpy = new SpyZipService();
        $assignmentService = app(AssignmentService::class);

        $job = new BuildZipJob(
            batchId: $batch->getKey(),
            channelId: $channel->getKey(),
            assignmentIds: [], // none
            ip: '198.51.100.20',
            userAgent: 'TestAgent/1.0',
        );

        // Act
        $job->handle($assignmentService, $zipSpy);

        // Assert
        $this->assertSame($batch->getKey(), $zipSpy->seenBatchId);
        $this->assertSame($channel->getKey(), $zipSpy->seenChannelId);
        $this->assertSame([], $zipSpy->seenAssignmentIds);
        $this->assertSame('198.51.100.20', $zipSpy->seenIp);
        $this->assertSame('TestAgent/1.0', $zipSpy->seenUserAgent);
    }
}