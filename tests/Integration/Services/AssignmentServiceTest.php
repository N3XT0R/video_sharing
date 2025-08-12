<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enum\StatusEnum;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Download;
use App\Models\Video;
use App\Services\AssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\DatabaseTestCase;

class AssignmentServiceTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a fake route so URL::temporarySignedRoute() can generate a URL.
        Route::get('/assignments/{assignment}/download', fn() => 'ok')
            ->name('assignments.download');
    }

    public function testFetchPendingReturnsReadyAssignmentsForChannelOrderedById(): void
    {
        $batch = Batch::factory()->type('assign')->finished()->create();
        $channel = Channel::factory()->create();

        // Two videos for the same channel (unique constraint requires distinct videos per (channel, video))
        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();

        // Ready statuses should be returned
        $a1 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);
        $a2 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);

        // Different channel -> excluded
        Assignment::factory()->for($batch, 'batch')->for(Channel::factory(), 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);

        // Not ready status -> excluded
        Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        $items = app(AssignmentService::class)->fetchPending($batch, $channel);

        // Ordered by id ASC: a1 then a2
        $this->assertCount(2, $items);
        $this->assertSame([$a1->id, $a2->id], $items->pluck('id')->all());
        $this->assertTrue($items[0]->relationLoaded('video'));
        $this->assertTrue($items[0]->video->relationLoaded('clips'));
    }

    public function testFetchForZipFiltersByIdsAndReadyStatuses(): void
    {
        $batch = Batch::factory()->type('assign')->finished()->create();
        $channel = Channel::factory()->create();

        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();
        $v3 = Video::factory()->create();

        $a1 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);
        $a2 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);
        // Not ready -> should be filtered out even if id included
        $a3 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v3, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        // Include all three ids; only a1 and a2 should be returned
        $ids = collect([$a1->id, $a2->id, $a3->id]);
        $items = app(AssignmentService::class)->fetchForZip($batch, $channel, $ids);

        $this->assertEqualsCanonicalizing([$a1->id, $a2->id], $items->pluck('id')->all());
        $this->assertTrue($items[0]->relationLoaded('video'));
        $this->assertTrue($items[0]->video->relationLoaded('clips'));
    }

    public function testFetchPickedUpReturnsOnlyPickedUpForBatchAndChannel(): void
    {
        $batch = Batch::factory()->type('assign')->finished()->create();
        $channel = Channel::factory()->create();

        $vp = Video::factory()->create();
        $vq = Video::factory()->create();

        $picked = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($vp, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($vq, 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);

        Assignment::factory()->for($batch, 'batch')->for(Channel::factory(), 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        $items = app(AssignmentService::class)->fetchPickedUp($batch, $channel);

        $this->assertCount(1, $items);
        $this->assertTrue($items->first()->is($picked));
    }

    public function testMarkUnusedResetsPickedUpBackToQueuedAndClearsFields(): void
    {
        $batch = Batch::factory()->type('assign')->finished()->create();
        $channel = Channel::factory()->create();

        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();

        $picked1 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create([
                'status' => StatusEnum::PICKEDUP->value,
                'download_token' => 'tok',
                'expires_at' => now()->addDay(),
                'last_notified_at' => now()->subHour(),
            ]);

        // Not picked up -> should not be touched
        $notPicked = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video')
            ->create([
                'status' => StatusEnum::NOTIFIED->value,
                'download_token' => 'tok2',
                'expires_at' => now()->addDay(),
                'last_notified_at' => now()->subHour(),
            ]);

        $updated = app(AssignmentService::class)->markUnused($batch, $channel, [$picked1->id, $notPicked->id]);

        $this->assertTrue($updated);

        $this->assertSame(StatusEnum::QUEUED->value, $picked1->fresh()->status);
        $this->assertNull($picked1->fresh()->download_token);
        $this->assertNull($picked1->fresh()->expires_at);
        $this->assertNull($picked1->fresh()->last_notified_at);

        // untouched
        $this->assertSame(StatusEnum::NOTIFIED->value, $notPicked->fresh()->status);
        $this->assertSame('tok2', $notPicked->fresh()->download_token);
    }

    public function testMarkUnusedReturnsFalseWhenNothingMatches(): void
    {
        $batch = Batch::factory()->type('assign')->finished()->create();
        $channel = Channel::factory()->create();

        $a = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);

        $this->assertFalse(app(AssignmentService::class)->markUnused($batch, $channel, [$a->id]));
    }

    public function testMarkDownloadedSetsPickedUpAndCreatesDownloadRow(): void
    {
        $assignment = Assignment::factory()
            ->for(Batch::factory()->type('assign')->finished(), 'batch')
            ->for(Channel::factory(), 'channel')
            ->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);

        app(AssignmentService::class)->markDownloaded($assignment, '203.0.113.10', 'UA/1.0');

        $fresh = $assignment->fresh();
        $this->assertSame(StatusEnum::PICKEDUP->value, $fresh->status);

        $this->assertDatabaseHas('downloads', [
            'assignment_id' => $assignment->id,
            'ip' => '203.0.113.10',
            'user_agent' => 'UA/1.0',
        ]);
        $this->assertNull(Download::query()->where('assignment_id', $assignment->id)->value('bytes_sent'));
    }

    public function testPrepareDownloadSetsTokenStatusAndReturnsSignedUrlWithTParam(): void
    {
        // Freeze time so expiry calculations are deterministic
        \Illuminate\Support\Carbon::setTestNow('2025-08-12 09:00:00');

        // Create a queued assignment without expiry or token
        $assignment = \App\Models\Assignment::factory()
            ->for(\App\Models\Batch::factory()->type('assign')->finished(), 'batch')
            ->for(\App\Models\Channel::factory(), 'channel')
            ->for(\App\Models\Video::factory(), 'video')
            ->create([
                'status' => \App\Enum\StatusEnum::QUEUED->value,
                'expires_at' => null,
                'download_token' => null,
            ]);

        // Act: prepare for download with TTL = 24 hours
        $url = app(AssignmentService::class)->prepareDownload($assignment, 24);

        // Parse the signed URL into path + query components
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $qs);

        // The assignment id is embedded in the PATH, not in the query string
        $this->assertSame("/assignments/{$assignment->id}/download", $parts['path'] ?? '');

        // Required signed-route params should be present
        $this->assertArrayHasKey('signature', $qs);
        $this->assertArrayHasKey('expires', $qs);
        $this->assertArrayHasKey('t', $qs);

        // The stored download_token must equal sha256 of the plain token 't'
        $this->assertSame(hash('sha256', $qs['t']), $assignment->fresh()->download_token);

        // Status transition: QUEUED -> NOTIFIED; last_notified_at must be set
        $this->assertSame(\App\Enum\StatusEnum::NOTIFIED->value, $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->last_notified_at);

        // Expiry must be exactly now + 24 hours
        $this->assertTrue($assignment->fresh()->expires_at->equalTo(now()->addHours(24)));

        // verify the signature is valid for the generated URL
        $this->assertTrue(URL::hasValidSignature(
            Request::create($url)
        ));
    }


    public function testPrepareDownloadHonorsExistingSoonerExpiry(): void
    {
        Carbon::setTestNow('2025-08-12 09:00:00');

        // expires_at sooner than ttlHours -> should keep the earlier expiry
        $existingExpiry = now()->addHours(6);

        $assignment = Assignment::factory()
            ->for(Batch::factory()->type('assign')->finished(), 'batch')
            ->for(Channel::factory(), 'channel')
            ->for(Video::factory(), 'video')
            ->create([
                'status' => StatusEnum::NOTIFIED->value, // already notified; status should stay
                'expires_at' => $existingExpiry,
            ]);

        $url = app(AssignmentService::class)->prepareDownload($assignment, 24);

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $qs);
        $this->assertArrayHasKey('t', $qs);

        // Status remains NOTIFIED (was not QUEUED)
        $this->assertSame(StatusEnum::NOTIFIED->value, $assignment->fresh()->status);

        // Expires_at stays the earlier one
        $this->assertTrue($assignment->fresh()->expires_at->equalTo($existingExpiry));
        $this->assertSame(hash('sha256', $qs['t']), $assignment->fresh()->download_token);
    }
}
