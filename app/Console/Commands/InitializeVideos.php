<?php

namespace App\Console\Commands;

use App\Services\FileGrabbingService;
use Illuminate\Console\Command;

class InitializeVideos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:initialize-videos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'initialized new videos';

    protected FileGrabbingService $service;

    public function getService(): FileGrabbingService
    {
        return $this->service;
    }

    public function setService(FileGrabbingService $service): void
    {
        $this->service = $service;
    }

    /**
     * Execute the console command.
     */
    public function handle(FileGrabbingService $service): int
    {
        $this->setService($service);
        $exitCode = self::FAILURE;

        return $exitCode;
    }

    protected function unzipNewFiles(): void
    {
        $service = $this->getService();
        $zipFiles = $service->getZipFiles();

        foreach ($zipFiles as $zipFile) {
        }
    }
}
