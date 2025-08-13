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

        // Use distinct videos to satisfy (video_id, channel_id) unique constraint.
        $videoA = Video::factory()->create([
            'hash' => 'hash-a',
            'ext' => 'mp4',
            'bytes' => 111,
            'path' => 'videos/aa/bb/hash-a.mp4',
            'disk' => 'local',
        ]);
        $videoB = Video::factory()->create([
            'hash' => 'hash-b',
            'ext' => 'mp4',
            'bytes' => 222,
            'path' => 'videos/cc/dd/hash-b.mp4',
            'disk' => 'local',
        ]);
        $videoC = Video::factory()->create([
            'hash' => 'hash-c',
            'ext' => 'mp4',
            'bytes' => 333,
            'path' => 'videos/ee/ff/hash-c.mp4',
            'disk' => 'local',
        ]);

        // a1: ready (QUEUED) for $channel
        $a1 = Assignment::factory()->create([
            'batch_id' => $batch->getKey(),
            'channel_id' => $channel->getKey(),
            'video_id' => $videoA->getKey(),
            'status' => StatusEnum::QUEUED->value,
        ]);

        // a2: ready (NOTIFIED) for the same $channel but different video
        $a2 = Assignment::factory()->create([
            'batch_id' => $batch->getKey(),
            'channel_id' => $channel->getKey(),
            'video_id' => $videoB->getKey(),
            'status' => StatusEnum::NOTIFIED->value,
        ]);

        // a3: not ready (PICKED_UP) for the same $channel, different video
        $a3 = Assignment::factory()->create([
            'batch_id' => $batch->getKey(),
            'channel_id' => $channel->getKey(),
            'video_id' => $videoC->getKey(),
            'status' => StatusEnum::PICKEDUP->value,
        ]);

        // a4: ready but different channel (should be filtered out)
        $a4 = Assignment::factory()->create([
            'batch_id' => $batch->getKey(),
            'channel_id' => $otherChannel->getKey(),
            'video_id' => $videoA->getKey(), // can reuse video across channels
            'status' => StatusEnum::QUEUED->value,
        ]);

        $zipSpy = new SpyZipService();
        $assignmentService = app(AssignmentService::class);

        // Provide all IDs; fetchForZip must filter them by batch/channel and ready statuses.
        $ids = [$a1->getKey(), $a2->getKey(), $a3->getKey(), $a4->getKey()];

        $job = new BuildZipJob(
            batchId: $batch->getKey(),
            channelId: $channel->getKey(),
            assignmentIds: $ids,
            ip: '203.0.113.10',
            userAgent: null, // job passes '' to ZipService when null
        );

        // Act
        $job->handle($assignmentService, $zipSpy);

        // Assert: batch/channel forwarded correctly
        $this->assertSame($batch->getKey(), $zipSpy->seenBatchId);
        $this->assertSame($channel->getKey(), $zipSpy->seenChannelId);

        // Only ready assignments for target batch+channel make it through
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
        // Arrange: batch & channel with no matching assignments
        $batch = Batch::factory()->create([
            'type' => 'assign',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        $channel = Channel::factory()->create();

        // Create an assignment in a different batch to ensure no match.
        $otherBatch = Batch::factory()->create([
            'type' => 'assign',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $video = Video::factory()->create([
            'hash' => 'hash-x',
            'ext' => 'mp4',
            'bytes' => 444,
            'path' => 'videos/xx/yy/hash-x.mp4',
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
            assignmentIds: [], // none provided
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