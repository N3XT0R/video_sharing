<?php
// app/Console/Commands/IngestScan.php
namespace App\Console\Commands;

use App\Services\IngestScanner;
use Illuminate\Console\Command;
use RuntimeException;

class IngestScan extends Command
{
    protected $signature = 'ingest:scan {--inbox=/srv/ingest/inbox}';
    protected $description = 'Scannt Inbox, dedupe per SHA-256, verschiebt content-addressiert in storage.';

    public function __construct(private IngestScanner $scanner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $inbox = $this->option('inbox');

        try {
            $stats = $this->scanner->scan($inbox);
            $this->info("Ingest done. new={$stats['new']} dups={$stats['dups']}");
            return 0;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}