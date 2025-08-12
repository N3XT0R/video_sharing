<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Batch;
use App\Models\Clip;
use App\Models\Video;
use App\Services\InfoImporter;
use App\Services\IngestScanner;
use App\Services\PreviewService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\DatabaseTestCase;

class IngestScannerTest extends DatabaseTestCase
{

    /** Build destination path like IngestScanner does (videos/aa/bb/hash.ext). */
    private function expectedDest(string $sha256, string $ext): string
    {
        $sub = substr($sha256, 0, 2).'/'.substr($sha256, 2, 2);
        return sprintf('videos/%s/%s.%s', $sub, $sha256, $ext);
    }

    /** Create an inbox folder under storage/app so makeStorageRelative() resolves correctly. */
    private function makeInbox(): string
    {
        $inbox = storage_path('app/_inbox_test_'.bin2hex(random_bytes(3)));
        if (!is_dir($inbox)) {
            mkdir($inbox, 0777, true);
        }
        return $inbox;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Use real local filesystem (Zip/paths rely on Storage::path())
        Config::set('filesystems.default', 'local');
    }

    protected function tearDown(): void
    {
        // Cleanup any test inboxes
        foreach (glob(storage_path('app/_inbox_test_*')) ?: [] as $dir) {
            @array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);
        }
        parent::tearDown();
    }

    public function testScanIngestsNewVideo_generatesPreview_importsCsv_andDeletesSourceAndCsv(): void
    {
        $inbox = $this->makeInbox();

        // 1) Prepare a small mp4 file inside inbox
        $filename = 'cam1.mp4';
        $absFile = $inbox.'/'.$filename;
        file_put_contents($absFile, str_repeat('A', 2048));
        $hash = hash_file('sha256', $absFile);
        $destRel = $this->expectedDest($hash, 'mp4');

        // 2) CSV describing a clip for that file (will first warn before video exists, then succeed)
        $csvPath = $inbox.'/clips.csv';
        file_put_contents($csvPath, implode("\n", [
            'filename;start;end;note;bundle;role;submitted_by',
            'cam1.mp4;00:00;00:10;intro;B;F;tester',
            '',
        ]));

        // 3) Stub PreviewService (no real ffmpeg). We don't assert calls, only stub returns.
        $preview = Mockery::mock(PreviewService::class)->shouldIgnoreMissing();
        $preview->allows('setOutput'); // no-op
        $preview->allows('generate')->andReturn('preview://gen');
        $preview->allows('generateForClip')->andReturn('preview://clip');

        // 4) Real InfoImporter so CSV -> Clip is actually created & csv gets deleted on second pass
        $scanner = new IngestScanner($preview, app(InfoImporter::class));

        // Act
        $stats = $scanner->scan($inbox, 'local');

        // Assert stats
        $this->assertSame(['new' => 1, 'dups' => 0, 'err' => 0], $stats);

        // Batch written with type ingest and disk meta
        $batch = Batch::query()->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame('ingest', $batch->type);
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->finished_at);
        $this->assertEquals([
            'new' => 1,
            'dups' => 0,
            'err' => 0,
            'disk' => 'local',
        ], $batch->stats);

        // Video created & moved to storage path; preview URL set from stub
        $video = Video::query()->where('hash', $hash)->first();
        $this->assertNotNull($video);
        $this->assertSame('local', $video->disk);
        $this->assertSame($destRel, $video->path);
        $this->assertSame('cam1.mp4', $video->original_name);
        $this->assertSame('preview://clip', $video->preview_url); // because clip exists

        // Destination file exists with same bytes; source file was deleted
        $this->assertTrue(Storage::exists($destRel));
        $this->assertFileDoesNotExist($absFile);

        // Clip actually created by importer
        $clip = Clip::query()->where('video_id', $video->id)->first();
        $this->assertNotNull($clip);
        $this->assertSame(0, $clip->start_sec);
        $this->assertSame(10, $clip->end_sec);
        $this->assertSame('intro', $clip->note);
        $this->assertSame('B', $clip->bundle_key);
        $this->assertSame('F', $clip->role);
        $this->assertSame('tester', $clip->submitted_by);

        // CSV should have been deleted on second import (warnings == 0)
        $this->assertFileDoesNotExist($csvPath);
    }

    public function testScanSkipsDuplicate_andDeletesDuplicateSource(): void
    {
        $inbox = $this->makeInbox();

        // First run: ingest one file
        $abs1 = $inbox.'/d1.mp4';
        file_put_contents($abs1, str_repeat('X', 100));
        $hash = hash_file('sha256', $abs1);
        $destRel = $this->expectedDest($hash, 'mp4');

        $preview = Mockery::mock(PreviewService::class)->shouldIgnoreMissing();
        $preview->allows('generate')->andReturn('p://1');
        $scanner = new IngestScanner($preview, app(InfoImporter::class));

        $stats1 = $scanner->scan($inbox, 'local');
        $this->assertSame(['new' => 1, 'dups' => 0, 'err' => 0], $stats1);
        $this->assertTrue(Storage::exists($destRel));

        // Second run: place a duplicate (same content, different file name)
        $abs2 = $inbox.'/duplicate_same_content.mp4';
        file_put_contents($abs2, str_repeat('X', 100)); // same content -> same hash

        $stats2 = $scanner->scan($inbox, 'local');
        $this->assertSame(['new' => 0, 'dups' => 1, 'err' => 0], $stats2);

        // Duplicate source should be deleted
        $this->assertFileDoesNotExist($abs2);

        // Only one video in DB remains
        $this->assertSame(1, Video::query()->where('hash', $hash)->count());
    }

    public function testScanCountsErrorWhenDestinationNotWritable_andKeepsSourceAndVideo(): void
    {
        $inbox = $this->makeInbox();

        // Prepare file
        $filename = 'broken.mp4';
        $abs = $inbox.'/'.$filename;
        file_put_contents($abs, 'content');
        $hash = hash_file('sha256', $abs);

        // Pre-create a DIRECTORY at the FINAL file path to force fopen() failure -> exception -> err++
        $destRel = $this->expectedDest($hash, 'mp4');
        $destAbs = Storage::path($destRel);
        @mkdir(dirname($destAbs), 0777, true);
        @mkdir($destAbs, 0777, true); // this path now collides with file creation

        $preview = Mockery::mock(PreviewService::class)->shouldIgnoreMissing();
        $preview->allows('generate')->andReturn('p://err');

        $scanner = new IngestScanner($preview, app(InfoImporter::class));

        $stats = $scanner->scan($inbox, 'local');

        // Expect one error (write failure); no new or dup
        $this->assertSame(['new' => 0, 'dups' => 0, 'err' => 1], $stats);

        // Source file remains (because failure happened before unlink)
        $this->assertFileExists($abs);

        // Video row was created before upload attempt; since exception aborted processFile(),
        // it wasn't deleted by processFile's "uploaded=false" branch.
        $this->assertTrue(Video::query()->where('hash', $hash)->exists());
    }
}
