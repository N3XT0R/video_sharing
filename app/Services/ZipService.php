<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Batch;
use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ZipService
{
    public function build(Batch $batch, Channel $channel, array $pathsOnDisk): string
    {
        $batchId = $batch->getKey();
        $name = $channel->getAttribute('name');
        $jobId = $batchId.'_'.$name;
        $downloadName = sprintf('videos_%s_%s_selected.zip', $batchId, Str::slug($name));
        $tmpPath = "zips/{$jobId}.zip";
        Storage::makeDirectory('zips');

        $zip = new ZipArchive();
        $absPath = Storage::path($tmpPath);
        $zip->open($absPath, ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $total = max(count($pathsOnDisk), 1);
        $i = 0;

        Cache::put($this->key($jobId, 'status'), 'working', 600);
        Cache::put($this->key($jobId, 'progress'), 0, 600);

        foreach ($pathsOnDisk as $path) {
            $zip->addFile($path, basename($path));
            $i++;
            $pct = (int)floor($i * 100 / $total);
            Cache::put($this->key($jobId, 'progress'), $pct, 600);
        }

        $zip->close();

        Cache::put($this->key($jobId, 'status'), 'ready', 600);
        Cache::put($this->key($jobId, 'file'), $tmpPath, 600);
        Cache::put($this->key($jobId, 'name'), $downloadName, 600);

        return $tmpPath;
    }

    public function key(string $jobId, string $suffix): string
    {
        return "zipjob:{$jobId}:{$suffix}";
    }
}