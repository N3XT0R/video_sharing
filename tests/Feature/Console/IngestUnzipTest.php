<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Command;
use Tests\DatabaseTestCase;
use ZipArchive;

final class IngestUnzipTest extends DatabaseTestCase
{
    public function testRunsExtractionAndRendersStats(): void
    {
        $dir = storage_path('app/unzip_'.bin2hex(random_bytes(4)));
        mkdir($dir, 0777, true);

        $zip = new ZipArchive();
        $zip->open($dir.'/good.zip', ZipArchive::CREATE);
        $zip->addFromString('ok.txt', 'hi');
        $zip->close();

        $this->artisan("ingest:unzip --inbox={$dir}")
            ->expectsOutput('Done. total=1 extracted=1 failed=0 skipped=0')
            ->expectsOutput('Extracted: good.zip')
            ->assertExitCode(Command::SUCCESS);

        $this->assertFileExists($dir.'/ok.txt');
        $this->assertFileDoesNotExist($dir.'/good.zip');
    }

    public function testReturnsFailureWhenDirectoryMissing(): void
    {
        $dir = storage_path('app/missing_'.bin2hex(random_bytes(4)));

        $this->artisan("ingest:unzip --inbox={$dir}")
            ->expectsOutput("Directory not found or not readable: {$dir}")
            ->assertExitCode(Command::FAILURE);
    }

    public function testAbortsWhenLockIsHeld(): void
    {
        $lock = cache()->lock('ingest:lock', 10);
        $this->assertTrue($lock->get());

        $dir = storage_path('app/unzip_'.bin2hex(random_bytes(4)));
        mkdir($dir, 0777, true);

        $this->artisan("ingest:unzip --inbox={$dir}")
            ->expectsOutput('Another ingest task is running. Abort.')
            ->assertExitCode(Command::SUCCESS);

        $lock->release();
    }
}
