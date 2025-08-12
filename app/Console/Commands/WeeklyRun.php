<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WeeklyRun extends Command
{
    protected $signature = 'weekly:run';
    protected $description = 'Sonntagslauf: expire → distribute → notify';

    public function handle(): int
    {
        $exitCode = self::SUCCESS;
        $commands = [
            'assign:expire',
            'assign:distribute',
            'notify:offers'
        ];

        foreach ($commands as $command) {
            $tmpCode = $this->call($command);
            if (self::FAILURE === $tmpCode) {
                $exitCode = $tmpCode;
                break;
            }
        }

        return $exitCode;
    }
}
