<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Clip;
use App\Models\Video;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\DatabaseTestCase;

/**
 * Unit tests for the App\Models\Video model.
 *
 * We validate:
 *  - mass assignment and the "meta" array cast
 *  - hasMany relationships: assignments(), clips()
 *  - getDisk() returns the configured filesystem and is writable
 */
final class VideoTest extends DatabaseTestCase
{
    public function testMassAssignmentAndMetaCast(): void
    {
        $video = Video::query()->create([
            'hash' => 'abc123',
            'ext' => 'mp4',
            'bytes' => 123456,
            'path' => 'videos/ab/cd/abc123.mp4',
            'meta' => ['duration' => 42, 'codec' => 'h264'],
            'original_name' => 'dashcam.mp4',
            'disk' => 'local',
            'preview_url' => null,
        ])->fresh();

        $this->assertSame('abc123', $video->hash);
        $this->assertSame('mp4', $video->ext);
        $this->assertSame(123456, $video->bytes);
        $this->assertSame('videos/ab/cd/abc123.mp4', $video->path);
        $this->assertIsArray($video->meta);
        $this->assertSame(['duration' => 42, 'codec' => 'h264'], $video->meta);
        $this->assertSame('dashcam.mp4', $video->original_name);
        $this->assertSame('local', $video->disk);
        $this->assertNull($video->preview_url);
    }

    public function testAssignmentsRelationReturnsRelatedModels(): void
    {
        $video = Video::factory()->create();
        $batch = Batch::factory()->type('assign')->finished()->create();
        $ch1 = Channel::factory()->create();
        $ch2 = Channel::factory()->create();

        $a1 = Assignment::factory()
            ->for($video, 'video')->for($ch1, 'channel')->for($batch, 'batch')
            ->create();
        $a2 = Assignment::factory()
            ->for($video, 'video')->for($ch2, 'channel')->for($batch, 'batch')
            ->create();

        $ids = $video->assignments()->pluck('id')->all();

        $this->assertContains($a1->getKey(), $ids);
        $this->assertContains($a2->getKey(), $ids);
        $this->assertCount(2, $video->assignments()->get());
    }

    public function testClipsRelationReturnsRelatedModels(): void
    {
        $video = Video::factory()->create();

        $c1 = Clip::factory()->create([
            'video_id' => $video->getKey(),
            'start_sec' => 0,
            'end_sec' => 10,
        ]);
        $c2 = Clip::factory()->create([
            'video_id' => $video->getKey(),
            'start_sec' => 30,
            'end_sec' => 50,
        ]);

        $clipIds = $video->clips()->pluck('id')->all();

        $this->assertContains($c1->getKey(), $clipIds);
        $this->assertContains($c2->getKey(), $clipIds);
        $this->assertCount(2, $video->clips()->get());
    }

    public function testGetDiskReturnsConfiguredFilesystemAndIsWritable(): void
    {
        // Fake the "public" disk to avoid touching real storage
        Storage::fake('public');

        $video = Video::factory()->create([
            'disk' => 'public',
            'path' => 'videos/'.Str::random(8).'/'.Str::random(8).'.mp4',
        ]);

        $disk = $video->getDisk();
        $this->assertInstanceOf(Filesystem::class, $disk);

        $testPath = 'probe/'.Str::random(12).'.txt';
        $this->assertTrue($disk->put($testPath, 'ok'));

        Storage::disk('public')->assertExists($testPath);
    }
}
