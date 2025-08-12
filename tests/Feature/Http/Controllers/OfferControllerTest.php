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

final class OfferControllerFeatureTest extends DatabaseTestCase
{
    public function testShowRequiresValidSignature(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        // Ohne Signatur -> 403
        $this->get(route('offer.show', [$batch, $channel]))->assertStatus(403);
    }

    public function testShowRendersOnlyReadyAssignmentsAndInjectsTempUrlsAndZipPostUrl(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        $v1 = Video::factory()->create(['original_name' => 'a.mp4']);
        $v2 = Video::factory()->create(['original_name' => 'b.mp4']);
        $vOther = Video::factory()->create(['original_name' => 'c.mp4']);

        // Ready-Assignments (queued/notified) für batch+channel
        $aQueued = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);
        $aNotified = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video')
            ->create(['status' => StatusEnum::NOTIFIED->value]);

        // Nicht passend: anderer Channel/Batch oder falscher Status
        Assignment::factory()->for(Batch::factory(), 'batch')->for($channel, 'channel')->for($vOther, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);
        Assignment::factory()->for($batch, 'batch')->for(Channel::factory(), 'channel')->for($vOther, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);
        Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($vOther, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]); // nicht ready

        $url = URL::signedRoute('offer.show', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        $res = $this->get($url)
            ->assertOk()
            ->assertViewIs('offer.show')
            ->assertViewHas('batch')
            ->assertViewHas('channel')
            ->assertViewHas('zipPostUrl')
            ->assertViewHas('items', function ($items) use ($aQueued, $aNotified) {
                // Es sollen genau die beiden ready-Assignments drin sein
                $ids = collect($items)->pluck('id')->sort()->values()->all();
                $expected = collect([$aQueued->id, $aNotified->id])->sort()->values()->all();
                if ($ids !== $expected) {
                    return false;
                }
                // Jedes Item muss eine temp_url (signierter Download-Link) tragen
                foreach ($items as $it) {
                    if (!is_string($it->temp_url ?? null)) {
                        return false;
                    }
                    if (strpos($it->temp_url, route('assignments.download', $it->id)) === false) {
                        return false;
                    }
                    // Signatur/Expires sollten als Query-Parameter vorhanden sein
                    $q = [];
                    parse_str(parse_url($it->temp_url, PHP_URL_QUERY) ?? '', $q);
                    if (!isset($q['signature'], $q['expires'], $q['t'])) {
                        return false;
                    }
                }
                return true;
            });

        // zipPostUrl sollte auf die Zips-Route zeigen (echter LinkService erzeugt signierte URL)
        $zipPostUrl = $res->viewData('zipPostUrl');
        $this->assertIsString($zipPostUrl);
        $this->assertStringContainsString('/zips/', $zipPostUrl);
        $this->assertStringContainsString((string)$batch->getKey(), $zipPostUrl);
        $this->assertStringContainsString((string)$channel->getKey(), $zipPostUrl);
    }

    public function testShowUnusedRendersPickedUpAssignmentsAndProvidesPostUrl(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();
        $v1 = Video::factory()->create(['original_name' => 'x.mp4']);
        $v2 = Video::factory()->create(['original_name' => 'y.mp4']);

        $picked1 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);
        $picked2 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video'
        )->create(['status' => StatusEnum::PICKEDUP->value]);

        // Nicht passend
        Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);
        Assignment::factory()->for(Batch::factory(), 'batch')->for($channel, 'channel')->for(Video::factory(), 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        $url = URL::signedRoute('offer.unused.show', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        $res = $this->get($url)
            ->assertOk()
            ->assertViewIs('offer.unused')
            ->assertViewHas('postUrl')
            ->assertViewHas('items', function ($items) use ($picked1, $picked2) {
                $ids = collect($items)->pluck('id')->sort()->values()->all();
                $expected = collect([$picked1->id, $picked2->id])->sort()->values()->all();
                return $ids === $expected;
            });

        $postUrl = $res->viewData('postUrl');
        $this->assertIsString($postUrl);
        $this->assertStringContainsString('/offer/', $postUrl);
        $this->assertStringContainsString('/unused', $postUrl);
    }

    public function testStoreUnusedRejectsEmptySelection(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        $url = URL::signedRoute('offer.unused.store', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        Session::start();
        $this->from('/back')
            ->post($url, ['_token' => csrf_token(), 'assignment_ids' => []])
            ->assertRedirect('/back')
            ->assertSessionHasErrors(['nothing']);
    }

    public function testStoreUnusedMarksPickedUpAsQueuedAndFlashesSuccess(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        $v1 = Video::factory()->create();
        $v2 = Video::factory()->create();

        $a1 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);
        $a2 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v2, 'video')
            ->create(['status' => StatusEnum::PICKEDUP->value]);

        $url = URL::signedRoute('offer.unused.store', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        Session::start();
        $this->from('/back')
            ->post($url, ['_token' => csrf_token(), 'assignment_ids' => [$a1->id, $a2->id]])
            ->assertRedirect('/back')
            ->assertSessionHas('success', 'Die ausgewählten Videos wurden wieder freigegeben.');

        // Echtes AssignmentService hat DB-Update gemacht:
        $this->assertDatabaseHas('assignments', ['id' => $a1->id, 'status' => StatusEnum::QUEUED->value]);
        $this->assertDatabaseHas('assignments', ['id' => $a2->id, 'status' => StatusEnum::QUEUED->value]);
        $this->assertDatabaseHas('assignments', ['id' => $a1->id, 'download_token' => null]);
        $this->assertDatabaseHas('assignments', ['id' => $a2->id, 'download_token' => null]);
    }

    public function testStoreUnusedFlashesErrorWhenNothingUpdated(): void
    {
        $batch = Batch::factory()->create();
        $channel = Channel::factory()->create();

        // IDs sind zwar gültig, aber Status ist nicht PICKEDUP → markUnused findet nichts → error
        $v1 = Video::factory()->create();
        $a1 = Assignment::factory()->for($batch, 'batch')->for($channel, 'channel')->for($v1, 'video')
            ->create(['status' => StatusEnum::QUEUED->value]);

        $url = URL::signedRoute('offer.unused.store', ['batch' => $batch->getKey(), 'channel' => $channel->getKey()]);

        Session::start();
        $this->from('/back')
            ->post($url, ['_token' => csrf_token(), 'assignment_ids' => [$a1->id]])
            ->assertRedirect('/back')
            ->assertSessionHas('error', 'Fehler: Die ausgewählten Videos konnten nicht freigegeben werden.');

        // Status bleibt unverändert
        $this->assertDatabaseHas('assignments', ['id' => $a1->id, 'status' => StatusEnum::QUEUED->value]);
    }
}
