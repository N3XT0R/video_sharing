<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WeeklyRun extends Command
{
    protected $signature = 'weekly:run';
    protected $description = 'expire → distribute → notify';

    public function handle(): int
    {
        $this->call('assign:expire');
        $this->call('assign:distribute');
        $this->call('notify:offers');
        return self::SUCCESS;
    }
}
