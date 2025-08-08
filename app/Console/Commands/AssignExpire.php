<?php
// app/Console/Commands/AssignExpire.php
namespace App\Console\Commands;

use App\Services\AssignmentExpirer;
use Illuminate\Console\Command;

class AssignExpire extends Command
{
    protected $signature = 'assign:expire {--cooldown-days=14}';
    protected $description = 'Markiert überfällige Assignments als expired und setzt Cooldown je (channel, video).';

    public function __construct(private AssignmentExpirer $expirer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cooldownDays = (int)$this->option('cooldown-days');
        $cnt = $this->expirer->expire($cooldownDays);
        $this->info("Expired: $cnt");
        return 0;
    }
}