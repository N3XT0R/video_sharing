<?php

declare(strict_types=1);

// app/Console/Commands/VideoCleanup.php

namespace App\Console\Commands;

use App\Services\VideoCleanupService;
use Illuminate\Console\Command;

class VideoCleanup extends Command
{
    protected $signature = 'video:cleanup';

    protected $description = 'Löscht heruntergeladene Videos, deren Ablauf seit einer Woche überschritten ist.';

    public function __construct(private VideoCleanupService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $deleted = $this->service->cleanup();
        $this->info("Removed: {$deleted}");

        return self::SUCCESS;
    }
}

