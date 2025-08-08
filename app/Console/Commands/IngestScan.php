<?php
// app/Console/Commands/IngestScan.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{Batch, Video};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Dropbox\Client as DropboxClient;

class IngestScan extends Command
{
    protected $signature = 'ingest:scan 
        {--inbox=/srv/ingest/pending : Wurzelordner der Uploads (rekursiv)} 
        {--disk= : Ziel-Storage-Disk (z.B. dropbox|local; überschreibt Config)}';

    protected $description = 'Scannt Inbox rekursiv, dedupe per SHA-256, verschiebt content-addressiert auf konfiguriertes Storage.';

    public function handle(): int
    {
        $inbox = rtrim((string)$this->option('inbox'), '/');
        $diskName = (string)($this->option('disk') ?: config('files.video_disk', env('FILES_VIDEOS_DISK', 'dropbox')));
        $disk = Storage::disk($diskName);
        $this->info('started...');

        if (!is_dir($inbox)) {
            $this->error("Inbox fehlt: {$inbox}");
            return self::FAILURE;
        }

        $batch = Batch::create(['type' => 'ingest', 'started_at' => now()]);

        $allowed = ['mp4', 'mov', 'mkv', 'avi', 'm4v', 'webm'];
        $newCount = 0;
        $dupCount = 0;
        $errorCount = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($inbox, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /**
         * @var \SplFileInfo $fileInfo
         */
        foreach ($it as $path => $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());
            if (!in_array($ext, $allowed, true)) {
                continue;
            } // CSV, TXT etc. ignorieren

            $fileName = $fileInfo->getFilename();

            try {
                $this->info('processing file: '.$fileName);
                // Hash & Bytes von der lokalen Quelle ermitteln
                $hash = hash_file('sha256', $path);
                $bytes = filesize($path);

                // Duplicate? -> lokale Datei löschen und weiter
                if (Video::query()->where('hash', $hash)->exists()) {
                    $this->info('duplicated file: '.$fileName);
                    @unlink($path);
                    $dupCount++;
                    continue;
                }

                // Content-addressierter Zielpfad
                $sub = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
                $dstRel = "videos/{$sub}/{$hash}".($ext ? ".{$ext}" : '');

                // Upload als Stream (Cloud-kompatibel), dann Quelle löschen
                $read = fopen($path, 'rb');
                if ($read === false) {
                    throw new \RuntimeException("Konnte Quelle nicht öffnen: {$path}");
                }

                // Für Cloud-Disks kein makeDirectory nötig
                $this->info('uploading file: '.$fileName);
                $this->info($dstRel);

                $uploadedSuccess = false;
                if ($diskName === 'dropbox') {
                    $chunkSize = 8 * 1024 * 1024; // 8MB
                    $root = config('filesystems.disks.dropbox.root', '');
                    $targetPath = '/' . trim($root . '/' . $dstRel, '/');
                    $client = new DropboxClient(config('filesystems.disks.dropbox.authorization_token'));

                    $bar = $this->output->createProgressBar($bytes);
                    $bar->start();

                    $firstChunk = fread($read, $chunkSize);
                    $cursor = $client->uploadSessionStart($firstChunk);
                    $bar->advance(strlen($firstChunk));

                    if (strlen($firstChunk) < $bytes) {
                        while (!feof($read)) {
                            $chunk = fread($read, $chunkSize);
                            if (feof($read)) {
                                $client->uploadSessionFinish($chunk, $cursor, $targetPath);
                            } else {
                                $cursor = $client->uploadSessionAppend($chunk, $cursor);
                            }
                            $bar->advance(strlen($chunk));
                        }
                    } else {
                        $client->uploadSessionFinish('', $cursor, $targetPath);
                    }

                    $bar->finish();
                    $this->newLine();
                    fclose($read);
                    @unlink($path);
                    $uploadedSuccess = true;
                } else {
                    $this->line('hochladen…');
                    $uploadedSuccess = $disk->put($dstRel, $read);
                    if (is_resource($read)) {
                        fclose($read);
                    }
                    if ($uploadedSuccess) {
                        @unlink($path);
                    }
                }

                if ($uploadedSuccess) {
                    // DB-Eintrag
                    Video::query()->create([
                        'hash' => $hash,
                        'ext' => $ext,
                        'bytes' => $bytes,
                        'path' => $dstRel,
                        'disk' => $diskName,
                        'meta' => null,
                        'original_name' => $fileName,
                    ]);

                    $newCount++;
                    $this->info('finished file: '.$fileName);
                } else {
                    $errorCount++;
                    $this->error('error file: '.$fileName);
                }
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $errorCount++;
            }
        }

        $batch->update([
            'finished_at' => now(),
            'stats' => ['new' => $newCount, 'dups' => $dupCount, 'err' => $errorCount, 'disk' => $diskName],
        ]);

        $this->info("Ingest done. new={$newCount} dups={$dupCount} err={$errorCount} disk={$diskName}");
        return self::SUCCESS;
    }
}

