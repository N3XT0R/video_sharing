<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\ZipProgressUpdated;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Unit tests for the ZipProgressUpdated event.
 *
 * We assert its core behavior without relying on a broadcaster:
 *  - constructor assigns public properties
 *  - implements ShouldBroadcastNow
 *  - broadcastOn / broadcastAs / broadcastWith return expected values
 *  - event can be dispatched and received with the same payload
 */
final class ZipProgressUpdatedTest extends TestCase
{
    public function testConstructorAssignsPublicPropertiesAndImplementsInterface(): void
    {
        // Arrange
        $jobId = '1_42';
        $status = 'packing';
        $progress = 67;
        $name = 'videos_1_channel-selected.zip';
        $files = ['a.mp4' => 'queued', 'b.mp4' => 'ready'];

        // Act
        $event = new ZipProgressUpdated($jobId, $status, $progress, $name, $files);

        // Assert: public properties are carried as-is
        $this->assertSame($jobId, $event->jobId);
        $this->assertSame($status, $event->status);
        $this->assertSame($progress, $event->progress);
        $this->assertSame($name, $event->name);
        $this->assertSame($files, $event->files);

        // Assert: broadcasts immediately
        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function testBroadcastOnReturnsZipChannelWithJobId(): void
    {
        $event = new ZipProgressUpdated('9_9', 'downloading', 25, 'foo.zip', []);

        $channel = $event->broadcastOn();

        // Channel instance and its name are correct
        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertSame('zip.9_9', $channel->name);
    }

    public function testBroadcastAsReturnsFixedEventName(): void
    {
        $event = new ZipProgressUpdated('x_y', 'ready', 100, 'bar.zip', []);
        $this->assertSame('zip.progress', $event->broadcastAs());
    }

    public function testBroadcastWithReturnsStructuredPayload(): void
    {
        $payload = ['v1.mp4' => 'packing', 'v2.mp4' => 'ready'];
        $event = new ZipProgressUpdated('7_1', 'packing', 40, 'bundle.zip', $payload);

        $this->assertSame([
            'status' => 'packing',
            'progress' => 40,
            'name' => 'bundle.zip',
            'files' => $payload,
        ], $event->broadcastWith());
    }

    public function testEventIsDispatchableAndPayloadSurvivesDispatch(): void
    {
        Event::fake();

        $jobId = 'a_b';
        $status = 'preparing';
        $progress = 10;
        $name = null;
        $files = ['x.mp4' => 'queued'];

        event(new ZipProgressUpdated($jobId, $status, $progress, $name, $files));

        Event::assertDispatched(ZipProgressUpdated::class,
            function (ZipProgressUpdated $e) use ($jobId, $status, $progress, $name, $files) {
                return $e->jobId === $jobId
                    && $e->status === $status
                    && $e->progress === $progress
                    && $e->name === $name
                    && $e->files === $files;
            });
    }
}
