<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\DownloadStatusEnum;
use App\Events\ZipProgressUpdated;
use Illuminate\Support\Facades\Cache;

class DownloadCacheService
{
    private int $ttl = 600;

    public function init(string $jobId): void
    {
        $this->setStatus($jobId, DownloadStatusEnum::QUEUED->value);
        $this->setProgress($jobId, 0);
    }

    public function setStatus(string $jobId, string $status): void
    {
        Cache::put($this->key($jobId, 'status'), $status, $this->ttl);
        $this->broadcast($jobId);
    }

    public function getStatus(string $jobId): string
    {
        return Cache::get($this->key($jobId, 'status'), DownloadStatusEnum::UNKNOWN->value);
    }

    public function setProgress(string $jobId, int $progress): void
    {
        Cache::put($this->key($jobId, 'progress'), $progress, $this->ttl);
        $this->broadcast($jobId);
    }

    public function getProgress(string $jobId): int
    {
        return (int)Cache::get($this->key($jobId, 'progress'), 0);
    }

    public function setFile(string $jobId, string $path): void
    {
        Cache::put($this->key($jobId, 'file'), $path, $this->ttl);
    }

    public function getFile(string $jobId): ?string
    {
        return Cache::get($this->key($jobId, 'file'));
    }

    public function setName(string $jobId, string $name): void
    {
        Cache::put($this->key($jobId, 'name'), $name, $this->ttl);
        $this->broadcast($jobId);
    }

    public function getName(string $jobId, ?string $default = null): ?string
    {
        return Cache::get($this->key($jobId, 'name'), $default);
    }

    private function key(string $jobId, string $suffix): string
    {
        return "zipjob:{$jobId}:{$suffix}";
    }

    private function broadcast(string $jobId): void
    {
        event(new ZipProgressUpdated(
            $jobId,
            $this->getStatus($jobId),
            $this->getProgress($jobId),
            $this->getName($jobId),
        ));
    }
}
