<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use Illuminate\Console\Command;
use Tests\DatabaseTestCase;

/**
 * Feature tests for the "assign:distribute" command using the real distributor.
 * No mocking/faking of services; we assert DB side-effects instead of brittle output.
 */
final class AssignDistributeFeatureTest extends DatabaseTestCase
{
    /**
     * Happy path: with two eligible channels and two videos, the command should
     * create an "assign" batch and queued assignments for our entities.
     * We avoid asserting exact console text to keep the test robust.
     */
    public function testDistributesAndCreatesQueuedAssignments(): void
    {
        // Arrange: two eligible channels
        $ch1 = Channel::factory()->create(['weekly_quota' => 10, 'weight' => 1]);
        $ch2 = Channel::factory()->create(['weekly_quota' => 10, 'weight' => 1]);

        // Arrange: two new videos
        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();

        // Act: run the real command
        $this->artisan('assign:distribute')
            ->assertExitCode(Command::SUCCESS);

        // Assert: a new assign batch exists and is finalized
        $batch = Batch::query()->where('type', 'assign')->latest('id')->first();
        $this->assertNotNull($batch, 'Expected a new assign batch to be created.');
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->finished_at);
        $this->assertIsArray($batch->stats);

        // Assert: at least one assignment for this batch
        $created = Assignment::query()->where('batch_id', $batch->getKey())->get();
        $this->assertGreaterThanOrEqual(1, $created->count(), 'Expected at least one assignment to be created.');

        // All created assignments must reference our channels and videos and be queued
        $channelIds = [$ch1->getKey(), $ch2->getKey()];
        $videoIds = [$v1->getKey(), $v2->getKey()];

        foreach ($created as $a) {
            $this->assertContains($a->channel_id, $channelIds, 'Unexpected channel assigned.');
            $this->assertContains($a->video_id, $videoIds, 'Unexpected video assigned.');
            $this->assertSame('queued', $a->status, 'Expected new assignments to be queued.');
        }
    }

    /**
     * No eligible work: many distributors treat this as an error and throw,
     * so the command returns FAILURE. We only assert that no assignments were created.
     */
    public function testRunsWithNoEligibleWorkReturnsFailureAndCreatesNoAssignments(): void
    {
        // Arrange: no channels, no videos

        // Act & Assert: command fails gracefully
        $this->artisan('assign:distribute')
            ->assertExitCode(Command::FAILURE);

        // Assert: no assignments created at all
        $this->assertSame(0, Assignment::query()->count());
        // Optional/loose: do not assert on batch presence, as implementations differ
    }
}
