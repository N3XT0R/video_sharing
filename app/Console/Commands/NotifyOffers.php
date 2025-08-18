<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Cfg;
use App\Models\Batch;
use App\Services\OfferNotifier;
use Illuminate\Console\Command;
use RuntimeException;

class NotifyOffers extends Command
{
    protected $signature = 'notify:offers {--ttl-days=} {--assign-batch=}';
    protected $description = 'Sendet je Kanal einen Offer-Link (Übersicht + ZIP).';

    public function __construct(private OfferNotifier $notifier)
    {
        parent::__construct();
    }

    protected function configureUsingFluentDefinition(): void
    {
        parent::configureUsingFluentDefinition();
        $option = $this->getDefinition()->getOption('ttl-days');
        $option->setDefault(Cfg::get('expire_after_days', 'default', 6));
    }

    public function handle(): int
    {
        $exitCode = self::SUCCESS;
        $ttlDays = (int)$this->option('ttl-days');
        $assignBatchId = (int)$this->option('assign-batch');
        $assignBatch = $assignBatchId > 0 ? Batch::query()->whereKey($assignBatchId)->firstOrFail() : null;

        try {
            $result = $this->notifier->notify($ttlDays, $assignBatch);
            if ($result['sent'] === 0) {
                $this->info('Keine Kanäle mit neuen Angeboten.');
            } else {
                $this->info("Offer emails queued: {$result['sent']} (Assign-Batch #{$result['batchId']})");
            }
        } catch (RuntimeException $e) {
            $this->warn($e->getMessage());
            $exitCode = self::FAILURE;
        }
        return $exitCode;
    }
}

