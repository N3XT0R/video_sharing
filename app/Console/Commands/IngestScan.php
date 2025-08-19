<?php
// app/Console/Commands/IngestScan.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\IngestScanner;
use Illuminate\Console\Command;
use RuntimeException;

class IngestScan extends Command
{
    protected $signature = 'ingest:scan
        {--inbox=/srv/ingest/pending : Wurzelordner der Uploads (rekursiv)}
        {--disk=dropbox : Ziel-Storage-Disk (z.B. dropbox|local; überschreibt Config)}';

    protected $description = 'Scannt Inbox rekursiv, dedupe per SHA-256, verschiebt content-addressiert auf konfiguriertes Storage.';

    public function __construct(private IngestScanner $scanner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $inbox = rtrim((string)$this->option('inbox'), '/');
        $diskName = (string)$this->option('disk');
        $this->info('started...');

        $output = $this->getOutput();
        $this->scanner->setOutput($output);
        try {
            $stats = $this->scanner->scan($inbox, $diskName);
            $this->info("Ingest done. new={$stats['new']} dups={$stats['dups']} err={$stats['err']} disk={$diskName}");
            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}

