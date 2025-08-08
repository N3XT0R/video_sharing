<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Clip;
use App\Models\Video;
use RuntimeException;

class InfoImporter
{
    /**
     * Import clip information from a CSV file.
     *
     * @param  array{infer-role?:bool, default-bundle?:string|null, default-submitter?:string|null}  $options
     * @param  callable(string):void|null  $onWarning  Optional callback for warnings
     * @return array{created:int, updated:int, warnings:int}
     */
    public function import(string $csvPath, array $options = [], ?callable $onWarning = null): array
    {
        $inferRole = (bool)($options['infer-role'] ?? false);
        $defaultBundle = $options['default-bundle'] ?? '';
        $defaultSubmitter = $options['default-submitter'] ?? '';

        $fh = fopen($csvPath, 'rb');
        if ($fh === false) {
            throw new RuntimeException("Kann CSV nicht öffnen: {$csvPath}");
        }

        $headerLine = fgets($fh);
        if ($headerLine === false) {
            fclose($fh);

            return ['created' => 0, 'updated' => 0, 'warnings' => 0];
        }

        $createdCount = 0;
        $updatedCount = 0;
        $warningCount = 0;

        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            $row = array_pad($row, 7, '');
            [$filename, $start, $end, $note, $bundle, $role, $submittedBy] = array_map(
                fn($v) => $this->trimUtf8Bom((string)$v),
                $row
            );

            if ($filename === '') {
                continue;
            }

            $startSec = $this->parseTimeToSec($start, $onWarning, $warningCount);
            $endSec = $this->parseTimeToSec($end, $onWarning, $warningCount);

            if ($inferRole && $role === '') {
                if (preg_match('/_F(\.[A-Za-z0-9]+)?$/u', $filename)) {
                    $role = 'F';
                } elseif (preg_match('/_R(\.[A-Za-z0-9]+)?$/u', $filename)) {
                    $role = 'R';
                }
            }

            if ($bundle === '' && $defaultBundle !== '') {
                $bundle = $defaultBundle;
            }
            if ($submittedBy === '' && $defaultSubmitter !== '') {
                $submittedBy = $defaultSubmitter;
            }

            $base = basename($filename);
            $video = Video::query()->where('original_name', $base)->first();

            if (!$video) {
                $warningCount++;
                if ($onWarning) {
                    $onWarning("Kein Video gefunden für filename='{$base}'");
                }

                continue;
            }

            $clip = Clip::query()->where('video_id', $video->id)
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
                Clip::query()->create([
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

        return ['created' => $createdCount, 'updated' => $updatedCount, 'warnings' => $warningCount];
    }

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
