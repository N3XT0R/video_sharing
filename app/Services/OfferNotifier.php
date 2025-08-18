<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\StatusEnum;
use App\Mail\NewOfferMail;
use App\Models\{Assignment, Batch, Channel};
use Carbon\Carbon;
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
    public function notify(int $ttlDays, ?Batch $assignBatch = null): array
    {
        $expireDate = now()->addDays($ttlDays);
        if (null === $assignBatch) {
            $assignBatch = $this->batchService->getLatestAssignBatch();
        }

        $channelIds = Assignment::query()->where('batch_id', $assignBatch->getKey())
            ->whereIn('status', StatusEnum::getReadyStatus())
            ->pluck('channel_id')->unique()->values();

        if ($channelIds->isEmpty()) {
            return ['sent' => 0, 'batchId' => $assignBatch->getKey()];
        }

        $sent = 0;

        $channels = Channel::query()->whereIn('id', $channelIds)->get();
        foreach ($channels as $channel) {
            $this->notifyChannel($channel, $assignBatch, $expireDate);
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

    public function notifyChannel(Channel $channel, Batch $assignBatch, Carbon $expireDate): void
    {
        $offerUrl = $this->linkService->getOfferUrl($assignBatch, $channel, $expireDate);
        $unusedUrl = $this->linkService->getUnusedUrl($assignBatch, $channel, $expireDate);


        $assignments = Assignment::query()->where('batch_id', $assignBatch->getKey())->get();
        /**
         * @var Assignment $assignment
         */
        foreach ($assignments as $assignment) {
            $assignment->setNotified();
            $assignment->setExpiresAt();
            $assignment->save();
        }


        Mail::to($channel->getAttribute('email'))->queue(
            new NewOfferMail($assignBatch, $channel, $offerUrl, $expireDate, $unusedUrl)
        );
    }
}

