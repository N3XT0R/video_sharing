<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ChannelNotifier;
use Illuminate\Console\Command;

class NotifyChannels extends Command
{
    protected $signature = 'notify:channels {--ttl-hours=144}'; // 6 Tage
    protected $description = 'Sendet Mails pro Kanal mit signierten Download-Links für neue Assignments.';

    public function __construct(private ChannelNotifier $notifier)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $ttl = (int)$this->option('ttl-hours');
        $sent = $this->notifier->notify($ttl);
        $this->info("Emails queued for $sent channels");
        return 0;
    }
}