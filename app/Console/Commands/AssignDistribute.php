<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{Assignment, Batch, Channel, ChannelVideoBlock, Video};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignDistribute extends Command
{
    protected $signature = 'assign:distribute {--quota=}';
    protected $description = 'Verteilt neue und expired Videos fair auf Kanäle (Round-Robin, gewichtet, Quota/Woche).';

    public function handle(): int
    {
        $batch = Batch::create(['type' => 'assign', 'started_at' => now()]);

        // Neue Videos seit letztem Assign-Finish ermitteln
        $last = Batch::where('type', 'assign')->whereNotNull('finished_at')->latest()->first();
        $newVideos = Video::when($last, fn($q) => $q->where('created_at', '>', $last->finished_at))
            ->orderBy('id')
            ->get();

        // Expired zurück in den Pool (einmalig pro Video)
        $expiredVideoIds = Assignment::where('status', 'expired')->pluck('video_id')->unique();
        $expiredVideos = Video::whereIn('id', $expiredVideoIds)->get();

        $poolVideos = $newVideos->concat($expiredVideos)->unique('id')->values();
        if ($poolVideos->isEmpty()) {
            $batch->update(['finished_at' => now(), 'stats' => ['assigned' => 0]]);
            $this->info('Nichts zu verteilen.');
            return 0;
        }

        // Kanal-Pool nach Gewicht
        $channels = Channel::orderBy('id')->get();
        if ($channels->isEmpty()) {
            $this->warn('Keine Kanäle konfiguriert.');
            return 0;
        }
        $pool = collect();
        foreach ($channels as $c) {
            $pool = $pool->merge(array_fill(0, max(1, (int)$c->weight), $c));
        }

        // Quota: optionale CLI-Override, sonst channel->weekly_quota
        $quota = $channels->mapWithKeys(fn($c) => [$c->id => (int)($this->option('quota') ?: $c->weekly_quota)]);

        $assigned = 0;
        $skipped = 0;
        foreach ($poolVideos as $v) {
            // blockierte Kanäle für dieses Video (Cooldown) ausschließen
            $blockedChannelIds = ChannelVideoBlock::where('video_id', $v->id)->where('until', '>',
                now())->pluck('channel_id')->all();

            // Finde den nächsten Kanal mit Rest-Quota, der nicht blockiert ist und noch kein Assignment zu (video,channel) hat
            $target = null;
            $rotations = 0;
            while ($rotations < $pool->count()) {
                $candidate = $pool->first();
                $pool->push($pool->shift()); // rotiere
                $rotations++;

                if ($quota[$candidate->id] <= 0) {
                    continue;
                }
                if (in_array($candidate->id, $blockedChannelIds, true)) {
                    continue;
                }
                $exists = Assignment::where('video_id', $v->id)->where('channel_id', $candidate->id)->exists();
                if ($exists) {
                    continue;
                }
                $target = $candidate;
                break;
            }

            if (!$target) {
                $skipped++;
                continue;
            }

            Assignment::create([
                'video_id' => $v->id,
                'channel_id' => $target->id,
                'batch_id' => $batch->id,
                'status' => 'queued',
                'attempts' => DB::raw('attempts'),
            ]);
            $quota[$target->id]--;
            $assigned++;

            // Wenn alle Quotas 0 sind → Abbruch
            if (collect($quota->all())->every(fn($q) => $q <= 0)) {
                break;
            }
        }

        $batch->update(['finished_at' => now(), 'stats' => ['assigned' => $assigned, 'skipped' => $skipped]]);
        $this->info("Assigned=$assigned, skipped=$skipped");
        return 0;
    }
}