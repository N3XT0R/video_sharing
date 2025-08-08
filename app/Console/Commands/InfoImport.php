<?php

namespace App\Console\Commands;

use App\Models\{Clip, Video};
use Illuminate\Console\Command;


class InfoImport extends Command
{
    protected $signature = 'info:import 
    {--dir= : Upload-Ordner mit Clips + CSV} 
    {--csv= : optionaler expliziter CSV-Pfad} 
    {--infer-role=1} 
    {--default-bundle=}';
    protected $description = 'Importiert Clip-Infos (start/end/note/bundle/role) aus CSV und verknüpft sie mit Videos.';

    public function handle(): int
    {
        $csvPath = $this->option('csv');
        $dir = $this->option('dir');

        if (!$csvPath && !$dir) {
            $this->error('Gib entweder --dir=/pfad/zum/ordner ODER --csv=/pfad/zur/datei.csv an.');
            return 1;
        }

        if ($dir) {
            if (!is_dir($dir)) {
                $this->error("Ordner nicht gefunden: $dir");
                return 1;
            }
            // Finde genau **eine** CSV/TXT
            $candidates = glob(rtrim($dir, '/').'/*.{csv,CSV,txt,TXT}', GLOB_BRACE) ?: [];
            if (count($candidates) === 0) {
                $this->error("Keine CSV/TXT in $dir gefunden.");
                return 1;
            }
            if (count($candidates) > 1) {
                $this->error("Mehrere CSV/TXT gefunden. Bitte mit --csv=... eine auswählen:\n - ".implode("\n - ",
                        $candidates));
                return 1;
            }
            $csvPath = $candidates[0];
        }

        if (!is_file($csvPath)) {
            $this->error("CSV nicht gefunden: $csvPath");
            return 1;
        }

        $fh = fopen($csvPath, 'r');
        if (!$fh) {
            $this->error("Kann CSV nicht öffnen: $csvPath");
            return 1;
        }

        // Header-Zeile mit Spaltenbeschreibung überspringen
        $header = fgets($fh);
        if ($header === false) {
            $this->warn('Leere CSV.');
            fclose($fh);
            return 0;
        }

        $count = 0;
        $updated = 0;
        $warn = 0;

        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            $row = array_pad($row, 6, '');
            [$filename, $start, $end, $note, $bundle, $role] = array_map('trim', $row);
            if ($filename === '') {
                continue;
            }

            $startSec = $this->parseTimeToSec($start);
            $endSec = $this->parseTimeToSec($end);

            if ($this->option('infer-role') && $role === '') {
                if (preg_match('/_F(\.[A-Za-z0-9]+)?$/u', $filename)) {
                    $role = 'F';
                } elseif (preg_match('/_R(\.[A-Za-z0-9]+)?$/u', $filename)) {
                    $role = 'R';
                }
            }
            if ($bundle === '' && ($def = (string)$this->option('default-bundle')) !== '') {
                $bundle = $def;
            }

            // Match über original_name == **reinem Dateinamen** (Ordner egal)
            $video = Video::query()->where('original_name', basename($filename))->first();
            if (!$video) {
                $this->warn("Kein Video gefunden für filename='".basename($filename)."'");
                $warn++;
                continue;
            }

            $clip = Clip::query()->where('video_id', $video->id)
                ->when($startSec !== null, fn($q) => $q->where('start_sec', $startSec),
                    fn($q) => $q->whereNull('start_sec'))
                ->when($endSec !== null, fn($q) => $q->where('end_sec', $endSec), fn($q) => $q->whereNull('end_sec'))
                ->when($role !== '', fn($q) => $q->where('role', $role), fn($q) => $q->whereNull('role'))
                ->first();

            if ($clip) {
                $clip->note = $note !== '' ? $note : $clip->note;
                $clip->bundle_key = $bundle !== '' ? $bundle : $clip->bundle_key;
                $clip->save();
                $updated++;
            } else {
                Clip::query()->create([
                    'video_id' => $video->id,
                    'start_sec' => $startSec,
                    'end_sec' => $endSec,
                    'note' => $note !== '' ? $note : null,
                    'bundle_key' => $bundle !== '' ? $bundle : null,
                    'role' => $role !== '' ? $role : null,
                ]);
                $count++;
            }
        }
        fclose($fh);

        $this->info("Import fertig: neu=$count, aktualisiert=$updated, Warnungen=$warn");
        $this->line("Reihenfolge im Cron: ingest:scan → info:import --dir=... → weekly:run");
        return 0;
    }
}
