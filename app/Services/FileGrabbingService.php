<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;

class FileGrabbingService
{
    protected Filesystem $filesystem;

    private const UNZIP_DIR = 'unzipped';
    private const ZIP_DIR = 'zipped';


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

    public function getZipFiles(): array
    {
        $zipFiles = [];
        $zipDir = self::ZIP_DIR;
        $disk = $this->getFilesystem();
        if (!$disk->exists($zipDir)) {
            $disk->makeDirectory($zipDir);
        }

        $files = $disk->files($zipDir);
        foreach ($files as $file) {
            if (Str::endsWith('.zip', $file)) {
                $zipFiles[] = $file;
            }
        }

        return $zipFiles;
    }

    public function unpackZipFilesToUnpackDir(array $zipFiles): void
    {
        $unzipDir = self::UNZIP_DIR;
        $disk = $this->getFilesystem();
        $path = $unzipDir.DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;

        $directories = [$unzipDir, $path];
        foreach ($directories as $directory) {
            if (!$disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }
        }

        foreach ($zipFiles as $zipFile) {
            $zipArchive = new \ZipArchive();
            if ($zipArchive->open($disk->path($zipFile))) {
                $result = $zipArchive->extractTo($disk->path($path));
                $zipArchive->close();
                if (true === $result) {
                    $disk->delete($zipFile);
                }
            } else {
                throw new \RuntimeException('file '.$zipFile.' could not be extracted');
            }
        }
    }
}