<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Batch, Video};
use Illuminate\Support\Facades\{Log, Storage};
use RuntimeException;

class IngestScanner
{
    /**
     * Scan an inbox for new videos, deduplicate and move them to storage.
     *
     * @return array{new:int, dups:int}
     */
    public function scan(string $inbox): array
    {
        $batch = Batch::query()->create(['type' => 'ingest', 'started_at' => now()]);
        if (!is_dir($inbox)) {
            $batch->update(['finished_at' => now(), 'stats' => ['new' => 0, 'dups' => 0]]);
            throw new RuntimeException("Inbox $inbox fehlt");
        }

        $cntNew = $cntDup = 0;
        foreach (glob($inbox.'/*') as $src) {
            if (!is_file($src)) {
                continue;
            }
            try {
                $hash = hash_file('sha256', $src);
                $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                $orig = basename($src); // Original-Name aus der Inbox


                if (Video::query()->where('hash', $hash)->exists()) {
                    unlink($src);
                    $cntDup++;
                    continue;
                }

                $sub = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
                $dstRel = "videos/$sub/$hash".($ext ? ".$ext" : "");
                Storage::makeDirectory("videos/$sub");
                rename($src, Storage::path($dstRel));

                Video::query()->create([
                    'hash' => $hash,
                    'ext' => $ext,
                    'bytes' => filesize(Storage::path($dstRel)),
                    'path' => $dstRel,
                    'meta' => null,
                    'original_name' => $orig,
                ]);
                $cntNew++;
            } catch (\Throwable $e) {
                Log::error($e->getMessage());
            }
        }
        $batch->update(['finished_at' => now(), 'stats' => ['new' => $cntNew, 'dups' => $cntDup]]);
        return ['new' => $cntNew, 'dups' => $cntDup];
    }
}

