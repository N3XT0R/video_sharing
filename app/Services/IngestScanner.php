<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Batch;
use App\Models\Video;
use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Dropbox\Client as DropboxClient;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

/**
 * Scannt rekursiv einen Eingangsordner und übernimmt neue Videodateien
 * in den konfigurierten Storage. Erkennt Duplikate über SHA-256.
 */
final class IngestScanner
{
    /** @var string[] */
    private const ALLOWED_EXTENSIONS = ['mp4', 'mov', 'mkv', 'avi', 'm4v', 'webm'];

    private const CHUNK_SIZE = 8 * 1024 * 1024; // 8 MB
    private const CSV_REGEX = '/\.(csv|txt)$/i';

    private ?OutputStyle $output = null;

    public function __construct(private PreviewService $previews)
    {
    }

    public function setOutput(?OutputStyle $outputStyle = null): void
    {
        $this->output = $outputStyle;
    }

    /**
     * Scan an inbox recursively and ingest new videos.
     *
     * @return array{new:int, dups:int, err:int}
     */
    public function scan(string $inbox, string $diskName): array
    {
        $this->assertDirectory($inbox);

        $this->log(sprintf('Starte Scan: %s -> %s', $inbox, $diskName));

        $batch = Batch::query()->create([
            'type' => 'ingest',
            'started_at' => now(),
        ]);

        $stats = ['new' => 0, 'dups' => 0, 'err' => 0];

        $iterator = $this->makeRecursiveIterator($inbox);

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $path => $fileInfo) {
            // 1) Falls der Eintrag ein Ordner ist: optional CSV im Unterordner importieren
            if ($fileInfo->isDir()) {
                $this->maybeImportCsvForDirectory($fileInfo->getPathname());
                continue; // nur Verzeichnis-Logik; Dateien kommen in eigener Schleife
            }

            // 2) Nur echte, zulässige Videodateien verarbeiten
            if (!$fileInfo->isFile() || !$this->isAllowedExtension($fileInfo)) {
                continue;
            }

            $this->log("Verarbeite {$path}");

            try {
                $result = $this->processFile(
                    path: $path,
                    ext: strtolower($fileInfo->getExtension()),
                    fileName: $fileInfo->getFilename(),
                    diskName: $diskName
                );

                $stats[$result]++;

                $this->updateBatchStats($batch, $stats, $diskName);
            } catch (Throwable $e) {
                Log::error($e->getMessage(), ['file' => $path]);
                $this->log("Fehler: {$e->getMessage()}");
                $stats['err']++;
            }
        }

        $batch->update([
            'finished_at' => now(),
            'stats' => $stats + ['disk' => $diskName],
        ]);

        $this->log(sprintf('Fertig. Neu: %d  Doppelt: %d  Fehler: %d', $stats['new'], $stats['dups'], $stats['err']));

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────────────────────

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

        if ($this->isDuplicate($hash)) {
            @unlink($path);
            $this->log('Duplikat übersprungen');
            return 'dups';
        }

        $dstRel = $this->buildDestinationPath($hash, $ext);

        // Video vor dem Upload anlegen, damit Preview aus lokalem Pfad generiert werden kann
        $video = Video::query()->create([
            'hash' => $hash,
            'ext' => $ext,
            'bytes' => $bytes,
            'path' => $this->makeStorageRelative($path),
            'disk' => 'local',
            'meta' => null,
            'original_name' => $fileName,
        ]);

        // Clip-Informationen nach Anlage des Videos erneut importieren
        $this->maybeImportCsvForDirectory(dirname($path));
        $video->refresh();

        $previewUrl = null;
        try {
            $this->previews->setOutput($this->output);
            $clip = $video->clips()->first();
            if ($clip && $clip->start_sec !== null && $clip->end_sec !== null) {
                $previewUrl = $this->previews->generateForClip($clip);
            } else {
                $previewUrl = $this->previews->generate($video, 0, 10);
            }
        } catch (Throwable $e) {
            Log::warning('Preview generation failed', ['file' => $path, 'e' => $e->getMessage()]);
            $this->log("Warnung: Preview konnte nicht erstellt werden ({$e->getMessage()})");
        }

        $this->log("Upload nach {$dstRel}");
        $uploaded = $this->uploadFile($path, $dstRel, $diskName, $bytes);

        if (!$uploaded) {
            $video->delete();
            $this->log('Upload fehlgeschlagen');
            return 'err';
        }

        $video->update([
            'path' => $dstRel,
            'disk' => $diskName,
            'preview_url' => $previewUrl,
        ]);

        $this->log('Upload abgeschlossen');
        return 'new';
    }

    private function isDuplicate(string $hash): bool
    {
        return Video::query()->where('hash', $hash)->exists();
    }

    private function buildDestinationPath(string $hash, string $ext): string
    {
        $sub = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
        return sprintf('videos/%s/%s%s', $sub, $hash, $ext !== '' ? ".{$ext}" : '');
    }

    private function uploadFile(string $srcPath, string $dstRel, string $diskName, int $bytes): bool
    {
        $read = fopen($srcPath, 'rb');
        if ($read === false) {
            throw new RuntimeException("Konnte Quelle nicht öffnen: {$srcPath}");
        }

        $bar = $this->createProgressBar($bytes);

        try {
            if ($diskName === 'dropbox') {
                $this->uploadToDropbox($read, $dstRel, $bytes, $bar);
                @unlink($srcPath);
                $bar?->finish();
                return true;
            }

            $disk = Storage::disk($diskName);
            $dest = $disk->path($dstRel);

            $this->ensureDirectory(dirname($dest));

            $write = fopen($dest, 'wb');
            if ($write === false) {
                throw new RuntimeException("Konnte Ziel nicht öffnen: {$dest}");
            }

            try {
                while (!feof($read)) {
                    $chunk = fread($read, self::CHUNK_SIZE);
                    if ($chunk === false) {
                        break;
                    }
                    fwrite($write, $chunk);
                    $bar?->advance(strlen($chunk));
                }
            } finally {
                fclose($write);
            }

            @unlink($srcPath);
            $bar?->finish();

            return true;
        } finally {
            // sicheres Close, falls oben Exceptions fliegen
            if (is_resource($read)) {
                fclose($read);
            }
        }
    }

    private function uploadToDropbox($read, string $dstRel, int $bytes, ?ProgressBar $bar = null): void
    {
        $root = (string)config('filesystems.disks.dropbox.root', '');
        $targetPath = '/'.trim($root.'/'.$dstRel, '/');

        /** @var AutoRefreshTokenProvider $provider */
        $provider = app(AutoRefreshTokenProvider::class);
        $client = new DropboxClient($provider);

        // Edge-Case: leere Datei
        if ($bytes === 0) {
            $client->upload($targetPath, '');
            return;
        }

        $firstChunk = fread($read, self::CHUNK_SIZE) ?: '';
        $cursor = $client->uploadSessionStart($firstChunk);
        $bar?->advance(strlen($firstChunk));

        $transferred = strlen($firstChunk);

        while (!feof($read)) {
            $chunk = fread($read, self::CHUNK_SIZE) ?: '';
            $transferred += strlen($chunk);

            if ($transferred >= $bytes) {
                // letzter Chunk
                $client->uploadSessionFinish($chunk, $cursor, $targetPath);
            } else {
                $cursor = $client->uploadSessionAppend($chunk, $cursor);
            }

            $bar?->advance(strlen($chunk));
        }
    }

    private function maybeImportCsvForDirectory(string $dirPath): void
    {
        $hasCsv = $this->directoryContainsCsv($dirPath);
        if (!$hasCsv) {
            return;
        }

        // Artisan-Call isolieren; Fehler nicht killen lassen
        try {
            Artisan::call('info:import', ['--dir' => $dirPath, '--keep-csv' => 0]);
        } catch (Throwable $e) {
            Log::warning('info:import fehlgeschlagen', [
                'dir' => $dirPath,
                'e' => $e->getMessage(),
            ]);
            $this->log("Warnung: info:import für {$dirPath} fehlgeschlagen ({$e->getMessage()})");
        }
    }

    private function directoryContainsCsv(string $dirPath): bool
    {
        try {
            foreach (new \DirectoryIterator($dirPath) as $f) {
                if ($f->isFile() && preg_match(self::CSV_REGEX, $f->getFilename())) {
                    return true;
                }
            }
        } catch (\UnexpectedValueException) {
            // nicht lesbar -> behandeln wie "keine CSV"
        }

        return false;
    }

    private function makeRecursiveIterator(string $baseDir): \RecursiveIteratorIterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $baseDir,
                \FilesystemIterator::SKIP_DOTS
                | \FilesystemIterator::CURRENT_AS_FILEINFO
                | \FilesystemIterator::FOLLOW_SYMLINKS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );
    }

    private function updateBatchStats(Batch $batch, array $stats, string $diskName): void
    {
        $batch->update([
            'stats' => $stats + ['disk' => $diskName],
        ]);
    }

    private function makeStorageRelative(string $absolute): string
    {
        $root = rtrim(str_replace('\\', '/', storage_path('app')), '/');
        $absolute = str_replace('\\', '/', $absolute);

        if (str_starts_with($absolute, $root.'/')) {
            return substr($absolute, strlen($root) + 1);
        }

        $rootParts = explode('/', trim($root, '/'));
        $absParts = explode('/', trim($absolute, '/'));
        $i = 0;
        while (isset($rootParts[$i], $absParts[$i]) && $rootParts[$i] === $absParts[$i]) {
            $i++;
        }

        $relParts = array_fill(0, count($rootParts) - $i, '..');
        $relParts = array_merge($relParts, array_slice($absParts, $i));

        return implode('/', $relParts);
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Konnte Zielordner nicht erstellen: {$dir}");
        }
    }

    private function assertDirectory(string $path): void
    {
        if (!is_dir($path)) {
            throw new RuntimeException("Inbox fehlt: {$path}");
        }
    }

    private function createProgressBar(int $max): ?ProgressBar
    {
        if ($this->output === null) {
            return null;
        }

        $bar = $this->output->createProgressBar($max);
        $bar->start();

        return $bar;
    }

    private function log(string $message): void
    {
        $this->output?->writeln($message);
    }
}
