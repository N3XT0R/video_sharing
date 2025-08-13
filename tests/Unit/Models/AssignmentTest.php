<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Tests\DatabaseTestCase;

/**
 * Unit tests for the App\Models\Assignment model.
 *
 * We validate:
 *  - mass assignment for fillable attributes
 *  - datetime casts for "expires_at" and "last_notified_at"
 *  - belongsTo relationships (video, channel, batch)
 *  - status / attempts updates persist correctly
 */
final class AssignmentTest extends DatabaseTestCase
{
    public function testMassAssignmentAndCasts(): void
    {
        $expires = '2025-08-20 10:00:00';
        $notified = '2025-08-19 08:30:00';

        $assignment = Assignment::factory()
            ->for(Batch::factory()->type('assign')->finished(), 'batch')
            ->for(Channel::factory(), 'channel')
            ->for(Video::factory(), 'video')
            ->create([
                'status' => 'queued',
                'expires_at' => $expires,
                'attempts' => 0,
                'last_notified_at' => $notified,
                'download_token' => 'tok_abc123',
            ])->fresh();

        // Fillable attributes persisted
        $this->assertSame('queued', $assignment->status);
        $this->assertSame(0, $assignment->attempts);
        $this->assertSame('tok_abc123', $assignment->download_token);

        // Casts are Carbon instances and match expected timestamps
        $this->assertInstanceOf(Carbon::class, $assignment->expires_at);
        $this->assertInstanceOf(Carbon::class, $assignment->last_notified_at);
        $this->assertTrue($assignment->expires_at->equalTo(Carbon::parse($expires)));
        $this->assertTrue($assignment->last_notified_at->equalTo(Carbon::parse($notified)));
    }

    public function testBelongsToRelationshipsResolveParents(): void
    {
        $batch = Batch::factory()->type('assign')->finished()->create();
        $channel = Channel::factory()->create();
        $video = Video::factory()->create();

        $assignment = Assignment::factory()
            ->for($batch, 'batch')
            ->for($channel, 'channel')
            ->for($video, 'video')
            ->create();

        $this->assertNotNull($assignment->video);
        $this->assertNotNull($assignment->channel);
        $this->assertNotNull($assignment->batch);

        $this->assertSame($video->getKey(), $assignment->video->getKey());
        $this->assertSame($channel->getKey(), $assignment->channel->getKey());
        $this->assertSame($batch->getKey(), $assignment->batch->getKey());
    }

    public function testStatusAndAttemptsUpdatePersists(): void
    {
        $assignment = Assignment::factory()
            ->for(Batch::factory()->type('assign')->finished(), 'batch')
            ->for(Channel::factory(), 'channel')
            ->for(Video::factory(), 'video')
            ->create([
                'status' => 'queued',
                'attempts' => 0,
            ]);

        // Update attributes
        $assignment->update([
            'status' => 'notified',
            'attempts' => 1,
        ]);

        $fresh = $assignment->fresh();
        $this->assertSame('notified', $fresh->status);
        $this->assertSame(1, $fresh->attempts);
    }
}
