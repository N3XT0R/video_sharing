<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{Clip, Video};
use Illuminate\Console\Command;

class InfoImport extends Command
{
    /**
     * Erwartetes CSV-Format (Semikolon-getrennt, erste Zeile = Spaltenbeschreibung):
     * filename;start;end;note;bundle;role;submitted_by
     */
    protected $signature = 'info:import
        {--dir= : Upload-Ordner mit Clips + genau 1 CSV/TXT}
        {--csv= : Optional: direkter Pfad zur CSV/TXT}
        {--infer-role=1 : Rolle (F/R) aus Dateinamen _F/_R ableiten, wenn Spalte leer}
        {--default-bundle= : Bundle-Fallback, wenn in CSV leer}
        {--default-submitter= : submitted_by-Fallback, wenn in CSV leer}';

    protected $description = 'Importiert Clip-Infos (start/end/note/bundle/role/submitted_by) aus einer CSV in den angegebenen Upload-Ordner.';

    public function handle(): int
    {
        $csvPath = (string)($this->option('csv') ?? '');
        $dir = (string)($this->option('dir') ?? '');

        if ($csvPath === '' && $dir === '') {
            $this->error('Gib entweder --dir=/pfad/zum/ordner ODER --csv=/pfad/zur/datei.csv an.');
            return self::FAILURE;
        }

        if ($dir !== '' && $csvPath === '') {
            if (!is_dir($dir)) {
                $this->error("Ordner nicht gefunden: {$dir}");
                return self::FAILURE;
            }
            // genau EINE CSV/TXT im Ordner finden
            $candidates = glob(rtrim($dir, '/').'/*.{csv,CSV,txt,TXT}', GLOB_BRACE) ?: [];
            if (count($candidates) === 0) {
                $this->error("Keine CSV/TXT in {$dir} gefunden.");
                return self::FAILURE;
            }
            if (count($candidates) > 1) {
                $this->error("Mehrere CSV/TXT in {$dir} gefunden. Bitte eine mit --csv=... auswählen:");
                foreach ($candidates as $c) {
                    $this->line(' - '.$c);
                }
                return self::FAILURE;
            }
            $csvPath = $candidates[0];
        }

        if (!is_file($csvPath)) {
            $this->error("CSV nicht gefunden: {$csvPath}");
            return self::FAILURE;
        }

        $fh = fopen($csvPath, 'rb');
        if (!$fh) {
            $this->error("Kann CSV nicht öffnen: {$csvPath}");
            return self::FAILURE;
        }

        // Erste Zeile ist die Spaltenbeschreibung – lesen & verwerfen
        $headerLine = fgets($fh);
        if ($headerLine === false) {
            fclose($fh);
            $this->warn('Leere CSV.');
            return self::SUCCESS;
        }

        // UTF-8 BOM am Anfang der ersten Datenzeile tolerieren
        $createdCount = 0;
        $updatedCount = 0;
        $warningCount = 0;

        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            // CSV kann weniger Spalten haben – auffüllen
            $row = array_pad($row, 7, '');
            [$filename, $start, $end, $note, $bundle, $role, $submittedBy] = array_map(
                fn($v) => $this->trimUtf8Bom((string)$v),
                $row
            );

            if ($filename === '') {
                continue; // komplett leere Zeile überspringen
            }

            // Zeiten parsen (MM:SS oder H:MM:SS oder Sekunden)
            $startSec = $this->parseTimeToSec($start);
            $endSec = $this->parseTimeToSec($end);

            // Rolle intelligent ableiten, wenn gewünscht und leer
            if ($this->optionTruthy('infer-role') && $role === '') {
                if (preg_match('/_F(\.[A-Za-z0-9]+)?$/u', $filename)) {
                    $role = 'F';
                } elseif (preg_match('/_R(\.[A-Za-z0-9]+)?$/u', $filename)) {
                    $role = 'R';
                }
            }

            // Defaults einziehen
            if ($bundle === '' && ($defB = (string)$this->option('default-bundle')) !== '') {
                $bundle = $defB;
            }
            if ($submittedBy === '' && ($defS = (string)$this->option('default-submitter')) !== '') {
                $submittedBy = $defS;
            }

            // Video-Matching nur über reinen Dateinamen (Ordner egal)
            $base = basename($filename);
            $video = Video::where('original_name', $base)->first();

            if (!$video) {
                $this->warn("Kein Video gefunden für filename='{$base}'");
                $warningCount++;
                continue;
            }

            // Upsert-Logik: gleicher video_id + (start,end,role) → update; sonst create
            $clip = Clip::where('video_id', $video->id)
                ->when($startSec !== null, fn($q) => $q->where('start_sec', $startSec),
                    fn($q) => $q->whereNull('start_sec'))
                ->when($endSec !== null, fn($q) => $q->where('end_sec', $endSec), fn($q) => $q->whereNull('end_sec'))
                ->when($role !== '', fn($q) => $q->where('role', $role), fn($q) => $q->whereNull('role'))
                ->first();

            if ($clip) {
                $dirty = false;
                if ($note !== '' && $clip->note !== $note) {
                    $clip->note = $note;
                    $dirty = true;
                }
                if ($bundle !== '' && $clip->bundle_key !== $bundle) {
                    $clip->bundle_key = $bundle;
                    $dirty = true;
                }
                if ($submittedBy !== '' && $clip->submitted_by !== $submittedBy) {
                    $clip->submitted_by = $submittedBy;
                    $dirty = true;
                }
                if ($dirty) {
                    $clip->save();
                    $updatedCount++;
                }
            } else {
                Clip::create([
                    'video_id' => $video->id,
                    'start_sec' => $startSec,
                    'end_sec' => $endSec,
                    'note' => $note !== '' ? $note : null,
                    'bundle_key' => $bundle !== '' ? $bundle : null,
                    'role' => $role !== '' ? $role : null,
                    'submitted_by' => $submittedBy !== '' ? $submittedBy : null,
                ]);
                $createdCount++;
            }
        }

        fclose($fh);

        $this->info("Import fertig: neu={$createdCount}, aktualisiert={$updatedCount}, Warnungen={$warningCount}");
        $this->line("Reihenfolge im Cron: ingest:scan → info:import (--dir oder --csv) → weekly:run");
        return self::SUCCESS;
    }

    private function parseTimeToSec(?string $s): ?int
    {
        $s = trim((string)$s);
        if ($s === '') {
            return null;
        }

        // H:MM:SS
        if (preg_match('/^(?:(\d+):)?([0-5]?\d):([0-5]\d)$/', $s, $m)) {
            $h = (int)($m[1] ?? 0);
            $mm = (int)$m[2];
            $ss = (int)$m[3];
            return $h * 3600 + $mm * 60 + $ss;
        }

        // MM:SS
        if (preg_match('/^([0-5]?\d):([0-5]\d)$/', $s, $m)) {
            return ((int)$m[1]) * 60 + (int)$m[2];
        }

        // Nur Sekunden
        if (ctype_digit($s)) {
            return (int)$s;
        }

        $this->warn("Ungültige Zeitangabe: '{$s}' (erwartet MM:SS oder H:MM:SS oder Sekunden)");
        return null;
    }

    private function trimUtf8Bom(string $v): string
    {
        // BOM am Wortanfang killen
        if (strncmp($v, "\xEF\xBB\xBF", 3) === 0) {
            $v = substr($v, 3);
        }
        return trim($v);
    }

    private function optionTruthy(string $name): bool
    {
        $val = $this->option($name);
        if ($val === null) {
            return false;
        }
        $s = strtolower((string)$val);
        return in_array($s, ['1', 'true', 'on', 'yes', 'y'], true);
    }
}
