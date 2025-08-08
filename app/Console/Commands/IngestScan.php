<?php
// app/Console/Commands/IngestScan.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{Batch, Video};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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

        foreach ($it as $path => $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());
            if (!in_array($ext, $allowed, true)) {
                continue;
            } // CSV, TXT etc. ignorieren

            try {
                // Hash & Bytes von der lokalen Quelle ermitteln
                $hash = hash_file('sha256', $path);
                $bytes = filesize($path);

                // Duplicate? -> lokale Datei löschen und weiter
                if (Video::where('hash', $hash)->exists()) {
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
                $disk->put($dstRel, $read);
                if (is_resource($read)) {
                    fclose($read);
                }
                @unlink($path);

                // DB-Eintrag
                Video::query()->create([
                    'hash' => $hash,
                    'ext' => $ext,
                    'bytes' => $bytes,
                    'path' => $dstRel,
                    'disk' => $diskName,
                    'meta' => null,
                    'original_name' => $fileInfo->getFilename(), // nur Dateiname, Ordner egal
                ]);

                $newCount++;

                // OPTIONAL: Thumbnails/Metadaten lokal generieren (ffprobe/ffmpeg)
                // -> wenn gewünscht, hier per Queue/Job nachschieben.

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

