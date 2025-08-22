<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Resources;

use App\Filament\Resources\DownloadResource\Pages\ListDownloads;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Download;
use App\Models\User;
use App\Models\Video;
use Livewire\Livewire;
use Tests\DatabaseTestCase;

final class DownloadResourceTest extends DatabaseTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function testListShowsDownloadData(): void
    {
        $channel = Channel::factory()->create(['name' => 'Main Channel']);
        $video = Video::factory()->create();
        $batch = Batch::factory()->type('assign')->create();
        $assignment = Assignment::factory()
            ->forChannel($channel)
            ->forVideo($video)
            ->withBatch($batch)
            ->create(['status' => 'queued']);

        Download::factory()
            ->forAssignment($assignment)
            ->create(['ip' => '203.0.113.1']);

        Livewire::test(ListDownloads::class)
            ->assertStatus(200)
            ->assertSee((string)$assignment->id)
            ->assertSee($assignment->status)
            ->assertSee('203.0.113.1')
            ->assertSee($channel->name)
            ->assertSee((string)$assignment->getAttribute('video')->getAttribute('original_name'));
    }
}
