<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Download;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Tests\DatabaseTestCase;

/**
 * Unit tests for the App\Models\Download model.
 *
 * We validate:
 *  - mass assignment for fillable fields
 *  - datetime cast for "downloaded_at"
 *  - belongsTo(Assignment) relationship
 *  - updating "bytes_sent" persists
 */
final class DownloadTest extends DatabaseTestCase
{
    public function testMassAssignmentAndCasts(): void
    {
        // Arrange: create minimal assignment (video, channel, batch via factories)
        $assignment = Assignment::factory()
            ->for(Batch::factory()->type('assign')->finished(), 'batch')
            ->for(Channel::factory(), 'channel')
            ->for(Video::factory(), 'video')
            ->create();

        // Act: create a Download via mass assignment using fillable fields
        $when = '2025-08-10 10:00:00';
        $download = Download::query()->create([
            'assignment_id' => $assignment->getKey(),
            'downloaded_at' => $when,
            'ip' => '203.0.113.5',
            'user_agent' => 'curl/7.88.1',
            'bytes_sent' => 12345,
        ])->fresh();

        // Assert: fields persisted as provided
        $this->assertSame($assignment->getKey(), $download->assignment_id);
        $this->assertSame('203.0.113.5', $download->ip);
        $this->assertSame('curl/7.88.1', $download->user_agent);
        $this->assertSame(12345, $download->bytes_sent);

        // Assert: "downloaded_at" is cast to Carbon and matches the timestamp
        $this->assertInstanceOf(Carbon::class, $download->downloaded_at);
        $this->assertTrue($download->downloaded_at->equalTo(Carbon::parse($when)));
    }

    public function testAssignmentRelationshipReturnsParent(): void
    {
        // Arrange
        $assignment = Assignment::factory()
            ->for(Batch::factory()->type('assign')->finished(), 'batch')
            ->for(Channel::factory(), 'channel')
            ->for(Video::factory(), 'video')
            ->create();

        $download = Download::factory()
            ->for($assignment, 'assignment')
            ->create();

        // Act
        $parent = $download->assignment;

        // Assert
        $this->assertNotNull($parent);
        $this->assertSame($assignment->getKey(), $parent->getKey());
    }

    public function testBytesSentCanBeUpdatedAndPersisted(): void
    {
        // Arrange
        $download = Download::factory()
            ->for(
                Assignment::factory()
                    ->for(Batch::factory()->type('assign')->finished(), 'batch')
                    ->for(Channel::factory(), 'channel')
                    ->for(Video::factory(), 'video'),
                'assignment'
            )
            ->create([
                'bytes_sent' => null,
            ]);

        // Act
        $download->update(['bytes_sent' => 987654]);
        $download = $download->fresh();

        // Assert
        $this->assertSame(987654, $download->bytes_sent);
    }
}
