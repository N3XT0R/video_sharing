<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Download;
use App\Models\Video;
use App\Enum\BatchTypeEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Tests\DatabaseTestCase;

/**
 * Feature tests for the "video:cleanup" console command.
 */
final class VideoCleanupTest extends DatabaseTestCase
{
    public function test_deletes_only_expired_picked_up_videos_with_download(): void
    {
        Carbon::setTestNow('2025-08-12 12:00:00');

        $batch = Batch::factory()->state(['type' => 'assign'])
            ->create(['started_at' => now()->subHour(), 'finished_at' => now()->subMinute()]);

        // Video to delete (picked up, downloaded, expired >1 week)
        $videoDel = Video::factory()->create();
        $assignDel = Assignment::factory()
            ->for($batch, 'batch')
            ->for(Channel::factory()->create(), 'channel')
            ->for($videoDel, 'video')
            ->create([
                'status' => 'picked_up',
                'expires_at' => now()->subWeek()->subDay(),
            ]);
        Download::factory()->forAssignment($assignDel)->create();

        // Not expired long enough
        $videoFresh = Video::factory()->create();
        $assignFresh = Assignment::factory()
            ->for($batch, 'batch')
            ->for(Channel::factory()->create(), 'channel')
            ->for($videoFresh, 'video')
            ->create([
                'status' => 'picked_up',
                'expires_at' => now()->subDays(3),
            ]);
        Download::factory()->forAssignment($assignFresh)->create();

        // Missing download
        $videoNoDl = Video::factory()->create();
        Assignment::factory()
            ->for($batch, 'batch')
            ->for(Channel::factory()->create(), 'channel')
            ->for($videoNoDl, 'video')
            ->create([
                'status' => 'picked_up',
                'expires_at' => now()->subWeeks(2),
            ]);

        // Wrong status
        $videoWrongStatus = Video::factory()->create();
        $assignWrongStatus = Assignment::factory()
            ->for($batch, 'batch')
            ->for(Channel::factory()->create(), 'channel')
            ->for($videoWrongStatus, 'video')
            ->create([
                'status' => 'queued',
                'expires_at' => now()->subWeeks(2),
            ]);
        Download::factory()->forAssignment($assignWrongStatus)->create();

        $this->artisan('video:cleanup')
            ->expectsOutput('Removed: 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('videos', ['id' => $videoDel->getKey()]);
        $this->assertDatabaseHas('videos', ['id' => $videoFresh->getKey()]);
        $this->assertDatabaseHas('videos', ['id' => $videoNoDl->getKey()]);
        $this->assertDatabaseHas('videos', ['id' => $videoWrongStatus->getKey()]);

        $batchRemove = Batch::query()->where('type', BatchTypeEnum::REMOVE->value)->latest('id')->first();
        $this->assertNotNull($batchRemove);
        $this->assertSame(1, $batchRemove->stats['removed']);
        $this->assertEquals([$videoDel->original_name], $batchRemove->stats['original_names']);
    }

    public function test_skips_video_if_latest_download_assignment_not_picked_up(): void
    {
        Carbon::setTestNow('2025-08-12 12:00:00');

        $batch = Batch::factory()->state(['type' => 'assign'])
            ->create(['started_at' => now()->subHour(), 'finished_at' => now()->subMinute()]);

        $video = Video::factory()->create();

        $assignOld = Assignment::factory()
            ->for($batch, 'batch')
            ->for(Channel::factory()->create(), 'channel')
            ->for($video, 'video')
            ->create([
                'status' => 'picked_up',
                'expires_at' => now()->subWeeks(2),
            ]);
        Download::factory()->forAssignment($assignOld)->at(now()->subWeeks(2))->create();

        $assignNew = Assignment::factory()
            ->for($batch, 'batch')
            ->for(Channel::factory()->create(), 'channel')
            ->for($video, 'video')
            ->create([
                'status' => 'rejected',
                'expires_at' => now()->subWeek()->subDay(),
            ]);
        Download::factory()->forAssignment($assignNew)->at(now()->subWeek())->create();

        $this->artisan('video:cleanup')
            ->expectsOutput('Removed: 0')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('videos', ['id' => $video->getKey()]);

        $batchRemove = Batch::query()->where('type', BatchTypeEnum::REMOVE->value)->latest('id')->first();
        $this->assertNotNull($batchRemove);
        $this->assertSame(0, $batchRemove->stats['removed']);
    }
}
