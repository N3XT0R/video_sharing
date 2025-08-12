<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Clip;
use App\Models\Video;
use Illuminate\Console\Command;
use Tests\DatabaseTestCase;

/**
 * Feature tests for the "info:import" console command using the real InfoImporter.
 * No service mocking; assertions rely on DB side-effects and console output.
 */
final class InfoImportTest extends DatabaseTestCase
{
    /** Fails when neither --dir nor --csv is provided. */
    public function testFailsWhenNoDirAndNoCsvGiven(): void
    {
        $this->artisan('info:import')
            ->expectsOutput('Gib entweder --dir=/pfad/zum/ordner ODER --csv=/pfad/zur/datei.csv an.')
            ->assertExitCode(Command::FAILURE);
    }

    /** Fails when --dir points to a non-existing directory. */
    public function testFailsWhenDirDoesNotExist(): void
    {
        $dir = sys_get_temp_dir().'/infoimport_'.bin2hex(random_bytes(4));
        // do NOT create the directory

        $this->artisan("info:import --dir={$dir}")
            ->expectsOutput("Ordner nicht gefunden: {$dir}")
            ->assertExitCode(Command::FAILURE);
    }

    /** Fails when --dir has no CSV/TXT files (recursive). */
    public function testFailsWhenDirContainsNoCsv(): void
    {
        $dir = sys_get_temp_dir().'/infoimport_'.bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        // create a non-csv file
        file_put_contents($dir.'/readme.md', '# nothing');

        $this->artisan("info:import --dir={$dir}")
            ->expectsOutput("Keine CSV/TXT in {$dir} (rekursiv) gefunden.")
            ->assertExitCode(Command::FAILURE);
    }

    /** Fails when --dir contains more than one CSV/TXT file. */
    public function testFailsWhenDirContainsMultipleCsv(): void
    {
        $dir = sys_get_temp_dir().'/infoimport_'.bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $csv1 = $dir.'/a.csv';
        $csv2 = $dir.'/b.txt';
        file_put_contents($csv1, "header\n");
        file_put_contents($csv2, "header\n");

        $this->artisan("info:import --dir={$dir}")
            ->expectsOutput('Mehrere CSV/TXT gefunden. Bitte eine mit --csv=... auswählen:')
            ->expectsOutputToContain($csv1)
            ->expectsOutputToContain($csv2)
            ->assertExitCode(Command::FAILURE);
    }

    /**
     * Success: imports from explicit --csv path, with keep-csv=0 (file gets deleted).
     * Asserts created clips and final summary. Also disables role inference to use explicit CSV role.
     */
    public function testImportsFromCsvPathAndDeletesCsvWhenKeepCsvZero(): void
    {
        // Prepare videos that the CSV rows will reference via original_name
        $v1 = Video::factory()->create(['original_name' => 'dash_F.mp4', 'bytes' => 10_000]);
        $v2 = Video::factory()->create(['original_name' => 'dash_R.mp4', 'bytes' => 20_000]);

        // Build a temporary CSV
        $csv = sys_get_temp_dir().'/infoimport_'.bin2hex(random_bytes(4)).'.csv';
        $rows = [
            'filename;start;end;note;bundle;role;submitted_by',           // header
            'dash_F.mp4;00:05;00:15;front note;bundleA;F;alice',
            'dash_R.mp4;00:10;00:20;rear note;bundleB;R;bob',
        ];
        file_put_contents($csv, implode("\n", $rows));

        // Run: explicit csv, delete after import, do NOT infer role (use CSV "role")
        $this->artisan("info:import --csv={$csv} --keep-csv=0 --infer-role=0")
            ->expectsOutput("CSV/TXT gelöscht: {$csv}")
            ->expectsOutputToContain('Import fertig: neu=2, aktualisiert=0, Warnungen=0')
            ->assertExitCode(Command::SUCCESS);

        // Assert: CSV is deleted
        $this->assertFileDoesNotExist($csv);

        // Assert: two clips created with expected fields (times converted to seconds)
        $this->assertDatabaseHas('clips', [
            'video_id' => $v1->getKey(),
            'start_sec' => 5,
            'end_sec' => 15,
            'note' => 'front note',
            'bundle_key' => 'bundleA',
            'role' => 'F',
            'submitted_by' => 'alice',
        ]);
        $this->assertDatabaseHas('clips', [
            'video_id' => $v2->getKey(),
            'start_sec' => 10,
            'end_sec' => 20,
            'note' => 'rear note',
            'bundle_key' => 'bundleB',
            'role' => 'R',
            'submitted_by' => 'bob',
        ]);

        // Sanity: exactly 2 new clips
        $this->assertSame(2, Clip::query()->count());
    }

    /**
     * Success: imports by scanning a directory containing exactly one CSV.
     * Default keep-csv=1 means the CSV is kept; also rely on default infer-role=1.
     */
    public function testImportsFromDirFindsSingleCsvAndKeepsCsvByDefault(): void
    {
        // Prepare a video; leave "role" empty in CSV so infer-role=1 can fill it based on filename suffix
        $v = Video::factory()->create(['original_name' => 'trip_F.mp4', 'bytes' => 42_000]);

        // Create temp directory with a single CSV
        $dir = sys_get_temp_dir().'/infoimport_'.bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $csv = $dir.'/clips.csv';

        $rows = [
            'filename;start;end;note;bundle;role;submitted_by',
            // role left empty → infer to 'F' due to *_F.mp4
            'trip_F.mp4;01:00;01:30;auto note;bundleX;;system',
        ];
        file_put_contents($csv, implode("\n", $rows));

        // Run: only --dir provided → command discovers the single CSV; keep-csv defaults to 1 (keep file)
        $this->artisan("info:import --dir={$dir}")
            ->expectsOutput("CSV/TXT behalten: {$csv}")
            ->expectsOutputToContain('Import fertig: neu=1, aktualisiert=0, Warnungen=0')
            ->assertExitCode(Command::SUCCESS);

        // Assert: CSV is still there
        $this->assertFileExists($csv);

        // Assert: clip created; times parsed (MM:SS), role inferred to 'F'
        $this->assertDatabaseHas('clips', [
            'video_id' => $v->getKey(),
            'start_sec' => 60,
            'end_sec' => 90,
            'note' => 'auto note',
            'bundle_key' => 'bundleX',
            'role' => 'F',
            'submitted_by' => 'system',
        ]);
    }
}
