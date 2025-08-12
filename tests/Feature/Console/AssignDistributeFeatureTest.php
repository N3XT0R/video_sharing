<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Tests\DatabaseTestCase;

/**
 * Feature tests for the "assign:distribute" command.
 *
 * These tests execute the real command with the real AssignmentDistributor and
 * assert database side-effects (no mocking or faking of services).
 */
final class AssignDistributeFeatureTest extends DatabaseTestCase
{
    /**
     * Happy path: with two eligible channels and two videos, the command should
     * create an "assign" batch, produce queued assignments for the new batch,
     * and print a summary. We intentionally do not assert the exact round-robin
     * distribution, only that assignments were created for the new batch and
     * reference our channels/videos.
     */
    public function testDistributesAndCreatesQueuedAssignments(): void
    {
        // Arrange: two channels with generous weekly quota so they are eligible
        $ch1 = Channel::factory()->create(['weekly_quota' => 10, 'weight' => 1]);
        $ch2 = Channel::factory()->create(['weekly_quota' => 10, 'weight' => 1]);

        // Arrange: two new videos without any prior assignments/blocks
        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();

        // Sanity: no "assign" batch exists yet
        $this->assertNull(Batch::query()->where('type', 'assign')->latest('id')->first());

        // Act: run the real command (no fakes)
        $this->artisan('assign:distribute')
            // We do not hard-assert full line to avoid fragility; just ensure summary headings appear.
            ->expectsOutputToContain('Assigned=')
            ->expectsOutputToContain('skipped=')
            ->assertExitCode(CommandAlias::SUCCESS);

        // Assert: a new assign batch was created and finalized
        $batch = Batch::query()->where('type', 'assign')->latest('id')->first();
        $this->assertNotNull($batch, 'Expected a new assign batch to be created.');
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->finished_at);
        $this->assertIsArray($batch->stats);
        $this->assertArrayHasKey('assigned', $batch->stats);
        $this->assertArrayHasKey('skipped', $batch->stats);

        // Assert: assignments exist for this batch and reference our entities
        $created = Assignment::query()->where('batch_id', $batch->getKey())->get();

        // There should be at least one assignment (most implementations will create two here)
        $this->assertGreaterThanOrEqual(1, $created->count(), 'Expected at least one assignment to be created.');

        // All created assignments must reference our prepared channels and videos
        $channelIds = [$ch1->getKey(), $ch2->getKey()];
        $videoIds = [$v1->getKey(), $v2->getKey()];

        foreach ($created as $a) {
            $this->assertContains($a->channel_id, $channelIds, 'Unexpected channel assigned.');
            $this->assertContains($a->video_id, $videoIds, 'Unexpected video assigned.');
            // New assignments should typically start as "queued"
            $this->assertSame('queued', $a->status, 'Expected new assignments to be queued.');
        }

        // Optional: If the distributor reports exact counts, they should match DB reality.
        // We only check the type and presence above to keep the test robust.
    }

    /**
     * Edge case: with no eligible work the command should still create an assign
     * batch with stats present and exit successfully. This exercises the "nothing to do" path.
     */
    public function testRunsWithNoEligibleWorkAndStillProducesBatchAndSummary(): void
    {
        // Arrange: no channels, no videos → nothing to distribute

        // Act
        $this->artisan('assign:distribute')
            ->expectsOutputToContain('Assigned=')
            ->expectsOutputToContain('skipped=')
            ->assertExitCode(CommandAlias::SUCCESS);

        // Assert: a batch exists with stats keys populated
        $batch = Batch::query()->where('type', 'assign')->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertIsArray($batch->stats);
        $this->assertArrayHasKey('assigned', $batch->stats);
        $this->assertArrayHasKey('skipped', $batch->stats);

        // And obviously, no assignments created for this batch
        $this->assertSame(0, Assignment::query()->where('batch_id', $batch->getKey())->count());
    }
}
