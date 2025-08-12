<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Clip;
use App\Models\Video;
use App\Services\PreviewService;
use Illuminate\Support\Facades\Storage;
use Tests\DatabaseTestCase;
use Tests\Helper\FfmpegBinaryFaker;

class PreviewServiceTest extends DatabaseTestCase
{
    /** Small helper to compute the preview path like PreviewService::buildPath() does. */
    private function computePreviewPath(Video $video, int $start, int $end): string
    {
        $hash = md5($video->getKey().'_'.$start.'_'.$end);
        return "previews/{$hash}.mp4";
    }

    public function testGenerateReturnsCachedUrlWhenPreviewAlreadyExists(): void
    {
        // Use fake disks to avoid touching the real filesystem
        Storage::fake('local');
        Storage::fake('public');

        // Arrange: real source on local disk
        $srcRel = 'videos/a.mp4';
        Storage::disk('local')->put($srcRel, 'dummy');

        $video = Video::factory()->create([
            'disk' => 'local',
            'path' => $srcRel,
        ]);

        $start = 5;
        $end = 15;
        $previewPath = $this->computePreviewPath($video, $start, $end);

        // Pre-create cached preview so generate() will short-circuit
        Storage::disk('public')->put($previewPath, 'cached');

        // Act
        $url = app(PreviewService::class)->generate($video, $start, $end);

        // Assert: preview came from cache; URL should contain the path
        $this->assertNotNull($url);
        $this->assertTrue(Storage::disk('public')->exists($previewPath));
        $this->assertStringContainsString($previewPath, (string)$url);
    }

    public function testGenerateCreatesPreviewViaFakeFfmpegAndStoresOutputLocalSource(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Arrange: real source on local disk
        $srcRel = 'videos/b.mp4';
        Storage::disk('local')->put($srcRel, str_repeat('x', 1024));

        $video = Video::factory()->create([
            'disk' => 'local',
            'path' => $srcRel,
        ]);

        // Point ffmpeg bin to our fake success script
        $faker = new FfmpegBinaryFaker();
        config()->set('services.ffmpeg.bin', $faker->success());
        config()->set('services.ffmpeg.video_args', []); // no extra flags
        config()->set('services.ffmpeg.timeout', 5);

        $start = 0;
        $end = 3;
        $previewPath = $this->computePreviewPath($video, $start, $end);

        // Act
        $url = app(PreviewService::class)->generate($video, $start, $end);

        // Assert
        $this->assertNotNull($url);
        $this->assertTrue(Storage::disk('public')->exists($previewPath));
        $this->assertStringContainsString($previewPath, (string)$url);
    }

    public function testGenerateCreatesPreviewUsingReadStreamOnRemoteDisk(): void
    {
        // Fake a remote disk (e.g., s3) so resolveLocalSourcePath() goes through readStream()
        Storage::fake('s3');
        Storage::fake('public');

        // Put source on "remote" disk
        Storage::disk('s3')->put('remote/c.mp4', 'remote-content');

        $video = Video::factory()->create([
            'disk' => 's3',
            'path' => 'remote/c.mp4',
        ]);

        $faker = new FfmpegBinaryFaker();
        config()->set('services.ffmpeg.bin', $faker->success());

        $start = 1;
        $end = 4;
        $previewPath = $this->computePreviewPath($video, $start, $end);

        // Act
        $url = app(PreviewService::class)->generate($video, $start, $end);

        // Assert
        $this->assertNotNull($url);
        $this->assertTrue(Storage::disk('public')->exists($previewPath));
        $this->assertStringContainsString($previewPath, (string)$url);
    }

    public function testGenerateForClipWithMissingVideoOrInvalidRangeReturnsNull(): void
    {
        Storage::fake('local');

        $svc = app(PreviewService::class);

        // Case 1: clip without video relation
        $clipNoVideo = Clip::factory()->make(['video_id' => null, 'start_sec' => 0, 'end_sec' => 2]);
        $this->assertNull($svc->generateForClip($clipNoVideo));

        // Case 2: invalid range (end <= start)
        Storage::disk('local')->put('videos/d.mp4', 'x');
        $video = Video::factory()->create(['disk' => 'local', 'path' => 'videos/d.mp4']);

        $clipBadRange = Clip::factory()->forVideo($video)->make(['start_sec' => 10, 'end_sec' => 10]);
        $this->assertNull($svc->generateForClip($clipBadRange));
    }

    public function testUrlReturnsNullWhenPreviewMissingAndUrlWhenPresent(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Storage::disk('local')->put('videos/e.mp4', 'data');
        $video = Video::factory()->create(['disk' => 'local', 'path' => 'videos/e.mp4']);

        $start = 2;
        $end = 7;
        $previewPath = $this->computePreviewPath($video, $start, $end);

        $svc = app(PreviewService::class);

        // No preview yet
        $this->assertNull($svc->url($video, $start, $end));

        // After putting the preview, url() should return a link that includes the path
        Storage::disk('public')->put($previewPath, 'cached');
        $url = $svc->url($video, $start, $end);

        $this->assertNotNull($url);
        $this->assertStringContainsString($previewPath, (string)$url);
    }

    public function testGenerateReturnsNullOnInvalidRange(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('videos/f.mp4', 'data');
        $video = Video::factory()->create(['disk' => 'local', 'path' => 'videos/f.mp4']);

        $svc = app(PreviewService::class);

        // end <= start or negative start should be rejected
        $this->assertNull($svc->generate($video, 10, 10));
        $this->assertNull($svc->generate($video, 10, 9));
        $this->assertNull($svc->generate($video, -1, 5));
    }

    public function testGenerateReturnsNullWhenProcessExitsZeroButNoFileWasCreated(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Arrange: valid source
        Storage::disk('local')->put('videos/g.mp4', 'data');
        $video = Video::factory()->create(['disk' => 'local', 'path' => 'videos/g.mp4']);

        // Fake ffmpeg that exits 0 but does not create the destination file
        $faker = new FfmpegBinaryFaker();
        config()->set('services.ffmpeg.bin', $faker->zeroOutputZeroExit());

        // Act
        $url = app(PreviewService::class)->generate($video, 0, 2);

        // Assert: service should detect missing file and return null
        $this->assertNull($url);
    }
}
