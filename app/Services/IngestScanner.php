<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Batch, Video};
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\{Log, Storage};
use RuntimeException;
use Spatie\Dropbox\Client as DropboxClient;
use Throwable;

class IngestScanner
{
    private const ALLOWED_EXTENSIONS = ['mp4', 'mov', 'mkv', 'avi', 'm4v', 'webm'];


    protected OutputStyle $outputStyle;

    public function setOutput(OutputStyle $outputStyle): void
    {
        $this->outputStyle = $outputStyle;
    }

    /**
     * Scan an inbox recursively and ingest new videos.
     *
     * @return array{new:int, dups:int, err:int}
     */
    public function scan(string $inbox, string $diskName): array
    {
        if (!is_dir($inbox)) {
            throw new RuntimeException("Inbox fehlt: {$inbox}");
        }

        $batch = Batch::query()->create(['type' => 'ingest', 'started_at' => now()]);
        $stats = ['new' => 0, 'dups' => 0, 'err' => 0];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($inbox, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $path => $fileInfo) {
            if (!$fileInfo->isFile() || !$this->isAllowedExtension($fileInfo)) {
                continue;
            }

            try {
                $result = $this->processFile($path, strtolower($fileInfo->getExtension()), $fileInfo->getFilename(),
                    $diskName);
                $stats[$result]++;
                $batch->update([
                    'stats' => [
                        'new' => $stats['new'],
                        'dups' => $stats['dups'],
                        'err' => $stats['err'],
                        'disk' => $diskName
                    ],
                ]);
            } catch (Throwable $e) {
                Log::error($e->getMessage());
                $stats['err']++;
            }
        }

        $batch->update([
            'finished_at' => now(),
            'stats' => ['new' => $stats['new'], 'dups' => $stats['dups'], 'err' => $stats['err'], 'disk' => $diskName],
        ]);

        return $stats;
    }

    private function isAllowedExtension(\SplFileInfo $file): bool
    {
        return in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * @return 'new'|'dups'|'err'
     */
    private function processFile(string $path, string $ext, string $fileName, string $diskName): string
    {
        $hash = hash_file('sha256', $path);
        $bytes = filesize($path);

        if (Video::query()->where('hash', $hash)->exists()) {
            @unlink($path);
            return 'dups';
        }

        $dstRel = $this->buildDestinationPath($hash, $ext);
        $uploaded = $this->uploadFile($path, $dstRel, $diskName, $bytes);

        if (!$uploaded) {
            return 'err';
        }

        Video::query()->create([
            'hash' => $hash,
            'ext' => $ext,
            'bytes' => $bytes,
            'path' => $dstRel,
            'disk' => $diskName,
            'meta' => null,
            'original_name' => $fileName,
        ]);

        return 'new';
    }

    private function buildDestinationPath(string $hash, string $ext): string
    {
        $sub = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
        return "videos/{$sub}/{$hash}".($ext ? ".{$ext}" : '');
    }

    private function uploadFile(string $path, string $dstRel, string $diskName, int $bytes): bool
    {
        $read = fopen($path, 'rb');
        if ($read === false) {
            throw new RuntimeException("Konnte Quelle nicht öffnen: {$path}");
        }

        try {
            if ($diskName === 'dropbox') {
                $this->uploadToDropbox($read, $dstRel, $bytes);
                fclose($read);
                @unlink($path);
                return true;
            }

            $disk = Storage::disk($diskName);
            $uploaded = $disk->put($dstRel, $read);
            if (is_resource($read)) {
                fclose($read);
            }
            if ($uploaded) {
                @unlink($path);
            }
            return $uploaded;
        } finally {
            if (is_resource($read)) {
                fclose($read);
            }
        }
    }

    private function uploadToDropbox($read, string $dstRel, int $bytes): void
    {
        $chunkSize = 8 * 1024 * 1024; // 8MB
        $root = config('filesystems.disks.dropbox.root', '');
        $targetPath = '/'.trim($root.'/'.$dstRel, '/');
        $client = new DropboxClient(config('filesystems.disks.dropbox.authorization_token'));

        $firstChunk = fread($read, $chunkSize);
        $cursor = $client->uploadSessionStart($firstChunk);

        if (strlen($firstChunk) < $bytes) {
            while (!feof($read)) {
                $chunk = fread($read, $chunkSize);
                if (feof($read)) {
                    $client->uploadSessionFinish($chunk, $cursor, $targetPath);
                } else {
                    $cursor = $client->uploadSessionAppend($chunk, $cursor);
                }
            }
        } else {
            $client->uploadSessionFinish('', $cursor, $targetPath);
        }
    }
}
