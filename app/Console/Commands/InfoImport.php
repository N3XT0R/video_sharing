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
        {--dir= : Upload-Ordner mit Clips; alle CSV/TXT darunter werden importiert}
        {--csv= : Optional: direkter Pfad zur CSV/TXT}
        {--infer-role=1 : Rolle (F/R) aus Dateinamen _F/_R ableiten, wenn Spalte leer}
        {--default-bundle= : Bundle-Fallback, wenn in CSV leer}
        {--default-submitter= : submitted_by-Fallback, wenn in CSV leer}';

    protected $description = 'Importiert Clip-Infos (start/end/note/bundle/role/submitted_by) aus einer CSV in den angegebenen Upload-Ordner.';

    public function handle(InfoImporter $importer): int
    {
        $csvPath = (string) ($this->option('csv') ?? '');
        $dir = (string) ($this->option('dir') ?? '');

        if ($csvPath === '' && $dir === '') {
            $this->error('Gib entweder --dir=/pfad/zum/ordner ODER --csv=/pfad/zur/datei.csv an.');

            return self::FAILURE;
        }

        $csvPaths = [];

        if ($csvPath !== '') {
            $csvPaths[] = $csvPath;
        } elseif ($dir !== '') {
            if (! is_dir($dir)) {
                $this->error("Ordner nicht gefunden: {$dir}");

                return self::FAILURE;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.(csv|txt)$/i', $file->getFilename())) {
                    $csvPaths[] = $file->getPathname();
                }
            }

            if (count($csvPaths) === 0) {
                $this->error("Keine CSV/TXT in {$dir} gefunden.");

                return self::FAILURE;
            }
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalWarnings = 0;

        foreach ($csvPaths as $path) {
            if (! is_file($path)) {
                $this->error("CSV nicht gefunden: {$path}");

                return self::FAILURE;
            }

            try {
                $this->line('Importiere '.$path.' ...');
                $result = $importer->import($path, [
                    'infer-role' => $this->optionTruthy('infer-role'),
                    'default-bundle' => (string) $this->option('default-bundle'),
                    'default-submitter' => (string) $this->option('default-submitter'),
                ], fn ($msg) => $this->warn($msg));
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $totalWarnings += $result['warnings'];
        }

        $this->info("Import fertig: neu={$totalCreated}, aktualisiert={$totalUpdated}, Warnungen={$totalWarnings}");
        $this->line('Reihenfolge im Cron: ingest:scan → info:import (--dir oder --csv) → weekly:run');

        return self::SUCCESS;
    }

    private function optionTruthy(string $name): bool
    {
        $val = $this->option($name);
        if ($val === null) {
            return false;
        }
        $s = strtolower((string) $val);

        return in_array($s, ['1', 'true', 'on', 'yes', 'y'], true);
    }
}
