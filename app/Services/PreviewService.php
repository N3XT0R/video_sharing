<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Storage;

class PreviewService
{
    /**
     * Generate a preview clip for the given video and return its public URL.
     * If generation fails, null is returned.
     */
    public function generate(Video $video, int $start, int $end): ?string
    {
        if ($start < 0 || $end <= $start) {
            return null;
        }

        $disk = Storage::disk($video->disk ?? 'local');
        if (! $disk->exists($video->path)) {
            return null;
        }

        $srcPath = $disk->path($video->path);
        $previewDisk = Storage::disk('public');

        $hash = md5($video->id . '_' . $start . '_' . $end);
        $previewPath = "previews/{$hash}.mp4";

        if (! $previewDisk->exists($previewPath)) {
            $tmpFile = sys_get_temp_dir() . "/preview_{$hash}.mp4";
            $duration = $end - $start;
            $cmd = sprintf(
                'ffmpeg -y -ss %d -i %s -t %d -an -vcodec libx264 -preset veryfast -crf 28 %s 2>&1',
                $start,
                escapeshellarg($srcPath),
                $duration,
                escapeshellarg($tmpFile)
            );
            exec($cmd, $out, $ret);
            if ($ret !== 0 || ! file_exists($tmpFile)) {
                return null;
            }
            $previewDisk->put($previewPath, fopen($tmpFile, 'rb'));
            @unlink($tmpFile);
        }

        return $previewDisk->url($previewPath);
    }
}
