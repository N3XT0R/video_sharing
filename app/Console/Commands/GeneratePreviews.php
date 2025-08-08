<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\PreviewService;
use Illuminate\Console\Command;

class GeneratePreviews extends Command
{
    protected $signature = 'previews:generate';
    protected $description = 'Generate preview clips for videos without an existing preview.';

    public function __construct(private PreviewService $previews)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $videos = Video::with('clips')
            ->whereNull('preview_url')
            ->get();

        $this->previews->setOutput($this->output);

        foreach ($videos as $video) {
            $clip = $video->clips->first();
            if ($clip && $clip->start_sec !== null && $clip->end_sec !== null) {
                $url = $this->previews->generate(
                    $video,
                    (int)$clip->start_sec,
                    (int)$clip->end_sec
                );
                if ($url) {
                    $video->preview_url = $url;
                    $video->save();
                }
            }
        }

        return self::SUCCESS;
    }
}
