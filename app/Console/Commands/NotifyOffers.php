<?php

namespace App\Console\Commands;

use App\Mail\NewOfferMail;
use App\Models\{Assignment, Batch, Channel};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Mail, URL};

class NotifyOffers extends Command
{
    protected $signature = 'notify:offers {--ttl-days=6}';
    protected $description = 'Sendet je Kanal einen Offer-Link (Übersicht + ZIP).';

    public function handle(): int
    {
        $ttlDays = (int)$this->option('ttl-days');

        $assignBatch = Batch::query()->where('type', 'assign')->whereNotNull('finished_at')
            ->latest('id')->first();

        if (!$assignBatch) {
            $this->warn('Kein Assign-Batch gefunden.');
            return 0;
        }

        $channelIds = Assignment::query()->where('batch_id', $assignBatch->id)
            ->whereIn('status', ['queued', 'notified'])
            ->pluck('channel_id')->unique()->values();

        if ($channelIds->isEmpty()) {
            $this->info('Keine Kanäle mit neuen Angeboten.');
            return 0;
        }

        $sent = 0;
        foreach (Channel::query()->whereIn('id', $channelIds)->get() as $channel) {
            $offerUrl = URL::temporarySignedRoute(
                'offer.show',
                now()->addDays($ttlDays),
                ['batch' => $assignBatch->id, 'channel' => $channel->id]
            );

            Mail::to($channel->email)->queue(
                new NewOfferMail($assignBatch, $channel, $offerUrl, now()->addDays($ttlDays))
            );
            $sent++;
        }

        $notifyBatch = Batch::query()->create([
            'type' => 'notify',
            'started_at' => now(),
            'finished_at' => now(),
            'stats' => ['emails' => $sent]
        ]);
        $this->info("Offer emails queued: $sent (Assign-Batch #{$assignBatch->id})");
        return 0;
    }
}
