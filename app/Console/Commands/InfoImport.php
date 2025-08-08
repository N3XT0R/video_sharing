<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InfoImporter;
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
        {--default-submitter= : submitted_by-Fallback, wenn in CSV leer}
        {--keep-csv=0 : CSV/TXT nach Import behalten (1 = nicht löschen)}';

    protected $description = 'Importiert Clip-Infos (start/end/note/bundle/role/submitted_by) aus einer CSV in den angegebenen Upload-Ordner.';

    public function handle(InfoImporter $importer): int
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

            $base = rtrim($dir, "/\\");
            $candidates = [];

            try {
                $it = new \DirectoryIterator($base);
                foreach ($it as $file) {
                    if ($file->isFile() && preg_match('/\.(csv|txt)$/i', $file->getFilename())) {
                        $candidates[] = $file->getPathname();
                    }
                }
            } catch (\UnexpectedValueException $e) {
                $this->error("Ordner kann nicht gelesen werden: {$dir} ({$e->getMessage()})");
                return self::FAILURE;
            }

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

        try {
            $result = $importer->import(
                $csvPath,
                [
                    'infer-role' => $this->optionTruthy('infer-role'),
                    'default-bundle' => (string)$this->option('default-bundle'),
                    'default-submitter' => (string)$this->option('default-submitter'),
                ],
                fn($msg) => $this->warn($msg)
            );

            // CSV nur löschen, wenn nicht --keep-csv=1
            if (!$this->optionTruthy('keep-csv') && is_file($csvPath)) {
                if (@unlink($csvPath)) {
                    $this->info("CSV/TXT gelöscht: {$csvPath}");
                } else {
                    $this->warn("CSV/TXT konnte nicht gelöscht werden: {$csvPath}");
                }
            } elseif ($this->optionTruthy('keep-csv')) {
                $this->line("CSV/TXT behalten: {$csvPath}");
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Import fertig: neu={$result['created']}, aktualisiert={$result['updated']}, Warnungen={$result['warnings']}");
        $this->line('Reihenfolge im Cron: ingest:scan → info:import (--dir oder --csv) → weekly:run');

        return self::SUCCESS;
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
