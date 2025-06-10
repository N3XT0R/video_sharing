<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;

class FileGrabbingService
{
    protected Filesystem $filesystem;


    public function __construct(Filesystem $filesystem)
    {
        $this->setFilesystem($filesystem);
    }

    public function getFilesystem(): Filesystem
    {
        return $this->filesystem;
    }

    public function setFilesystem(Filesystem $filesystem): void
    {
        $this->filesystem = $filesystem;
    }
}