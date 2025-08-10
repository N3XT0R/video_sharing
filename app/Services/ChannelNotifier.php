<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\StatusEnum;
use App\Mail\ChannelAssignmentMail;
use App\Models\{Assignment, Batch};
use Illuminate\Support\Facades\Mail;

class ChannelNotifier
{
    public function __construct(private AssignmentService $assignmentService)
    {
    }

    /**
     * Notify channels about pending assignments.
     */
    public function notify(int $ttlHours): int
    {
        $batch = Batch::query()->create(['type' => 'notify', 'started_at' => now()]);

        $groups = Assignment::query()->where('status', StatusEnum::QUEUED->value)
            ->with(['channel', 'video'])
            ->get()
            ->groupBy('channel_id');

        $sent = 0;
        foreach ($groups as $items) {
            $links = [];
            foreach ($items as $a) {
                $url = $this->assignmentService->prepareDownload($a, $ttlHours);
                $links[] = [
                    'id' => $a->getKey(),
                    'hash' => $a->video->hash,
                    'bytes' => $a->video->bytes,
                    'ext' => $a->video->ext,
                    'url' => $url,
                ];
            }
            Mail::to($items->first()->channel->email)->queue(
                new ChannelAssignmentMail($items->first()->channel, $links)
            );
            $sent++;
        }

        $batch->update(['finished_at' => now(), 'stats' => ['emails' => $sent]]);
        return $sent;
    }
}

