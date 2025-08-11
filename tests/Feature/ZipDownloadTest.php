<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ZipDownloadTest extends TestCase
{
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
}
