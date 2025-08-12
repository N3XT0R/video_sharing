<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enum\StatusEnum;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Tests\DatabaseTestCase;

final class OfferControllerTest extends DatabaseTestCase
{
    /** Rejects requests without a valid signature. */
    public function testShowRequiresValidSignature(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();

        $this->get(route('offer.show', [$batch, $channel]))
            ->assertStatus(403);
    }

    /**
     * Ensures only "ready" items are listed and temp URLs + zip post URL are present.
     */
    public function testShowRendersOnlyReadyAssignmentsAndInjectsTempUrlsAndZipPostUrl(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();

        $v1 = Video::factory()->create(['original_name' => 'a.mp4']);
        $v2 = Video::factory()->create(['original_name' => 'b.mp4']);

        $aQueued = Assignment::factory()
            ->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);

        $aNotified = Assignment::factory()
            ->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);

        // Irrelevant: different batch/channel or not "ready"
        Assignment::factory()->for(Batch::factory()->state(['type' => 'assign']), 'batch')->for($channel,
            'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);
        Assignment::factory()->for($batch, 'batch')->for(Channel::factory(), 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);
        Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        $url = URL::signedRoute('offer.show', [
            'batch' => $batch->getKey(),
            'channel' => $channel->getKey(),
        ]);

        $res = $this->get($url)
            ->assertOk()
            ->assertViewIs('offer.show')
            ->assertViewHas('batch')
            ->assertViewHas('channel')
            ->assertViewHas('zipPostUrl')
            ->assertViewHas('items', function ($items) use ($aQueued, $aNotified) {
                // Expect exactly the two ready assignments
                $ids = collect($items)->map->getKey()->sort()->values()->all();
                $expected = collect([$aQueued->getKey(), $aNotified->getKey()])->sort()->values()->all();
                if ($ids !== $expected) {
                    return false;
                }
                // Each item must have a temp_url pointing to the signed download route
                foreach ($items as $it) {
                    $temp = $it->temp_url ?? null;
                    if (!is_string($temp) || $temp === '') {
                        return false;
                    }
                    $expectedBase = route('assignments.download', $it->getKey());
                    if (strpos($temp, $expectedBase) === false) {
                        return false;
                    }
                    $q = [];
                    parse_str(parse_url($temp, PHP_URL_QUERY) ?: '', $q);
                    if (!isset($q['signature'], $q['expires'], $q['t'])) {
                        return false;
                    }
                }
                return true;
            });

        $zipPostUrl = $res->viewData('zipPostUrl');
        $this->assertIsString($zipPostUrl);
        $this->assertStringContainsString('/zips/', $zipPostUrl);
        $this->assertStringContainsString((string)$batch->getKey(), $zipPostUrl);
        $this->assertStringContainsString((string)$channel->getKey(), $zipPostUrl);
    }

    /** Lists only PICKEDUP assignments and provides a signed post URL. */
    public function testShowUnusedRendersPickedUpAssignmentsAndProvidesPostUrl(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();

        $v1 = Video::factory()->create(['original_name' => 'x.mp4']);
        $v2 = Video::factory()->create(['original_name' => 'y.mp4']);

        $picked1 = Assignment::factory()
            ->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        $picked2 = Assignment::factory()
            ->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        // Irrelevant: wrong status/batch
        Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);
        Assignment::factory()->for(Batch::factory()->state(['type' => 'assign']), 'batch')->for($channel,
            'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        $url = URL::signedRoute('offer.unused.show', [
            'batch' => $batch->getKey(),
            'channel' => $channel->getKey(),
        ]);

        $res = $this->get($url)
            ->assertOk()
            ->assertViewIs('offer.unused')
            ->assertViewHas('postUrl')
            ->assertViewHas('items', function ($items) use ($picked1, $picked2) {
                $ids = collect($items)->map->getKey()->sort()->values()->all();
                $expected = collect([$picked1->getKey(), $picked2->getKey()])->sort()->values()->all();
                return $ids === $expected;
            });

        $postUrl = $res->viewData('postUrl');
        $this->assertIsString($postUrl);
        $this->assertStringContainsString('/offer/', $postUrl);
        $this->assertStringContainsString('/unused', $postUrl);
    }

    /**
     * Triggers your custom "nothing" error: validation passes (array|min:1),
     * then filtering removes non-digit values -> empty -> custom error.
     */
    public function testStoreUnusedRejectsEmptySelection(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();

        $url = URL::signedRoute('offer.unused.store', [
            'batch' => $batch->getKey(),
            'channel' => $channel->getKey(),
        ]);

        Session::start();
        // Use a non-numeric entry so the validator passes but your filter drops it.
        $this->from('/back')
            ->post($url, ['_token' => csrf_token(), 'assignment_ids' => ['x']])
            ->assertRedirect('/back')
            ->assertSessionHasErrors(['nothing']);
    }

    /**
     * Valid PICKEDUP IDs get marked QUEUED; tokens/expiry cleared; success flash.
     */
    public function testStoreUnusedMarksPickedUpAsQueuedAndFlashesSuccess(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();

        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();

        $a1 = Assignment::factory()
            ->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        $a2 = Assignment::factory()
            ->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        $url = URL::signedRoute('offer.unused.store', [
            'batch' => $batch->getKey(),
            'channel' => $channel->getKey(),
        ]);

        Session::start();
        $this->from('/back')
            ->post($url, [
                '_token' => csrf_token(),
                'assignment_ids' => [$a1->getKey(), $a2->getKey()],
            ])
            ->assertRedirect('/back')
            ->assertSessionHas('success', 'Die ausgewählten Videos wurden wieder freigegeben.');

        $this->assertDatabaseHas('assignments', ['id' => $a1->getKey(), 'status' => StatusEnum::QUEUED->value]);
        $this->assertDatabaseHas('assignments', ['id' => $a2->getKey(), 'status' => StatusEnum::QUEUED->value]);
        $this->assertDatabaseHas('assignments', ['id' => $a1->getKey(), 'download_token' => null]);
        $this->assertDatabaseHas('assignments', ['id' => $a2->getKey(), 'download_token' => null]);
        $this->assertDatabaseHas('assignments', ['id' => $a1->getKey(), 'expires_at' => null]);
        $this->assertDatabaseHas('assignments', ['id' => $a2->getKey(), 'expires_at' => null]);
    }

    /** If nothing can be updated (wrong status), the controller flashes an error. */
    public function testStoreUnusedFlashesErrorWhenNothingUpdated(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();

        $v1 = Video::factory()->create();
        $a1 = Assignment::factory()
            ->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);

        $url = URL::signedRoute('offer.unused.store', [
            'batch' => $batch->getKey(),
            'channel' => $channel->getKey(),
        ]);

        Session::start();
        $this->from('/back')
            ->post($url, ['_token' => csrf_token(), 'assignment_ids' => [$a1->getKey()]])
            ->assertRedirect('/back')
            ->assertSessionHas('error', 'Fehler: Die ausgewählten Videos konnten nicht freigegeben werden.');

        $this->assertDatabaseHas('assignments', [
            'id' => $a1->getKey(),
            'status' => StatusEnum::QUEUED->value,
        ]);
    }
}
