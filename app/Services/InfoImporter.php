<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Clip;
use App\Models\Video;
use RuntimeException;

class InfoImporter
{
    private const CSV_DELIMITER = ';';
    private const ROW_COLUMNS = 7;

    /**
     * Import clip information from a CSV file.
     *
     * @param  array{infer-role?:bool, default-bundle?:string|null, default-submitter?:string|null}  $options
     * @param  callable(string):void|null  $onWarning  Optional callback for warnings
     * @return array{created:int, updated:int, warnings:int}
     */
    public function import(string $csvPath, array $options = [], ?callable $onWarning = null): array
    {
        [$inferRole, $defaultBundle, $defaultSubmitter] = $this->parseOptions($options);

        $fh = $this->openCsvOrFail($csvPath);

        // Read and ignore the header line. If there is no header, return empty stats.
        if ($this->readHeader($fh) === false) {
            fclose($fh);
            return ['created' => 0, 'updated' => 0, 'warnings' => 0];
        }

        $stats = ['created' => 0, 'updated' => 0, 'warnings' => 0];

        while (($row = fgetcsv($fh, 0, self::CSV_DELIMITER)) !== false) {
            $this->processRow(
                row: $row,
                inferRole: $inferRole,
                defaultBundle: (string)$defaultBundle,
                defaultSubmitter: (string)$defaultSubmitter,
                onWarning: $onWarning,
                stats: $stats
            );
        }

        fclose($fh);

        return $stats;
    }

    // === Pipeline steps =======================================================

    /**
     * Normalize and extract importer options.
     * @return array{0:bool,1:string,2:string}
     */
    private function parseOptions(array $options): array
    {
        $inferRole = (bool)($options['infer-role'] ?? false);
        $defaultBundle = (string)($options['default-bundle'] ?? '');
        $defaultSubmitter = (string)($options['default-submitter'] ?? '');

        return [$inferRole, $defaultBundle, $defaultSubmitter];
    }

    /**
     * Open CSV file or throw if it cannot be opened.
     * @return resource
     */
    private function openCsvOrFail(string $csvPath)
    {
        $fh = fopen($csvPath, 'rb');
        if ($fh === false) {
            // Keep original user-facing message
            throw new RuntimeException("Kann CSV nicht öffnen: {$csvPath}");
        }
        return $fh;
    }

    /**
     * Read the first line (header) and return it, or false if none exists.
     * @param  resource  $fh
     * @return string|false
     */
    private function readHeader($fh): string|false
    {
        return fgets($fh);
    }

    /**
     * Process a single CSV row end-to-end.
     *
     * @param  list<string|null>  $row
     * @param  array{created:int,updated:int,warnings:int}  $stats  (by-ref)
     */
    private function processRow(
        array $row,
        bool $inferRole,
        string $defaultBundle,
        string $defaultSubmitter,
        ?callable $onWarning,
        array &$stats
    ): void {
        [$filename, $start, $end, $note, $bundle, $role, $submittedBy] = $this->sanitizeRow($row);

        // Skip empty lines (no filename)
        if ($filename === '') {
            return;
        }

        $startSec = $this->parseTimeToSec($start, $onWarning, $stats['warnings']);
        $endSec = $this->parseTimeToSec($end, $onWarning, $stats['warnings']);

        $role = $this->inferRoleIfNeeded($filename, $role, $inferRole);
        [$bundle, $submittedBy] = $this->applyDefaults($bundle, $submittedBy, $defaultBundle, $defaultSubmitter);

        $baseName = basename($filename);
        $video = $this->findVideoOrWarn($baseName, $onWarning, $stats['warnings']);
        if (!$video) {
            // Without a video, nothing more to do for this row
            return;
        }

        $clip = $this->findExistingClip(
            videoId: (int)$video->getKey(),
            startSec: $startSec,
            endSec: $endSec,
            role: $role
        );

        if ($clip) {
            $this->updateClipIfDirty($clip, $note, $bundle, $submittedBy, $stats['updated']);
        } else {
            $this->createClip($video, $startSec, $endSec, $note, $bundle, $role, $submittedBy, $stats['created']);
        }
    }

    /**
     * Ensure row has the expected number of columns and trim values (incl. BOM).
     * @param  list<string|null>  $row
     * @return array{0:string,1:string,2:string,3:string,4:string,5:string,6:string}
     */
    private function sanitizeRow(array $row): array
    {
        $row = array_pad($row, self::ROW_COLUMNS, '');
        /** @var array{0:string,1:string,2:string,3:string,4:string,5:string,6:string} $mapped */
        $mapped = array_map(fn($v) => $this->trimUtf8Bom((string)$v), $row);

        [$filename, $start, $end, $note, $bundle, $role, $submittedBy] = $mapped;

        return [$filename, $start, $end, $note, $bundle, $role, $submittedBy];
    }

    /**
     * Infer role from filename suffix if requested and role is empty.
     */
    private function inferRoleIfNeeded(string $filename, string $role, bool $inferRole): string
    {
        if (!$inferRole || $role !== '') {
            return $role;
        }

        if (preg_match('/_F(\.[A-Za-z0-9]+)?$/u', $filename)) {
            return 'F';
        }

        if (preg_match('/_R(\.[A-Za-z0-9]+)?$/u', $filename)) {
            return 'R';
        }

        return $role;
    }

    /**
     * Apply default bundle/submitter if the respective value is empty.
     * @return array{0:string,1:string}
     */
    private function applyDefaults(
        string $bundle,
        string $submittedBy,
        string $defaultBundle,
        string $defaultSubmitter
    ): array {
        if ($bundle === '' && $defaultBundle !== '') {
            $bundle = $defaultBundle;
        }
        if ($submittedBy === '' && $defaultSubmitter !== '') {
            $submittedBy = $defaultSubmitter;
        }

        return [$bundle, $submittedBy];
    }

    /**
     * Look up video by original name, or emit a warning.
     */
    private function findVideoOrWarn(string $baseName, ?callable $onWarning, int &$warningCount): ?Video
    {
        $video = Video::query()->where('original_name', $baseName)->first();

        if (!$video) {
            $warningCount++;
            if ($onWarning) {
                // Keep original user-facing message
                $onWarning("Kein Video gefunden für filename='{$baseName}'");
            }
        }

        return $video;
    }

    /**
     * Find an existing clip by composite identity (video + start/end/role).
     */
    private function findExistingClip(int $videoId, ?int $startSec, ?int $endSec, string $role): ?Clip
    {
        return Clip::query()
            ->where('video_id', $videoId)
            ->when(
                $startSec !== null,
                fn($q) => $q->where('start_sec', $startSec),
                fn($q) => $q->whereNull('start_sec')
            )
            ->when(
                $endSec !== null,
                fn($q) => $q->where('end_sec', $endSec),
                fn($q) => $q->whereNull('end_sec')
            )
            ->when(
                $role !== '',
                fn($q) => $q->where('role', $role),
                fn($q) => $q->whereNull('role')
            )
            ->first();
    }

    /**
     * Update the clip only if any relevant field actually changed.
     */
    private function updateClipIfDirty(
        Clip $clip,
        string $note,
        string $bundle,
        string $submittedBy,
        int &$updatedCount
    ): void {
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
    }

    /**
     * Create a new clip from the given values.
     */
    private function createClip(
        Video $video,
        ?int $startSec,
        ?int $endSec,
        string $note,
        string $bundle,
        string $role,
        string $submittedBy,
        int &$createdCount
    ): void {
        Clip::query()->create([
            'video_id' => $video->getKey(),
            'start_sec' => $startSec,
            'end_sec' => $endSec,
            'note' => $note !== '' ? $note : null,
            'bundle_key' => $bundle !== '' ? $bundle : null,
            'role' => $role !== '' ? $role : null,
            'submitted_by' => $submittedBy !== '' ? $submittedBy : null,
        ]);

        $createdCount++;
    }

    // === Existing helpers (behavior preserved) ================================

    private function parseTimeToSec(?string $s, ?callable $onWarning, int &$warningCount): ?int
    {
        $s = trim((string)$s);
        if ($s === '') {
            return null;
        }

        if (preg_match('/^(?:(\d+):)?([0-5]?\d):([0-5]\d)$/', $s, $m)) {
            $h = (int)($m[1] ?? 0);
            $mm = (int)$m[2];
            $ss = (int)$m[3];

            return $h * 3600 + $mm * 60 + $ss;
        }

        if (preg_match('/^([0-5]?\d):([0-5]\d)$/', $s, $m)) {
            return ((int)$m[1]) * 60 + (int)$m[2];
        }

        if (ctype_digit($s)) {
            return (int)$s;
        }

        $warningCount++;
        if ($onWarning) {
            // Keep original user-facing message
            $onWarning("Ungültige Zeitangabe: '{$s}' (erwartet MM:SS oder H:MM:SS oder Sekunden)");
        }

        return null;
    }

    private function trimUtf8Bom(string $v): string
    {
        if (strncmp($v, "\xEF\xBB\xBF", 3) === 0) {
            $v = substr($v, 3);
        }

        return trim($v);
    }
}
