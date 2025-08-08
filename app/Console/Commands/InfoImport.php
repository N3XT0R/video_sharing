<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{Clip, Video};
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InfoImport extends Command
{
    protected $signature = 'info:import {--file=/srv/ingest/info.csv} {--bundle-duration=}';
    protected $description = 'Parst Info-CSV/Text und legt Clip-Annotationen + Bundles an.';

    public function handle(): int
    {
        $file = $this->option('file');
        if (!is_file($file)) {
            $this->error("File not found: $file");
            return 1;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = 0;
        $warn = 0;

        foreach ($lines as $line) {
            // Format: <name>( & R optional): mm:ss - mm:ss [ - Anmerkung: ...]
            // 1) split name vs rest
            $parts = explode(':', $line, 2);
            if (count($parts) < 2) {
                $this->warn("Skip: $line");
                $warn++;
                continue;
            }
            $namePart = trim($parts[0]);
            $rest = trim($parts[1]);

            // 2) Note extrahieren
            $note = null;
            if (Str::contains($rest, 'Anmerkung:')) {
                [$range, $note] = array_map('trim', explode('Anmerkung:', $rest, 2));
            } else {
                $range = $rest;
            }

            // 3) Zeiten parsen "mm:ss - mm:ss"
            if (!preg_match('/(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})/', $range, $m)) {
                $this->warn("No range: $line");
                $warn++;
                continue;
            }
            $start = (int)$m[1] * 60 + (int)$m[2];
            $end = (int)$m[3] * 60 + (int)$m[4];

            // 4) Dateinamen & Bundles interpretieren
            // Case A: "..._F.MP4 & R" -> suche F und R
            $bundleKey = null;
            $roles = [];

            if (preg_match('/(.+)_F\.[A-Za-z0-9]+(\s*&\s*R)?$/u', $namePart, $mm)) {
                $base = $mm[1];
                $bundleKey = Str::slug($base, '_'); // z.B. teenies_schlafen_an_der_ampel
                $roles = ['F', 'R'];
                $names = [
                    $base.'_F', // mit beliebiger Extension matchen
                    $base.'_R',
                ];
            } else {
                // Case B: einzelnes File – bundle_key = basename ohne Ext/role
                $base = preg_replace('/\.[A-Za-z0-9]+$/', '', $namePart);
                $bundleKey = Str::slug($base, '_');
                $roles = [null];
                $names = [$base];
            }

            foreach ($names as $idx => $needle) {
                // Video suchen: per original_name (ohne ext) oder path-Basename
                $video = Video::where('original_name', 'like', $needle.'%')
                    ->orWhere('path', 'like', '%/'.$needle.'.%')
                    ->first();

                if (!$video) {
                    $this->warn("Video not found for '$needle'");
                    $warn++;
                    continue;
                }

                Clip::query()->create([
                    'video_id' => $video->id,
                    'start_sec' => $start,
                    'end_sec' => $end,
                    'note' => $note,
                    'bundle_key' => $bundleKey,
                    'role' => $roles[$idx] ?? null,
                ]);
                $count++;
            }
        }
        $this->info("Imported $count clip annotations. Warnings: $warn");
        return 0;
    }
}