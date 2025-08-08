<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Services\PreviewService;
use Illuminate\Console\Command;

class GeneratePreviews extends Command
{
    protected $signature = 'previews:generate';
    protected $description = 'Generate preview clips for queued or notified assignments.';

    public function __construct(private PreviewService $previews)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $assignments = Assignment::with('video.clips')
            ->whereIn('status', ['queued', 'notified'])
            ->get();

        $this->previews->setOutput($this->output);

        foreach ($assignments as $assignment) {
            $clip = $assignment->video->clips->first();
            if ($clip && $clip->start_sec !== null && $clip->end_sec !== null) {
                $this->previews->generate(
                    $assignment->video,
                    (int)$clip->start_sec,
                    (int)$clip->end_sec
                );
            }
        }

        return self::SUCCESS;
    }
}
