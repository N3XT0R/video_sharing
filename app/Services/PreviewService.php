<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PreviewService
{
    private ?OutputStyle $output = null;

    /**
     * Optional: Console-Ausgabe aktivieren (z. B. in Commands)
     */
    public function setOutput(?OutputStyle $outputStyle = null): void
    {
        $this->output = $outputStyle;
    }

    /**
     * Erzeugt eine eindeutige Zielpfad-Signatur pro Video+Zeitfenster.
     */
    private function buildPath(Video $video, int $start, int $end): string
    {
        $hash = md5($video->id.'_'.$start.'_'.$end);
        return "previews/{$hash}.mp4";
    }

    /**
     * Generate a preview clip for the given video and return its public URL.
     * Returns null on failure.
     */
    public function generate(Video $video, int $start, int $end): ?string
    {
        // 1) Input validieren
        if ($start < 0 || $end <= $start) {
            $this->warn("Ungültiger Zeitbereich: start={$start}, end={$end}");
            return null;
        }

        $duration = $end - $start;

        // 2) Quelldatei prüfen
        $sourceDisk = Storage::disk($video->disk ?? 'local');
        if (!$sourceDisk->exists($video->path)) {
            $this->error("Quelldatei nicht gefunden: disk={$video->disk} path={$video->path}");
            return null;
        }

        $srcPath = $sourceDisk->path($video->path);
        $previewDisk = Storage::disk('public');
        $previewPath = $this->buildPath($video, $start, $end);
        $previewUrlFn = fn() => $previewDisk->url($previewPath);

        // 3) Cache-Hit?
        if ($previewDisk->exists($previewPath)) {
            $this->info("Preview vorhanden (Cache-Hit): {$previewPath}");
            return $previewUrlFn();
        }

        // 4) ffmpeg ausführen
        $ffmpeg = (string)config('services.ffmpeg.bin', 'ffmpeg'); // konfigurierbar
        $tmpFile = tempnam(sys_get_temp_dir(), 'preview_');
        if ($tmpFile === false) {
            $this->error('Konnte temporäre Datei nicht anlegen.');
            return null;
        }
        // tempnam erstellt ohne Extension; für Klarheit benennen wir sie um
        $tmpOut = $tmpFile.'.mp4';
        @rename($tmpFile, $tmpOut);

        $cmd = sprintf(
            '%s -y -ss %d -i %s -t %d -an -vcodec libx264 -preset veryfast -crf 28 %s 2>&1',
            escapeshellcmd($ffmpeg),
            $start,
            escapeshellarg($srcPath),
            $duration,
            escapeshellarg($tmpOut)
        );

        $this->info("Erzeuge Preview: start={$start}s, end={$end}s, duration={$duration}s");
        $this->debug("ffmpeg: {$cmd}");

        $t0 = microtime(true);
        exec($cmd, $out, $ret);
        $elapsed = microtime(true) - $t0;

        if ($ret !== 0 || !is_file($tmpOut)) {
            $last = $this->tail($out, 10);
            $this->error("ffmpeg fehlgeschlagen (code={$ret}, t=".number_format($elapsed, 2)."s)");
            if ($last !== '') {
                $this->debug("ffmpeg out:\n{$last}");
            }
            @unlink($tmpOut);
            return null;
        }

        // 5) Ergebnis ins public-Storage legen
        $putOk = false;
        $stream = fopen($tmpOut, 'rb');
        if ($stream !== false) {
            $putOk = $previewDisk->put($previewPath, $stream);
            fclose($stream);
        }

        $size = @filesize($tmpOut) ?: 0;
        @unlink($tmpOut);

        if (!$putOk) {
            $this->error("Konnte Preview nicht in public speichern: {$previewPath}");
            return null;
        }

        $this->info("Preview erstellt: {$previewPath} (".$this->humanBytes($size).", t=".number_format($elapsed,
                2)."s)");

        return $previewUrlFn();
    }

    /**
     * Gibt – falls vorhanden – die öffentliche URL für ein bereits generiertes Preview zurück.
     */
    public function url(Video $video, int $start, int $end): ?string
    {
        if ($start < 0 || $end <= $start) {
            return null;
        }

        $previewDisk = Storage::disk('public');
        $previewPath = $this->buildPath($video, $start, $end);

        return $previewDisk->exists($previewPath)
            ? $previewDisk->url($previewPath)
            : null;
    }

    // ───────────────────────────────────── Hilfsfunktionen ─────────────────────────────────────

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
        // Nicht in die Konsole spammen – nur ins Log auf DEBUG-Level
        Log::debug($message, ['service' => 'PreviewService']);
    }

    /**
     * Gibt die letzten N Zeilen aus einer exec-Ausgabe zurück.
     *
     * @param  array<int,string>  $lines
     */
    private function tail(array $lines, int $n): string
    {
        $slice = array_slice($lines, -$n);
        return implode(PHP_EOL, $slice);
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return sprintf('%.1f %s', $bytes, $units[$i]);
    }
}
