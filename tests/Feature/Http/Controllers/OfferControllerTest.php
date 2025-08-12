<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use App\Services\AssignmentService;
use App\Services\LinkService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\DatabaseTestCase;

class OfferControllerTest extends DatabaseTestCase
{

    public function testShowRequiresValidSignature(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        // No signature -> should be forbidden
        $this->get(route('offer.show', [$batch, $channel]))
            ->assertStatus(403);
    }

    public function testShowRendersViewWithPendingAssignmentsAndTempUrls(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        // Create real assignments with relations so Eloquent collection behaves normally
        $v1 = Video::factory()->create(['original_name' => 'a.mp4']);
        $v2 = Video::factory()->create(['original_name' => 'b.mp4']);

        $a1 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')->create();
        $a2 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video')->create();

        // Mock AssignmentService: return our Eloquent collection & generate temp URLs
        $assignmentsMock = Mockery::mock(AssignmentService::class);
        $assignmentsMock->shouldReceive('fetchPending')
            ->once()
            ->withArgs(fn(Batch $b, Channel $c) => $b->is($batch) && $c->is($channel))
            ->andReturn(Assignment::query()->whereKey([$a1->getKey(), $a2->getKey()])->get());

        $assignmentsMock->shouldReceive('prepareDownload')
            ->andReturnUsing(fn(Assignment $a) => "https://example.test/dl/{$a->getKey()}");

        $this->app->instance(AssignmentService::class, $assignmentsMock);

        // Mock LinkService used to build "zip selected" POST URL
        $linkMock = Mockery::mock(LinkService::class);
        $linkMock->shouldReceive('getZipSelectedUrl')
            ->andReturn('https://example.test/zip-post');
        $this->app->instance(LinkService::class, $linkMock);

        // Signed URL is required by controller
        $url = URL::signedRoute('offer.show', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        $this->get($url)
            ->assertOk()
            ->assertViewIs('offer.show')
            ->assertViewHasAll(['batch', 'channel', 'items', 'zipPostUrl'])
            ->assertSee('https://example.test/dl/'.$a1->getKey())
            ->assertSee('https://example.test/dl/'.$a2->getKey())
            ->assertSee('https://example.test/zip-post');
    }

    public function testShowUnusedRendersPickedUpAssignments(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();
        $video = Video::factory()->create(['original_name' => 'c.mp4']);

        $picked = Assignment::factory()
            ->for($batch, 'batch')
            ->for($channel, 'channel')
            ->for($video, 'video')
            ->create();

        $assignmentsMock = Mockery::mock(AssignmentService::class);
        $assignmentsMock->shouldReceive('fetchPickedUp')
            ->once()
            ->withArgs(fn(Batch $b, Channel $c) => $b->is($batch) && $c->is($channel))
            ->andReturn(Assignment::query()->whereKey([$picked->getKey()])->get());
        $this->app->instance(AssignmentService::class, $assignmentsMock);

        $linkMock = Mockery::mock(LinkService::class);
        $linkMock->shouldReceive('getStoreUnusedUrl')->andReturn('https://example.test/unused-post');
        $this->app->instance(LinkService::class, $linkMock);

        $url = URL::signedRoute('offer.unused.show', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        $this->get($url)
            ->assertOk()
            ->assertViewIs('offer.unused')
            ->assertViewHasAll(['batch', 'channel', 'items', 'postUrl'])
            ->assertSee('https://example.test/unused-post');
    }

    public function testStoreUnusedValidatesSelectionRequired(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        $assignmentsMock = Mockery::mock(AssignmentService::class);
        // markUnused must not be called on empty selection
        $assignmentsMock->shouldReceive('markUnused')->never();
        $this->app->instance(AssignmentService::class, $assignmentsMock);

        $url = URL::signedRoute('offer.unused.store', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        Session::start(); // enable CSRF
        $this->from('/return-here')
            ->post($url, ['_token' => csrf_token(), 'assignment_ids' => []])
            ->assertRedirect('/return-here')
            ->assertSessionHasErrors(['nothing']);
    }

    public function testStoreUnusedMarksAndShowsSuccessFlash(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        $ids = [1, 2, 3];

        $assignmentsMock = Mockery::mock(AssignmentService::class);
        $assignmentsMock->shouldReceive('markUnused')
            ->once()
            ->withArgs(function (Batch $b, Channel $c, $payload) use ($batch, $channel, $ids) {
                // Payload comes in as array<int,int>
                return $b->is($batch) && $c->is($channel) && collect($payload)->sort()->values()->all() === $ids;
            })
            ->andReturn(true);
        $this->app->instance(AssignmentService::class, $assignmentsMock);

        $url = URL::signedRoute('offer.unused.store', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        Session::start();
        $this->from('/back')
            ->post($url, ['_token' => csrf_token(), 'assignment_ids' => $ids])
            ->assertRedirect('/back')
            ->assertSessionHas('success', 'Die ausgewählten Videos wurden wieder freigegeben.');
    }

    public function testStoreUnusedShowsErrorFlashOnFailure(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        $ids = [10, 11];

        $assignmentsMock = Mockery::mock(AssignmentService::class);
        $assignmentsMock->shouldReceive('markUnused')->andReturn(false);
        $this->app->instance(AssignmentService::class, $assignmentsMock);

        $url = URL::signedRoute('offer.unused.store', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        Session::start();
        $this->from('/back')
            ->post($url, ['_token' => csrf_token(), 'assignment_ids' => $ids])
            ->assertRedirect('/back')
            ->assertSessionHas('error', 'Fehler: Die ausgewählten Videos konnten nicht freigegeben werden.');
    }
}
