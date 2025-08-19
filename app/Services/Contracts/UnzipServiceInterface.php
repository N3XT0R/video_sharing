<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Services\Zip\Dto\UnzipStats;

interface UnzipServiceInterface
{
    /**
     * Unzip all .zip files found directly under the given directory.
     * Returns a stats DTO with per-archive results.
     *
     * @throws \InvalidArgumentException if the directory does not exist or is not readable
     */
    public function unzipDirectory(string $directory): UnzipStats;
}