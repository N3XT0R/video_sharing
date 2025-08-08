<?php
// app/Console/Commands/IngestScan.php
namespace App\Console\Commands;

use App\Models\Video;
use App\Services\IngestScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class IngestScan extends Command
{
    protected $signature = 'ingest:scan {--inbox=/srv/ingest/inbox}';
    protected $description = 'Scannt Inbox, dedupe per SHA-256, verschiebt content-addressiert in storage.';

    public function __construct(private IngestScanner $scanner)
    {
        parent::__construct();
    }

    // ...
    public function handle(): int
    {
        $inbox = rtrim($this->option('inbox'), '/');
        $batch = Batch::create(['type' => 'ingest', 'started_at' => now()]);
        if (!is_dir($inbox)) {
            $this->error("Inbox $inbox fehlt");
            return 1;
        }

        $allowed = ['mp4', 'mov', 'mkv', 'avi', 'm4v', 'webm'];
        $cntNew = $cntDup = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($inbox, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $path => $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());
            if (!in_array($ext, $allowed, true)) {
                continue;
            } // CSV etc. ignorieren

            try {
                $hash = hash_file('sha256', $path);
                if (Video::where('hash', $hash)->exists()) {
                    unlink($path);
                    $cntDup++;
                    continue;
                }

                $sub = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
                $dstRel = "videos/$sub/$hash".($ext ? ".$ext" : "");
                Storage::makeDirectory("videos/$sub");
                rename($path, Storage::path($dstRel));

                Video::query()->create([
                    'hash' => $hash,
                    'ext' => $ext,
                    'bytes' => filesize(Storage::path($dstRel)),
                    'path' => $dstRel,
                    'meta' => null,
                    // Nur der **Dateiname** ist relevant für CSV-Matching, nicht der Ordner
                    'original_name' => basename($fileInfo->getFilename()),
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