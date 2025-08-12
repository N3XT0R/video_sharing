<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enum\StatusEnum;
use App\Mail\NewOfferMail;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use App\Services\BatchService;
use App\Services\LinkService;
use App\Services\OfferNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\DatabaseTestCase;

class OfferNotifierTest extends DatabaseTestCase
{
    public function testNotifyQueuesOneEmailPerReadyChannelAndCreatesNotifyBatch(): void
    {
        // Freeze time so expireDate (now()->addDays($ttlDays)) is deterministic
        Carbon::setTestNow('2025-08-12 10:00:00');

        // Arrange: two channels
        $ch1 = Channel::factory()->create(['email' => 'ch1@example.test']);
        $ch2 = Channel::factory()->create(['email' => 'ch2@example.test']);

        // Arrange: videos
        $v1 = Video::factory()->create(['hash' => 'h1', 'bytes' => 111, 'ext' => 'mp4']);
        $v2 = Video::factory()->create(['hash' => 'h2', 'bytes' => 222, 'ext' => 'mp4']);
        $v3 = Video::factory()->create(['hash' => 'h3', 'bytes' => 333, 'ext' => 'avi']);

        // Latest "assign" batch returned by BatchService
        $assignBatch = Batch::factory()->type('assign')->create(['started_at' => now()]);

        // Determine one "ready" status accepted by OfferNotifier query
        $readyStatus = collect(StatusEnum::getReadyStatus())->first();

        // Arrange: ready assignments for ch1 (2x) and ch2 (1x) – all tied to assignBatch
        Assignment::factory()->for($ch1, 'channel')->for($v1, 'video')->for($assignBatch, 'batch')
            ->create(['status' => $readyStatus]);
        Assignment::factory()->for($ch1, 'channel')->for($v2, 'video')->for($assignBatch, 'batch')
            ->create(['status' => $readyStatus]);
        Assignment::factory()->for($ch2, 'channel')->for($v3, 'video')->for($assignBatch, 'batch')
            ->create(['status' => $readyStatus]);

        // Fake mail delivery
        Mail::fake();

        // Mock BatchService -> returns our assignBatch
        $this->mock(BatchService::class, function ($mock) use ($assignBatch) {
            $mock->shouldReceive('getLatestAssignBatch')
                ->once()
                ->andReturn($assignBatch);
        });

        // Mock LinkService -> deterministic URLs, assert correct args incl. expireDate
        $ttlDays = 7;
        $expireDate = now()->addDays($ttlDays);

        $this->mock(LinkService::class, function ($mock) use ($assignBatch, $ch1, $ch2, $expireDate) {
            $mock->shouldReceive('getOfferUrl')
                ->andReturnUsing(function (Batch $batch, Channel $channel, Carbon $exp) use (
                    $assignBatch,
                    $expireDate
                ) {
                    if ($batch->isNot($assignBatch) || !$exp->equalTo($expireDate)) {
                        throw new \RuntimeException('Unexpected offer args');
                    }
                    return sprintf('https://example.test/offer/%d/%d', $batch->id, $channel->id);
                })
                ->twice();

            $mock->shouldReceive('getUnusedUrl')
                ->andReturnUsing(function (Batch $batch, Channel $channel, Carbon $exp) use (
                    $assignBatch,
                    $expireDate
                ) {
                    if ($batch->isNot($assignBatch) || !$exp->equalTo($expireDate)) {
                        throw new \RuntimeException('Unexpected unused args');
                    }
                    return sprintf('https://example.test/unused/%d/%d', $batch->id, $channel->id);
                })
                ->twice();
        });

        // Act
        $result = app(OfferNotifier::class)->notify($ttlDays);

        // Assert: returned stats
        $this->assertSame(['sent' => 2, 'batchId' => $assignBatch->getKey()], $result);

        // Assert: one mail per channel queued
        Mail::assertQueued(NewOfferMail::class, function (NewOfferMail $m) use ($ch1) {
            return $m->hasTo($ch1->email);
        });
        Mail::assertQueued(NewOfferMail::class, function (NewOfferMail $m) use ($ch2) {
            return $m->hasTo($ch2->email);
        });

        // Assert: a notify batch was created with aggregated stats
        $notify = Batch::query()->where('type', 'notify')->latest('id')->first();
        $this->assertNotNull($notify);
        $this->assertNotNull($notify->started_at);
        $this->assertNotNull($notify->finished_at);
        $this->assertEquals(['emails' => 2], $notify->stats);
    }

    public function testNotifyWithNoReadyAssignmentsReturnsZeroAndNoNotifyBatch(): void
    {
        Carbon::setTestNow('2025-08-12 10:00:00');

        // Create an "assign" batch with no ready assignments bound to it
        $assignBatch = Batch::factory()->type('assign')->create(['started_at' => now()]);

        Mail::fake();

        // BatchService still returns this assignBatch
        $this->mock(BatchService::class, function ($mock) use ($assignBatch) {
            $mock->shouldReceive('getLatestAssignBatch')->once()->andReturn($assignBatch);
        });

        // LinkService must not be called
        $this->mock(LinkService::class, function ($mock) {
            $mock->shouldReceive('getOfferUrl')->never();
            $mock->shouldReceive('getUnusedUrl')->never();
        });

        // Act
        $result = app(OfferNotifier::class)->notify(3);

        // Assert: zero sent, same batchId
        $this->assertSame(['sent' => 0, 'batchId' => $assignBatch->getKey()], $result);

        // No mails queued
        Mail::assertNothingQueued();

        // No notify batch created (method returns early)
        $this->assertDatabaseMissing('batches', ['type' => 'notify']);
    }
}
