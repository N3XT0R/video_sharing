<?php

declare(strict_types=1);

// app/Console/Commands/VideoCleanup.php

namespace App\Console\Commands;

use App\Services\VideoCleanupService;
use Illuminate\Console\Command;

class VideoCleanup extends Command
{
    protected $signature = 'video:cleanup {--weeks=1 : Anzahl der Wochen, die der Ablauf überschritten haben muss}';

    protected $description = 'Löscht heruntergeladene Videos, deren Ablauf seit der angegebenen Wochenzahl überschritten ist.';

    public function __construct(private VideoCleanupService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $weeks = (int) $this->option('weeks');
        $deleted = $this->service->cleanup($weeks);
        $this->info("Removed: {$deleted}");

        return self::SUCCESS;
    }
}

