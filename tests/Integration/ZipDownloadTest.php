<?php

namespace Tests\Integration;

use App\Models\{Assignment, Batch, Channel, Video};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ZipDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_succeeds_when_file_exists(): void
    {
        Storage::fake();

        $id = 'job1';
        $path = "zips/{$id}.zip";
        Storage::put($path, 'dummy');
        $absolute = Storage::path($path);
        Cache::put("zipjob:{$id}:file", $absolute, 600);
        Cache::put("zipjob:{$id}:name", 'download.zip', 600);

        $response = $this->get("/zips/{$id}/download");

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_download_marks_assignments_as_downloaded(): void
    {
        Storage::fake();

        $batch = Batch::create(['type' => 'assign']);
        $channel = Channel::create(['name' => 'C1', 'email' => 'c1@example.com']);
        $video = Video::create(['hash' => 'h1', 'path' => 'p1']);
        $assignment = Assignment::create([
            'video_id' => $video->id,
            'channel_id' => $channel->id,
            'batch_id' => $batch->id,
            'status' => 'queued',
        ]);

        $id = 'job2';
        $path = "zips/{$id}.zip";
        Storage::put($path, 'dummy');
        $absolute = Storage::path($path);
        Cache::put("zipjob:{$id}:file", $absolute, 600);
        Cache::put("zipjob:{$id}:name", 'download.zip', 600);
        Cache::put("zipjob:{$id}:assignments", [$assignment->id], 600);

        $this->get("/zips/{$id}/download")->assertOk();

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'status' => 'picked_up',
        ]);

        $this->assertDatabaseHas('downloads', [
            'assignment_id' => $assignment->id,
        ]);
    }
}
