<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Resources;

use App\Filament\Resources\VideoResource\Pages\ListVideos;
use App\Filament\Resources\VideoResource\Pages\ViewVideo;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\DatabaseTestCase;

/**
 * Integration tests for the Filament VideoResource.
 *
 * We verify:
 *  - ListVideos renders and shows records
 *  - Filtering by "disk", "ext", and created_at range works
 *  - ViewVideo renders a single record
 *  - The "preview" link action exists and the preview URL appears in the output
 */
final class VideoResourceTest extends DatabaseTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate — User::canAccessPanel() returns true in your app
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function testListShowsVideosAndPreviewActionUrl(): void
    {
        $v1 = Video::factory()->create([
            'original_name' => 'dashcam_1.mp4',
            'ext' => 'mp4',
            'bytes' => 111,
            'disk' => 'dropbox',
            'preview_url' => 'https://cdn.example.test/previews/v1.mp4',
            'created_at' => Carbon::parse('2025-08-10 10:00:00'),
        ]);

        $v2 = Video::factory()->create([
            'original_name' => 'dashcam_2.mov',
            'ext' => 'mov',
            'bytes' => 222,
            'disk' => 'local',
            'preview_url' => null,
            'created_at' => Carbon::parse('2025-08-11 12:00:00'),
        ]);

        // Add some relations so counts have data (table uses ->counts())
        $batch = Batch::factory()->type('assign')->finished()->create();
        $channel = Channel::factory()->create();

        Assignment::factory()->for($v1, 'video')->for($batch, 'batch')->for($channel, 'channel')->create();
        // no relations for $v2 to keep counts different

        Livewire::test(ListVideos::class)
            ->assertStatus(200)
            // Records are visible in the table
            ->assertCanSeeTableRecords([$v1, $v2])
            // Preview action exists
            ->assertTableActionExists('preview')
            // And the preview URL appears (link action is rendered as <a href="...">)
            ->assertSee('https://cdn.example.test/previews/v1.mp4');
    }

    public function testFilterByDiskShowsOnlyMatchingRecords(): void
    {
        $drop = Video::factory()->create(['disk' => 'dropbox', 'ext' => 'mp4', 'original_name' => 'drop.mp4']);
        $loc = Video::factory()->create(['disk' => 'local', 'ext' => 'mov', 'original_name' => 'local.mov']);

        Livewire::test(ListVideos::class)
            ->assertStatus(200)
            // Apply the "disk" SelectFilter
            ->filterTable('disk', 'dropbox')
            ->assertCanSeeTableRecords([$drop])
            ->assertCanNotSeeTableRecords([$loc]);
    }

    public function testFilterByExtShowsOnlyMatchingRecords(): void
    {
        $mp4 = Video::factory()->create(['ext' => 'mp4', 'disk' => 'dropbox', 'original_name' => 'one.mp4']);
        $mov = Video::factory()->create(['ext' => 'mov', 'disk' => 'local', 'original_name' => 'two.mov']);

        Livewire::test(ListVideos::class)
            ->assertStatus(200)
            // Apply the "ext" SelectFilter
            ->filterTable('ext', 'mov')
            ->assertCanSeeTableRecords([$mov])
            ->assertCanNotSeeTableRecords([$mp4]);
    }

    public function testCreatedAtRangeFilterLimitsRecords(): void
    {
        $old = Video::factory()->create([
            'original_name' => 'old.mp4',
            'ext' => 'mp4',
            'disk' => 'local',
            'created_at' => Carbon::parse('2024-01-01 00:00:00'),
        ]);

        $new = Video::factory()->create([
            'original_name' => 'new.mp4',
            'ext' => 'mp4',
            'disk' => 'local',
            'created_at' => Carbon::parse('2025-08-13 09:00:00'),
        ]);

        Livewire::test(ListVideos::class)
            ->assertStatus(200)
            // The Date filter takes an array with 'from' and/or 'until'
            ->filterTable('created_at', [
                'from' => '2025-08-10',
                'until' => '2025-08-31',
            ])
            ->assertCanSeeTableRecords([$new])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function testViewPageRendersRecord(): void
    {
        // Arrange: create a video record
        $video = Video::factory()->create([
            'original_name' => 'sample.mp4',
            'ext' => 'mp4',
            'disk' => 'local',
        ]);

        // Act & Assert: the view page renders and shows stable UI labels
        // (the view does not necessarily output the original_name)
        Livewire::test(ViewVideo::class, [
            'record' => $video->getKey(),
        ])
            ->assertStatus(200)
            // Header on the page
            ->assertSeeText('Video ansehen')
            // Relation tabs that are always present for this resource
            ->assertSeeText('Assignments')
            ->assertSeeText('Clips');
    }
}
