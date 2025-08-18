<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Resources;

use App\Filament\Resources\BatchResource\Pages\ListBatches;
use App\Filament\Resources\BatchResource\Pages\ViewBatch;
use App\Filament\Resources\BatchResource\RelationManagers\ChannelsRelationManager;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Clip;
use App\Models\User;
use App\Models\Video;
use App\Services\LinkService;
use Livewire\Livewire;
use Tests\DatabaseTestCase;

final class BatchResourceTest extends DatabaseTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function testListBatchesShowsTabsAndAssignmentCount(): void
    {
        $ingest = Batch::factory()->type('ingest')->create();
        $notify = Batch::factory()->type('notify')->create();
        $assign = Batch::factory()->type('assign')->create();

        Assignment::factory()->count(5)->withBatch($assign)->create();

        Livewire::test(ListBatches::class, ['activeTab' => 'assign'])
            ->loadTable()
            ->assertCanSeeTableRecords([$assign])
            ->assertCanNotSeeTableRecords([$ingest, $notify])
            ->assertTableColumnExists('assignments_count');

        Livewire::test(ListBatches::class, ['activeTab' => 'ingest'])
            ->assertCanSeeTableRecords([$ingest])
            ->assertCanNotSeeTableRecords([$assign, $notify]);

        Livewire::test(ListBatches::class, ['activeTab' => 'notify'])
            ->assertCanSeeTableRecords([$notify])
            ->assertCanNotSeeTableRecords([$assign, $ingest]);
    }

    public function testViewBatchShowsRelations(): void
    {
        $batch = Batch::factory()->type('assign')->create();
        $video = Video::factory()->create();
        $channel = Channel::factory()->create();
        Clip::factory()->forVideo($video)->create();
        $assignment = Assignment::factory()
            ->forVideo($video)
            ->forChannel($channel)
            ->withBatch($batch)
            ->create();

        $link = app(LinkService::class)->getOfferUrl($batch, $channel, $assignment->expires_at);

        Livewire::test(ViewBatch::class, ['record' => $batch->getKey()])
            ->assertStatus(200)
            ->assertSeeText('Assignments')
            ->assertSeeText('Clips')
            ->assertSeeText('Channels');

        Livewire::test(ChannelsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewBatch::class,
        ])
            ->assertCanSeeTableRecords([$channel])
            ->assertTableActionExists('offer_link')
            ->assertTableActionHasUrl('offer_link', $link, record: $channel);
    }
}
