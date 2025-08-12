<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enum\StatusEnum;
use App\Mail\NewOfferMail;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Tests\DatabaseTestCase;

/**
 * Feature tests for the "notify:offers" console command using the real OfferNotifier.
 * We avoid mocking services; assertions are based on DB side effects and console output.
 */
final class NotifyOffersTest extends DatabaseTestCase
{
    /**
     * Happy path: ready assignments exist for multiple channels.
     * Expect queued mails per channel, a new "notify" batch with stats, and success exit code.
     */
    public function testQueuesOfferEmailsAndCreatesNotifyBatch(): void
    {
        Mail::fake();

        // Arrange: finished assign-batch that BatchService::getLatestAssignBatch() can discover
        $assignBatch = Batch::factory()
            ->state(['type' => 'assign'])
            ->create(['started_at' => now()->subHour(), 'finished_at' => now()->subMinute()]);

        // Two channels (ch1 has two ready items, ch2 has one)
        $ch1 = Channel::factory()->create(['email' => 'ch1@example.test']);
        $ch2 = Channel::factory()->create(['email' => 'ch2@example.test']);

        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();
        $v3 = Video::factory()->create();

        // Ready statuses (use your enum values)
        Assignment::factory()
            ->for($assignBatch, 'batch')->for($ch1, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);

        Assignment::factory()
            ->for($assignBatch, 'batch')->for($ch1, 'channel')->for($v2, 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);

        Assignment::factory()
            ->for($assignBatch, 'batch')->for($ch2, 'channel')->for($v3, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);

        // Irrelevant: different batch or non-ready status
        Assignment::factory()
            ->for(Batch::factory()->state(['type' => 'assign'])
                ->create(['started_at' => now()->subHours(2), 'finished_at' => now()->subHour()]), 'batch')
            ->for($ch2, 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);

        Assignment::factory()
            ->for($assignBatch, 'batch')->for(Channel::factory(), 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        // Act: run command with explicit ttl-days (any value ok; only used for link TTL)
        $this->artisan('notify:offers --ttl-days=5')
            ->expectsOutputToContain('Offer emails queued: 2')
            ->expectsOutputToContain('Assign-Batch #'.$assignBatch->getKey())
            ->assertExitCode(Command::SUCCESS);

        // Assert: one mail per channel got queued
        Mail::assertQueued(NewOfferMail::class, function (NewOfferMail $m) use ($ch1) {
            return $m->hasTo($ch1->email);
        });
        Mail::assertQueued(NewOfferMail::class, function (NewOfferMail $m) use ($ch2) {
            return $m->hasTo($ch2->email);
        });

        // Assert: a "notify" batch with correct stats exists
        $notifyBatch = Batch::query()->where('type', 'notify')->latest('id')->first();
        $this->assertNotNull($notifyBatch);
        $this->assertNotNull($notifyBatch->started_at);
        $this->assertNotNull($notifyBatch->finished_at);
        $this->assertIsArray($notifyBatch->stats);
        $this->assertSame(['emails' => 2], $notifyBatch->stats);
    }

    /**
     * No ready assignments for the latest finished assign-batch:
     * the command should succeed, print "Keine Kanäle...", queue no mails,
     * and create a "notify" batch with emails=0.
     */
    public function testNoReadyAssignmentsPrintsNoneAndCreatesZeroStatBatch(): void
    {
        Mail::fake();

        // Arrange: finished assign-batch but no ready items attached to it
        Batch::factory()
            ->state(['type' => 'assign'])
            ->create(['started_at' => now()->subHour(), 'finished_at' => now()->subMinute()]);

        $this->artisan('notify:offers')
            ->expectsOutput('Keine Kanäle mit neuen Angeboten.')
            ->assertExitCode(Command::SUCCESS);

        Mail::assertNothingQueued();

        $notifyBatch = Batch::query()->where('type', 'notify')->latest('id')->first();
        $this->assertNotNull($notifyBatch);
        $this->assertSame(['emails' => 0], $notifyBatch->stats);
    }

    /**
     * No finished assign-batch available at all:
     * BatchService will throw; the command should warn and return FAILURE.
     */
    public function testWarnsAndFailsWhenNoAssignBatchExists(): void
    {
        Mail::fake();

        // No assign-batch created on purpose
        $this->artisan('notify:offers')
            ->expectsOutputToContain('Assign-Batch') // part of the thrown message
            ->assertExitCode(Command::FAILURE);

        Mail::assertNothingQueued();
        $this->assertNull(Batch::query()->where('type', 'notify')->latest('id')->first());
    }
}
