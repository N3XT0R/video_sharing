<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\{NotificationTypeEnum, StatusEnum};
use App\Mail\ReminderMail;
use App\Models\{Assignment, Channel, Notification};
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class ReminderNotifier
{
    public function __construct(private LinkService $linkService)
    {
    }

    /**
     * Send reminder emails for assignments expiring in given days.
     *
     * @return array{sent:int}
     */
    public function notify(int $days = 1): array
    {
        $start = now()->addDays($days)->startOfDay();
        $end = $start->copy()->endOfDay();

        $assignments = Assignment::query()
            ->where('status', StatusEnum::NOTIFIED->value)
            ->whereBetween('expires_at', [$start, $end])
            ->with('video.clips')
            ->get()
            ->groupBy('channel_id');

        $sent = 0;
        foreach ($assignments as $channelId => $items) {
            $channel = Channel::query()->find($channelId);
            if (null === $channel) {
                continue;
            }
            $this->notifyChannel($channel, $items);
            $sent++;
        }

        return ['sent' => $sent];
    }

    public function notifyChannel(Channel $channel, Collection $assignments): void
    {
        $first = $assignments->first();
        $batch = $first->batch;
        $expireDate = $first->expires_at;
        $offerUrl = $this->linkService->getOfferUrl($batch, $channel, $expireDate);

        $notification = Notification::query()->create([
            'channel_id' => $channel->getKey(),
            'type' => NotificationTypeEnum::REMINDER->value,
        ]);

        foreach ($assignments as $assignment) {
            $assignment->setAttribute('notification_id', $notification->getKey());
            $assignment->save();
        }

        Mail::to($channel->getAttribute('email'))->queue(
            new ReminderMail($channel, $offerUrl, $expireDate, $assignments)
        );
    }
}
