<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\TypeEnum;
use App\Models\{Assignment, Batch, ChannelVideoBlock};

class AssignmentExpirer
{
    /**
     * Expire assignments that have passed their TTL and apply cooldown blocks.
     */
    public function expire(int $cooldownDays): int
    {
        $batch = Batch::query()->create(['type' => 'assign', 'started_at' => now()]);
        $cnt = 0;

        Assignment::query()->where('status', 'notified')
            ->where('expires_at', '<', now())
            ->chunkById(500, function ($items) use (&$cnt, $cooldownDays) {
                foreach ($items as $a) {
                    $a->update(['status' => TypeEnum::EXPIRED->value]);
                    ChannelVideoBlock::query()->updateOrCreate(
                        ['channel_id' => $a->channel_id, 'video_id' => $a->video_id],
                        ['until' => now()->addDays($cooldownDays)]
                    );
                    $cnt++;
                }
            });

        $batch->update(['finished_at' => now(), 'stats' => ['expired' => $cnt]]);
        return $cnt;
    }
}

