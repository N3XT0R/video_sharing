<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{Batch, Video};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class IngestScan extends Command
{
    protected $signature = 'ingest:scan {--inbox=/srv/ingest/inbox}';
    protected $description = 'Scannt Inbox, dedupe per SHA-256, verschiebt content-addressiert in storage.';

    public function handle(): int
    {
        $inbox = $this->option('inbox');
        $batch = Batch::query()->create(['type' => 'ingest', 'started_at' => now()]);
        if (!is_dir($inbox)) {
            $this->error("Inbox $inbox fehlt");
            return 1;
        }

        $cntNew = $cntDup = 0;
        foreach (glob($inbox.'/*') as $src) {
            if (!is_file($src)) {
                continue;
            }
            try {
                $hash = hash_file('sha256', $src);
                $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
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
                ]);
                $cntNew++;
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
            }
        }
        $batch->update(['finished_at' => now(), 'stats' => ['new' => $cntNew, 'dups' => $cntDup]]);
        $this->info("Ingest done. new=$cntNew dups=$cntDup");
        return 0;
    }
}