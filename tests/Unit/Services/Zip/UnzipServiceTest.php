<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zip;

use App\Services\Zip\UnzipService;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class UnzipServiceTest extends TestCase
{
    /** @var string */
    private string $tmpDir;

    /** @var Filesystem */
    private Filesystem $fs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new Filesystem();

        // Create a unique temp directory for each test
        $this->tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'unzip_service_'.bin2hex(random_bytes(6));

        $this->fs->makeDirectory($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Recursively delete temp directory
        $this->deleteDir($this->tmpDir);
        parent::tearDown();
    }

    public function testThrowsOnInvalidDirectory(): void
    {
        // Arrange
        $service = new UnzipService($this->fs);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $service->unzipDirectory($this->tmpDir + '/does-not-exist');
    }

    public function testReturnsEmptyStatsWhenNoArchivesPresent(): void
    {
        // Arrange
        $service = new UnzipService($this->fs);

        // Act
        $stats = $service->unzipDirectory($this->tmpDir);

        // Assert
        $this->assertSame([], $stats->extractedArchives);
        $this->assertSame([], $stats->failedArchives);
        $this->assertSame([], $stats->skippedArchives);
    }

    public function testExtractsSafeEntriesAndDeletesArchive(): void
    {
        // Arrange
        $archive = $this->tmp('safe.zip');
        $this->createZip($archive, [
            'file.txt' => 'hello',
            'subdir/inner.txt' => 'world',
        ]);

        $service = new UnzipService($this->fs);

        // Act
        $stats = $service->unzipDirectory($this->tmpDir);

        // Assert: archive should be listed as extracted and removed from disk
        $this->assertContains('safe.zip', $stats->extractedArchives);
        $this->assertFileDoesNotExist($archive);

        // Extracted files should exist inside target dir
        $this->assertFileExists($this->tmpDir.'/file.txt');
        $this->assertFileExists($this->tmpDir.'/subdir/inner.txt');
        $this->assertSame('hello', file_get_contents($this->tmpDir.'/file.txt'));
        $this->assertSame('world', file_get_contents($this->tmpDir.'/subdir/inner.txt'));
    }

    public function testSkipsArchivesWithOnlyUnsafeEntries(): void
    {
        // Arrange: only traversal entries ⇒ no safe entries
        $archive = $this->tmp('unsafe_only.zip');
        $this->createZip($archive, [
            '../evil.txt' => 'nope',
            './../../x' => 'nope',
        ]);

        $service = new UnzipService($this->fs);

        // Act
        $stats = $service->unzipDirectory($this->tmpDir);

        // Assert: archive should be listed as skipped and remain on disk
        $this->assertContains('unsafe_only.zip', $stats->skippedArchives);
        $this->assertFileExists($archive);

        // No files should be extracted
        $this->assertFileDoesNotExist($this->tmpDir.'/evil.txt');
        $this->assertFileDoesNotExist($this->tmpDir.'/x');
    }

    public function testExtractsOnlySafeEntriesWhenMixedAndDeletesArchive(): void
    {
        // Arrange: mix safe + unsafe; should extract safe ones, skip unsafe internally
        $archive = $this->tmp('mixed.zip');
        $this->createZip($archive, [
            'ok.txt' => 'ok',
            '../evil.txt' => 'bad',
            'inside/ok2.md' => '# ok2',
        ]);

        $service = new UnzipService($this->fs);

        // Act
        $stats = $service->unzipDirectory($this->tmpDir);

        // Assert: considered extracted (since there are safe entries)
        $this->assertContains('mixed.zip', $stats->extractedArchives);
        $this->assertFileDoesNotExist($archive);

        // Only safe entries should exist
        $this->assertFileExists($this->tmpDir.'/ok.txt');
        $this->assertFileExists($this->tmpDir.'/inside/ok2.md');
        $this->assertFileDoesNotExist($this->tmpDir.'/evil.txt');

        $this->assertSame('ok', file_get_contents($this->tmpDir.'/ok.txt'));
        $this->assertSame('# ok2', file_get_contents($this->tmpDir.'/inside/ok2.md'));
    }

    public function testMarksBrokenArchiveAsFailedAndKeepsFile(): void
    {
        // Arrange: create a non-zip file with .zip extension so ZipArchive::open() fails
        $archive = $this->tmp('broken.zip');
        file_put_contents($archive, 'this is not a zip');

        $service = new UnzipService($this->fs);

        // Act
        $stats = $service->unzipDirectory($this->tmpDir);

        // Assert: listed as failed and still present
        $this->assertContains('broken.zip', $stats->failedArchives);
        $this->assertFileExists($archive);
    }

    // ───────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────────────────────

    /** Create a file path inside the temp directory. */
    private function tmp(string $basename): string
    {
        return $this->tmpDir.DIRECTORY_SEPARATOR.$basename;
    }

    /**
     * Create a ZIP archive with given entries (name => content).
     * Directory entries are created implicitly by using "path/to/file".
     *
     * @param  array<string,string>  $entries
     */
    private function createZip(string $zipPath, array $entries): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Failed to create test archive: '.$zipPath);
        }

        foreach ($entries as $name => $content) {
            // Use addFromString to create files (directories will be created as needed)
            $zip->addFromString($name, $content);
        }

        $zip->close();
        $this->assertFileExists($zipPath, 'Archive was not created on disk.');
    }

    /** Recursively delete a directory if it exists. */
    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $fileInfo) {
            $path = $fileInfo->getPathname();
            if ($fileInfo->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
