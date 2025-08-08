<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AssignmentDistributor;
use Illuminate\Console\Command;
use RuntimeException;

class AssignDistribute extends Command
{
    protected $signature = 'assign:distribute {--quota=}';
    protected $description = 'Verteilt neue und expired Videos fair auf Kanäle (Round-Robin, gewichtet, Quota/Woche).';

    public function __construct(private AssignmentDistributor $distributor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $quota = $this->option('quota');
            $stats = $this->distributor->distribute($quota !== null ? (int) $quota : null);
            $this->info("Assigned={$stats['assigned']}, skipped={$stats['skipped']}");
        } catch (RuntimeException $e) {
            $this->warn($e->getMessage());
        }
        return self::SUCCESS;
    }
}
