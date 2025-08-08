<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{Assignment, Batch, ChannelVideoBlock};
use Illuminate\Console\Command;

class AssignExpire extends Command
{
    protected $signature = 'assign:expire {--cooldown-days=14}';
    protected $description = 'Markiert überfällige Assignments als expired und setzt Cooldown je (channel, video).';

    public function handle(): int
    {
        $cooldownDays = (int)$this->option('cooldown-days');
        $batch = Batch::query()->create([
            'type' => 'assign',
            'started_at' => now()
        ]); // protokolliere als Teil eines Assign-Zyklus
        $cnt = 0;
        
        Assignment::query()->where('status', 'notified')
            ->where('expires_at', '<', now())
            ->chunkById(500, function ($items) use (&$cnt, $cooldownDays) {
                foreach ($items as $a) {
                    $a->update(['status' => 'expired']);
                    ChannelVideoBlock::query()->updateOrCreate(
                        ['channel_id' => $a->channel_id, 'video_id' => $a->video_id],
                        ['until' => now()->addDays($cooldownDays)]
                    );
                    $cnt++;
                }
            });
        $batch->update(['finished_at' => now(), 'stats' => ['expired' => $cnt]]);
        $this->info("Expired: $cnt");
        return 0;
    }
}