<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\StatusEnum;
use App\Mail\NewOfferMail;
use App\Models\{Assignment, Batch, Channel};
use Illuminate\Support\Facades\{Mail};

class OfferNotifier
{

    public function __construct(private BatchService $batchService, private LinkService $linkService)
    {
    }

    /**
     * Notify channels about new offers and return stats.
     *
     * @return array{sent:int,batchId:int}
     */
    public function notify(int $ttlDays): array
    {
        $assignBatch = $this->batchService->getLatestAssignBatch();
        $channelIds = Assignment::query()->where('batch_id', $assignBatch->getKey())
            ->whereIn('status', StatusEnum::getReadyStatus())
            ->pluck('channel_id')->unique()->values();

        if ($channelIds->isEmpty()) {
            return ['sent' => 0, 'batchId' => $assignBatch->getKey()];
        }

        $sent = 0;
        foreach (Channel::query()->whereIn('id', $channelIds)->get() as $channel) {
            $offerUrl = $this->linkService->getOfferUrl($assignBatch, $channel, $ttlDays);
            $unusedUrl = $this->linkService->getUnusedUrl($assignBatch, $channel, $ttlDays);

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

