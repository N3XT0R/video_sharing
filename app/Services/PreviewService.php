<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Video;
use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

final class PreviewService
{
    private ?OutputStyle $output = null;

    // ───────────────────────────────── public API ─────────────────────────────────

    /**
     * Optional: Console-Ausgabe aktivieren (z. B. in Commands).
     */
    public function setOutput(?OutputStyle $outputStyle = null): void
    {
        $this->output = $outputStyle;
    }

    /**
     * Erzeugt (falls nicht vorhanden) einen Vorschauclip und gibt dessen öffentliche URL zurück.
     * Gibt null zurück, wenn etwas schiefgeht.
     */
    public function generate(Video $video, int $start, int $end): ?string
    {
        if (!$this->isValidRange($start, $end)) {
            $this->warn("Ungültiger Zeitbereich: start={$start}, end={$end}");
            return null;
        }

        $duration = $end - $start;
        $sourceDisk = Storage::disk($video->disk ?? 'local');
        if (!$sourceDisk->exists($video->path)) {
            $this->error("Quelldatei nicht gefunden: disk={$video->disk} path={$video->path}");
            return null;
        }

        $previewDisk = Storage::disk('public');
        $previewPath = $this->buildPath($video, $start, $end);

        // Cache-Hit
        if ($previewDisk->exists($previewPath)) {
            $this->info("Preview vorhanden (Cache): {$previewPath}");
            return $previewDisk->url($previewPath);
        }

        // Quelle auf lokalen Pfad auflösen (lokal: path(); remote: readStream -> Tempfile)
        [$srcPath, $isTempSrc] = $this->resolveLocalSourcePath($sourceDisk, $video->path);
        if ($srcPath === null) {
            $this->error('Konnte Quelle nicht für ffmpeg bereitstellen.');
            return null;
        }

        // ffmpeg ausführen
        $tmpOut = $this->makeTempFile('.mp4');
        if ($tmpOut === null) {
            $this->error('Konnte temporäre Datei nicht anlegen.');
            if ($isTempSrc) {
                @unlink($srcPath);
            }
            return null;
        }

        $args = $this->makeFfmpegArgs(
            srcPath: $srcPath,
            dstPath: $tmpOut,
            start: $start,
            duration: $duration
        );

        $this->info("Erzeuge Preview: start={$start}s, end={$end}s, duration={$duration}s");
        $this->debug('ffmpeg args: '.json_encode($args, JSON_UNESCAPED_SLASHES));

        try {
            $ok = $this->runFfmpeg($args, $elapsedSec);
            if (!$ok || !is_file($tmpOut)) {
                $this->error('ffmpeg fehlgeschlagen'.($elapsedSec !== null ? ' (t='.number_format($elapsedSec,
                            2).'s)' : ''));
                return null;
            }

            // Ergebnis in public speichern
            $size = @filesize($tmpOut) ?: 0;
            $put = $this->putFileToDisk($previewDisk, $previewPath, $tmpOut);
            if (!$put) {
                $this->error("Konnte Preview nicht in public speichern: {$previewPath}");
                return null;
            }

            $this->info("Preview erstellt: {$previewPath} (".$this->humanBytes($size).($elapsedSec !== null ? ', t='.number_format($elapsedSec,
                        2).'s' : '').')');

            return $previewDisk->url($previewPath);
        } finally {
            // Cleanup temporärer Dateien
            @unlink($tmpOut);
            if ($isTempSrc) {
                @unlink($srcPath);
            }
        }
    }

    /**
     * Gibt die öffentliche URL eines bereits vorhandenen Previews zurück – oder null.
     */
    public function url(Video $video, int $start, int $end): ?string
    {
        if (!$this->isValidRange($start, $end)) {
            return null;
        }

        $previewDisk = Storage::disk('public');
        $previewPath = $this->buildPath($video, $start, $end);

        return $previewDisk->exists($previewPath)
            ? $previewDisk->url($previewPath)
            : null;
    }

    // ───────────────────────────────── intern / helpers ─────────────────────────────────

    private function isValidRange(int $start, int $end): bool
    {
        return $start >= 0 && $end > $start;
    }

    private function buildPath(Video $video, int $start, int $end): string
    {
        $hash = md5($video->id.'_'.$start.'_'.$end);
        return "previews/{$hash}.mp4";
    }

    /**
     * Auflösung einer Disk-Resource auf einen lokalen lesbaren Pfad:
     *  - lokale Disks: ->path($relative)
     *  - Remote-Disks (Dropbox etc.): readStream() -> temporäre Datei
     *
     * @return array{0:?string,1:bool} [localPath|null, isTemp]
     */
    private function resolveLocalSourcePath(FilesystemAdapter $disk, string $relativePath): array
    {
        // 1) Versuch: native path()-Auflösung (nur lokale Disks)
        try {
            $path = $disk->path($relativePath); // kann bei Remote-Disks Exception werfen
            if (is_string($path) && $path !== '' && is_file($path)) {
                return [$path, false];
            }
        } catch (\Throwable $e) {
            // fällt durch auf Remote-Handling
            $this->debug('Disk path() nicht verfügbar: '.$e->getMessage());
        }

        // 2) Remote-Handling: Datei streamen und lokal puffern
        $stream = $disk->readStream($relativePath);
        if ($stream === false) {
            $this->error("readStream fehlgeschlagen: {$relativePath}");
            return [null, false];
        }

        $tmp = $this->makeTempFile('.src');
        if ($tmp === null) {
            fclose($stream);
            $this->error('Konnte Tempdatei für Source nicht anlegen.');
            return [null, false];
        }

        $out = @fopen($tmp, 'wb');
        if ($out === false) {
            fclose($stream);
            @unlink($tmp);
            $this->error('Konnte Tempdatei nicht öffnen (write).');
            return [null, false];
        }

        try {
            while (!feof($stream)) {
                $buf = fread($stream, 1024 * 1024);
                if ($buf === false) {
                    break;
                }
                fwrite($out, $buf);
            }
        } finally {
            fclose($stream);
            fclose($out);
        }

        return [$tmp, true];
    }

    /**
     * Erstellt sichere ffmpeg-Argumente basierend auf Konfiguration.
     * Nutzt standardmäßig libx264, crf/preset aus config('services.ffmpeg.*').
     */
    private function makeFfmpegArgs(string $srcPath, string $dstPath, int $start, int $duration): array
    {
        $ffmpeg = (string)config('services.ffmpeg.bin', 'ffmpeg');
        $crf = (int)config('services.ffmpeg.crf', 28);
        $preset = (string)config('services.ffmpeg.preset', 'veryfast');

        // Optional: weitere Parameter (Skalierung etc.) aus der Config
        $extraVideoArgs = (array)config('services.ffmpeg.video_args', []); // z. B. ['-vf','scale=-2:720']

        $args = [
            $ffmpeg,
            '-y',
            '-ss',
            (string)$start,
            '-i',
            $srcPath,
            '-t',
            (string)$duration,
            '-an',
            '-vcodec',
            'libx264',
            '-preset',
            $preset,
            '-crf',
            (string)$crf,
        ];

        if (!empty($extraVideoArgs)) {
            $args = array_merge($args, $extraVideoArgs);
        }

        $args[] = $dstPath;

        return $args;
    }

    /**
     * Führt ffmpeg via Symfony Process aus, loggt live stdout/stderr und liefert Erfolg + Zeit zurück.
     *
     * @param  array<int,string>  $args
     * @param  float|null  $elapsedSec  (out)
     */
    private function runFfmpeg(array $args, ?float &$elapsedSec = null): bool
    {
        $timeout = config('services.ffmpeg.timeout');      // sekunden oder null
        $idle = config('services.ffmpeg.idle_timeout'); // sekunden oder null

        $t0 = microtime(true);

        $process = new Process($args);
        if ($timeout !== null) {
            $process->setTimeout((float)$timeout);
        }
        if ($idle !== null && method_exists($process, 'setIdleTimeout')) {
            $process->setIdleTimeout((float)$idle);
        }

        // Live-Logging
        $process->run(function (string $type, string $buffer): void {
            $line = trim($buffer);
            if ($line === '') {
                return;
            }
            if ($type === Process::ERR) {
                Log::debug('[ffmpeg][stderr] '.$line, ['service' => 'PreviewService']);
            } else {
                Log::debug('[ffmpeg][stdout] '.$line, ['service' => 'PreviewService']);
            }
        });

        $elapsedSec = microtime(true) - $t0;

        if ($process->isSuccessful()) {
            return true;
        }

        $this->error(sprintf(
            'ffmpeg exit=%s (%s)',
            (string)$process->getExitCode(),
            $process->getExitCodeText() ?: 'unknown'
        ));
        $this->debug("stdout tail:\n".$this->tailLines($process->getOutput(), 10));
        $this->debug("stderr tail:\n".$this->tailLines($process->getErrorOutput(), 10));

        return false;
    }

    /**
     * Speichert eine lokale Datei in einen Laravel-Disk-Pfad.
     */
    private function putFileToDisk(FilesystemAdapter $disk, string $dstPath, string $localFile): bool
    {
        $stream = @fopen($localFile, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            return (bool)$disk->put($dstPath, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Legt eine temporäre Datei an und hängt optional eine Extension an.
     */
    private function makeTempFile(string $suffix = ''): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'preview_');
        if ($tmp === false) {
            return null;
        }
        if ($suffix !== '') {
            $renamed = $tmp.$suffix;
            @rename($tmp, $renamed);
            return $renamed;
        }
        return $tmp;
    }

    // ───────────────────────────────── Logging-Helpers ─────────────────────────────────

    private function info(string $message): void
    {
        $this->output?->writeln("<info>{$message}</info>");
        Log::info($message, ['service' => 'PreviewService']);
    }

    private function warn(string $message): void
    {
        $this->output?->writeln("<comment>{$message}</comment>");
        Log::warning($message, ['service' => 'PreviewService']);
    }

    private function error(string $message): void
    {
        $this->output?->writeln("<error>{$message}</error>");
        Log::error($message, ['service' => 'PreviewService']);
    }

    private function debug(string $message): void
    {
        Log::debug($message, ['service' => 'PreviewService']);
    }

    private function tailLines(string $text, int $n): string
    {
        $lines = preg_split('/\R/', $text) ?: [];
        return implode(PHP_EOL, array_slice($lines, -$n));
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < \count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return sprintf('%.1f %s', $bytes, $units[$i]);
    }
}
