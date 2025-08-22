<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReminderNotifier;
use Illuminate\Console\Command;

class NotifyReminders extends Command
{
    protected $signature = 'notify:reminders {--days=1}';
    protected $description = 'Sendet Erinnerungen vor Ablauf der Links.';

    public function __construct(private ReminderNotifier $notifier)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int)$this->option('days');
        $result = $this->notifier->notify($days);
        $this->info("Reminder emails queued: {$result['sent']}");
        return self::SUCCESS;
    }
}
