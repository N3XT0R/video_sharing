<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Batch;
use App\Models\Video;
use Illuminate\Console\Command;
use Tests\DatabaseTestCase;
use Tests\Helper\FfmpegBinaryFaker;

/**
 * Feature tests for the "ingest:scan" console command with the real IngestScanner.
 *
 * - No mocking/faking of services; we only point ffmpeg to a tiny shell script that "succeeds".
 * - We create real files under storage_path('app/...') so the default 'local' disk resolves correctly.
 * - We assert DB side-effects (ingest batch, video rows) and on-disk outcomes (files moved).
 */
final class IngestScanTest extends DatabaseTestCase
{
    /** Sets up a fake ffmpeg binary and returns its path. */
    private function useFakeFfmpegSuccess(): string
    {
        $bin = (new FfmpegBinaryFaker())->success('FAKEMP4');
        config()->set('services.ffmpeg.bin', $bin);
        config()->set('services.ffmpeg.timeout', 30);
        config()->set('services.ffmpeg.video_args', []); // keep args simple
        return $bin;
    }

    /** Creates a small MP4-like file under storage/app/$subdir and returns [absPath, fileName]. */
    private function makeInboxFile(string $subdir, string $fileName, string $contents = 'abc'): array
    {
        $dir = storage_path('app/'.$subdir);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $abs = $dir.'/'.$fileName;
        file_put_contents($abs, $contents); // tiny content is enough
        return [$abs, $fileName];
    }

    /** Happy path: one new video is ingested to the local disk; batch stats and file move are correct. */
    public function testScanMovesVideoAndCreatesBatchStats(): void
    {
        // Use fake ffmpeg so previews can be generated without a real encoder
        $this->useFakeFfmpegSuccess();

        // Prepare an inbox under storage/app so Storage::disk('local')->path() lines up
        $inboxRel = 'inbox_cmd_'.bin2hex(random_bytes(4));
        [, $fn] = $this->makeInboxFile($inboxRel, 'clip.mp4', 'abc123');

        $inboxAbs = rtrim(storage_path('app/'.$inboxRel), '/');

        // Sanity: DB empty
        $this->assertSame(0, Video::query()->count());
        $this->assertNull(Batch::query()->where('type', 'ingest')->latest('id')->first());

        // Act
        $this->artisan("ingest:scan --inbox={$inboxAbs} --disk=local")
            ->expectsOutput('started...')
            ->expectsOutputToContain('Ingest done.')
            ->assertExitCode(Command::SUCCESS);

        // Assert: a new ingest batch with stats including disk=local
        $batch = Batch::query()->where('type', 'ingest')->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->finished_at);
        $this->assertIsArray($batch->stats);
        $this->assertArrayHasKey('new', $batch->stats);
        $this->assertArrayHasKey('dups', $batch->stats);
        $this->assertArrayHasKey('err', $batch->stats);
        $this->assertSame('local', $batch->stats['disk'] ?? null);
        $this->assertSame(1, $batch->stats['new']);
        $this->assertSame(0, $batch->stats['dups']);
        $this->assertSame(0, $batch->stats['err']);

        // Assert: one video row created and moved to content-addressed path on the local disk
        $video = Video::query()->latest('id')->first();
        $this->assertNotNull($video);
        $this->assertSame('local', $video->disk);
        $this->assertNotEmpty($video->hash);
        $this->assertSame('mp4', $video->ext);
        $this->assertSame('clip.mp4', $video->original_name);

        // The destination path is "videos/<hash shards>/<hash>.mp4"
        $this->assertMatchesRegularExpression('#^videos/[0-9a-f]{2}/[0-9a-f]{2}/[0-9a-f]{64}\.mp4$#', $video->path);

        // The source file must be deleted
        $this->assertFileDoesNotExist($inboxAbs.'/'.$fn);

        // The new file must exist on the local disk
        $destAbs = app('filesystem')->disk('local')->path($video->path);
        $this->assertFileExists($destAbs);
        $this->assertGreaterThan(0, filesize($destAbs) ?: 0);

        // Preview URL should be set (since ffmpeg fake wrote an output)
        $this->assertNotNull($video->preview_url);
    }

    /** Duplicate handling: two identical files result in 1 new, 1 dup; the duplicate source is removed. */
    public function testScanCountsDuplicateAndDeletesDuplicateSource(): void
    {
        $this->useFakeFfmpegSuccess();

        $inboxRel = 'inbox_cmd_'.bin2hex(random_bytes(4));
        [, $fn1] = $this->makeInboxFile($inboxRel, 'a.mp4', 'SAMEBYTES');
        [, $fn2] = $this->makeInboxFile($inboxRel, 'b.mp4', 'SAMEBYTES'); // identical content → same hash
        $inboxAbs = rtrim(storage_path('app/'.$inboxRel), '/');

        $this->artisan("ingest:scan --inbox={$inboxAbs} --disk=local")
            ->assertExitCode(Command::SUCCESS);

        $batch = Batch::query()->where('type', 'ingest')->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame(1, $batch->stats['new']);
        $this->assertSame(1, $batch->stats['dups']);
        $this->assertSame(0, $batch->stats['err']);

        // Only one video stored
        $this->assertSame(1, Video::query()->count());

        // Source files are removed
        $this->assertFileDoesNotExist($inboxAbs.'/'.$fn1);
        $this->assertFileDoesNotExist($inboxAbs.'/'.$fn2);
    }

    /** Error path: non-existent inbox should produce FAILURE and print the error message. */
    public function testFailsWhenInboxDoesNotExist(): void
    {
        $missing = storage_path('app/not_there_'.bin2hex(random_bytes(4)));
        $this->artisan("ingest:scan --inbox={$missing} --disk=local")
            ->expectsOutputToContain('Inbox fehlt:')
            ->assertExitCode(Command::FAILURE);
    }
}
