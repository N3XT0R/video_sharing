<?php

declare(strict_types=1);

namespace App\Services\Zip;

use App\Services\Contracts\UnzipServiceInterface;
use App\Services\Zip\Dto\UnzipStats;
use Illuminate\Filesystem\Filesystem;
use ZipArchive;

readonly class UnzipService implements UnzipServiceInterface
{
    public function __construct(
        private Filesystem $fs
    ) {
    }

    /**
     * @inheritDoc
     */
    public function unzipDirectory(string $directory): UnzipStats
    {
        // Validate directory
        $dir = rtrim($directory, '/');
        if (!$this->fs->isDirectory($dir) || !$this->fs->isReadable($dir)) {
            throw new \InvalidArgumentException("Directory not found or not readable: {$dir}");
        }

        // Collect .zip files (non-recursive by design; adjust if needed)
        $files = $this->fs->files($dir);
        $zipFiles = array_values(array_filter($files, fn($f) => str_ends_with(strtolower((string)$f), '.zip')));

        $extracted = [];
        $failed = [];
        $skipped = [];

        foreach ($zipFiles as $zipPath) {
            $basename = basename((string)$zipPath);

            $result = $this->safeExtract((string)$zipPath, $dir);

            if ($result === SafeExtractResult::EXTRACTED) {
                // Delete archive only after successful extraction
                @unlink((string)$zipPath);
                $extracted[] = $basename;
            } elseif ($result === SafeExtractResult::SKIPPED_NO_SAFE_ENTRIES) {
                $skipped[] = $basename;
            } else {
                $failed[] = $basename;
            }
        }

        return new UnzipStats($extracted, $failed, $skipped);
    }

    /**
     * Extract only safe entries (prevents Zip-Slip / path traversal).
     */
    private function safeExtract(string $zipPath, string $targetDir): string
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            return SafeExtractResult::FAILED_OPEN;
        }

        // Build a whitelist of safe entries (skip dangerous paths)
        $safeEntries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if ($this->isSafeZipEntry($name)) {
                $safeEntries[] = $name;
            }
        }

        if ($safeEntries === []) {
            $zip->close();
            return SafeExtractResult::SKIPPED_NO_SAFE_ENTRIES;
        }

        // extractTo will create directories as needed
        $ok = (bool)$zip->extractTo($targetDir, $safeEntries);
        $zip->close();

        return $ok ? SafeExtractResult::EXTRACTED : SafeExtractResult::FAILED_EXTRACT;
    }

    /**
     * Accept only relative, "normal" paths inside the archive.
     */
    private function isSafeZipEntry(string $name): bool
    {
        // No absolute paths (Unix/Windows)
        if (str_starts_with($name, '/') || str_starts_with($name, '\\')) {
            return false;
        }
        // No Windows drive letter prefixes like "C:\"
        if (preg_match('#^[a-zA-Z]:#', $name) === 1) {
            return false;
        }
        // No traversal segments or null bytes
        $parts = preg_split('#[\\\\/]+#', $name) ?: [];
        foreach ($parts as $p) {
            if ($p === '..' || $p === "\0") {
                return false;
            }
        }
        return true;
    }
}