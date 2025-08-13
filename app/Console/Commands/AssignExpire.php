<?php

declare(strict_types=1);

// app/Console/Commands/AssignExpire.php
namespace App\Console\Commands;

use App\Facades\Cfg;
use App\Services\AssignmentExpirer;
use Illuminate\Console\Command;

class AssignExpire extends Command
{
    protected $signature = 'assign:expire {--cooldown-days=}';
    protected $description = 'Markiert überfällige Assignments als expired und setzt Cooldown je (channel, video).';

    public function __construct(private AssignmentExpirer $expirer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cooldownDays = (int)$this->option('cooldown-days');
        if (0 === $cooldownDays) {
            $cooldownDays = (int)Cfg::get('assign_expire_cooldown_days');
        }
        $expiredCount = $this->expirer->expire($cooldownDays);
        $this->info("Expired: {$expiredCount}");
        return self::SUCCESS;
    }
}
