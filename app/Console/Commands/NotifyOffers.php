<?php

namespace App\Console\Commands;

use App\Services\OfferNotifier;
use Illuminate\Console\Command;
use RuntimeException;

class NotifyOffers extends Command
{
    protected $signature = 'notify:offers {--ttl-days=6}';
    protected $description = 'Sendet je Kanal einen Offer-Link (Übersicht + ZIP).';

    public function __construct(private OfferNotifier $notifier)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $ttlDays = (int)$this->option('ttl-days');

        try {
            $result = $this->notifier->notify($ttlDays);
            if ($result['sent'] === 0) {
                $this->info('Keine Kanäle mit neuen Angeboten.');
            } else {
                $this->info("Offer emails queued: {$result['sent']} (Assign-Batch #{$result['batchId']})");
            }
        } catch (RuntimeException $e) {
            $this->warn($e->getMessage());
        }
        return 0;
    }
}
