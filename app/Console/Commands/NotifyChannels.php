<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\ChannelAssignmentMail;
use App\Models\{Assignment, Batch};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class NotifyChannels extends Command
{
    protected $signature = 'notify:channels {--ttl-hours=144}'; // 6 Tage
    protected $description = 'Sendet Mails pro Kanal mit signierten Download-Links für neue Assignments.';

    public function handle(): int
    {
        $ttl = (int)$this->option('ttl-hours');
        $batch = Batch::query()->create(['type' => 'notify', 'started_at' => now()]);

        $groups = Assignment::query()->where('status', 'queued')
            ->with(['channel', 'video'])
            ->get()
            ->groupBy('channel_id');

        $sent = 0;
        foreach ($groups as $channelId => $items) {
            $links = [];
            foreach ($items as $a) {
                $plain = Str::random(40);
                $a->download_token = hash('sha256', $plain);
                $a->expires_at = now()->addHours($ttl);
                $a->last_notified_at = now();

                $url = URL::temporarySignedRoute(
                    'assignments.download',
                    $a->expires_at,
                    ['assignment' => $a->id, 't' => $plain]
                );
                $links[] = [
                    'id' => $a->id,
                    'hash' => $a->video->hash,
                    'bytes' => $a->video->bytes,
                    'ext' => $a->video->ext,
                    'url' => $url,
                ];
                $a->status = 'notified';
                $a->attempts++;
                $a->save();
            }

            $mailable = new ChannelAssignmentMail($items->first()->channel, $links);
            Mail::to($items->first()->channel->email)->queue($mailable);
            $sent++;
        }

        $batch->update(['finished_at' => now(), 'stats' => ['emails' => $sent]]);
        $this->info("Emails queued for $sent channels");
        return 0;
    }
}