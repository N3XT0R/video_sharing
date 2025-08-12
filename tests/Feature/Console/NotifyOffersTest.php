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
 * Feature tests for the "notify:offers" command using the real OfferNotifier.
 * No service mocking; assertions rely on DB side-effects and queued mails.
 */
final class NotifyOffersTest extends DatabaseTestCase
{
    /**
     * Ready assignments exist for two distinct channels in the latest finished assign-batch.
     * Expect 2 mails queued (one per channel) and a "notify" batch with emails=2.
     */
    public function testQueuesOfferEmailsAndCreatesNotifyBatch(): void
    {
        Mail::fake();

        // Ensure THIS is the latest finished assign-batch (create last, no other assign-batches).
        $assignBatch = Batch::factory()
            ->state(['type' => 'assign'])
            ->create([
                'started_at' => now()->subHour(),
                'finished_at' => now()->subMinute(),
            ]);

        // Two channels; ch1 gets two ready items, ch2 gets one
        $ch1 = Channel::factory()->create(['email' => 'ch1@example.test']);
        $ch2 = Channel::factory()->create(['email' => 'ch2@example.test']);

        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();
        $v3 = Video::factory()->create();

        // Only attach "ready" statuses to the LATEST assign batch
        Assignment::factory()
            ->for($assignBatch, 'batch')->for($ch1, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);

        Assignment::factory()
            ->for($assignBatch, 'batch')->for($ch1, 'channel')->for($v2, 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);

        Assignment::factory()
            ->for($assignBatch, 'batch')->for($ch2, 'channel')->for($v3, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);

        // Act
        $this->artisan('notify:offers --ttl-days=5')
            ->assertExitCode(Command::SUCCESS);

        // One mail per channel queued
        Mail::assertQueued(NewOfferMail::class, fn(NewOfferMail $m) => $m->hasTo($ch1->email));
        Mail::assertQueued(NewOfferMail::class, fn(NewOfferMail $m) => $m->hasTo($ch2->email));

        // A "notify" batch with correct stats exists
        $notify = Batch::query()->where('type', 'notify')->latest('id')->first();
        $this->assertNotNull($notify);
        $this->assertNotNull($notify->started_at);
        $this->assertNotNull($notify->finished_at);
        $this->assertIsArray($notify->stats);
        $this->assertSame(['emails' => 2], $notify->stats);
    }

    /**
     * No ready assignments for the latest finished assign-batch:
     * command succeeds, prints the "none" message, queues no mail,
     * and DOES NOT create a "notify" batch (by design of OfferNotifier@notify).
     */
    public function testNoReadyAssignmentsPrintsNoneAndDoesNotCreateNotifyBatch(): void
    {
        Mail::fake();

        // Create exactly ONE finished assign-batch, but attach no ready assignments.
        $assignBatch = Batch::factory()
            ->state(['type' => 'assign'])
            ->create([
                'started_at' => now()->subHour(),
                'finished_at' => now()->subMinute(),
            ]);

        $this->artisan('notify:offers')
            ->expectsOutput('Keine Kanäle mit neuen Angeboten.')
            ->assertExitCode(Command::SUCCESS);

        Mail::assertNothingQueued();

        // No "notify" batch should be created when sent === 0
        $this->assertNull(Batch::query()->where('type', 'notify')->latest('id')->first());
    }

    /**
     * No finished assign-batch at all:
     * BatchService will throw; the command warns and returns FAILURE.
     */
    public function testFailsWhenNoAssignBatchExists(): void
    {
        Mail::fake();

        // Intentionally do not create any assign-batch
        $this->artisan('notify:offers')
            ->assertExitCode(Command::FAILURE);

        Mail::assertNothingQueued();
        $this->assertNull(Batch::query()->where('type', 'notify')->latest('id')->first());
    }
}
