<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NewOfferMail;
use App\Models\{Assignment, Batch, Channel};
use Illuminate\Support\Facades\{Mail, URL};
use RuntimeException;

class OfferNotifier
{
    /**
     * Notify channels about new offers and return stats.
     *
     * @return array{sent:int,batchId:int}
     */
    public function notify(int $ttlDays): array
    {
        $assignBatch = Batch::query()->where('type', 'assign')->whereNotNull('finished_at')
            ->latest('id')->first();

        if (!$assignBatch) {
            throw new RuntimeException('Kein Assign-Batch gefunden.');
        }

        $channelIds = Assignment::query()->where('batch_id', $assignBatch->getKey())
            ->whereIn('status', ['queued', 'notified'])
            ->pluck('channel_id')->unique()->values();

        if ($channelIds->isEmpty()) {
            return ['sent' => 0, 'batchId' => $assignBatch->getKey()];
        }

        $sent = 0;
        foreach (Channel::query()->whereIn('id', $channelIds)->get() as $channel) {
            $offerUrl = URL::temporarySignedRoute(
                'offer.show',
                now()->addDays($ttlDays),
                ['batch' => $assignBatch->getKey(), 'channel' => $channel->getKey()]
            );

            $unusedUrl = URL::temporarySignedRoute(
                'offer.unused.show',
                now()->addDays($ttlDays),
                ['batch' => $assignBatch->getKey(), 'channel' => $channel->getKey()]
            );

            Mail::to($channel->email)->queue(
                new NewOfferMail($assignBatch, $channel, $offerUrl, now()->addDays($ttlDays), $unusedUrl)
            );
            $sent++;
        }

        Batch::query()->create([
            'type' => 'notify',
            'started_at' => now(),
            'finished_at' => now(),
            'stats' => ['emails' => $sent]
        ]);

        return ['sent' => $sent, 'batchId' => $assignBatch->getKey()];
    }
}

