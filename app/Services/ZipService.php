<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\StatusEnum;
use App\Models\{Assignment, Batch, Channel, Download, Video};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ZipService
{
    /**
     * @param  Batch  $batch
     * @param  Channel  $channel
     * @param  Collection<Assignment>  $items
     * @param  string  $ip
     * @param  string|null  $userAgent
     * @return string
     */
    public function build(Batch $batch, Channel $channel, Collection $items, string $ip, ?string $userAgent): string
    {
        $batchId = $batch->getKey();
        $name = $channel->getAttribute('name');
        $jobId = $batchId.'_'.$channel->getKey();
        $downloadName = sprintf(
            'videos_%s_%s_selected.zip',
            $batchId,
            Str::slug($name)
        );
        $tmpPath = "zips/{$jobId}.zip";

        // Ensure working directories exist
        Storage::makeDirectory('zips');
        Storage::makeDirectory('zips/tmp');

        $zip = new ZipArchive();
        $absPath = Storage::path($tmpPath);
        $zip->open($absPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('info.csv', $this->buildInfoCsv($items));

        $total = max($items->count(), 1);
        $i = 0;
        $tmpFiles = [];

        Cache::put($this->key($jobId, 'status'), 'preparing', 600);
        Cache::put($this->key($jobId, 'progress'), 0, 600);

        foreach ($items as $assignment) {
            /**
             * @var Video $video
             */
            $video = $assignment->video;
            $disk = $video->getDisk();
            $path = $video->getAttribute('path');

            if ($disk->exists($path)) {
                $nameInZip = $video->getAttribute('original_name') ?: basename($path);
                $nameInZip = preg_replace('/[\\\\\/:*?"<>|]+/', '_', $nameInZip);

                if ($video->getAttribute('disk') === 'dropbox') {
                    // Stream file from Dropbox to temporary local storage
                    Cache::put($this->key($jobId, 'status'), 'downloading', 600);
                    $stream = $disk->readStream($path);
                    if (is_resource($stream)) {
                        $tmpFile = 'zips/tmp/'.Str::uuid()->toString();
                        $tmpFiles[] = $tmpFile;
                        $localPath = Storage::path($tmpFile);
                        $localHandle = fopen($localPath, 'w+b');
                        if ($localHandle !== false) {
                            stream_copy_to_stream($stream, $localHandle);
                            fclose($localHandle);
                        }
                        fclose($stream);

                        Cache::put($this->key($jobId, 'status'), 'adding', 600);
                        $zip->addFile($localPath, $nameInZip);
                    }
                } else {
                    Cache::put($this->key($jobId, 'status'), 'adding', 600);
                    $zip->addFile($disk->path($path), $nameInZip);
                }

                $assignment->update(['status' => StatusEnum::PICKEDUP->value]);

                Download::query()->create([
                    'assignment_id' => $assignment->getKey(),
                    'downloaded_at' => now(),
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'bytes_sent' => null,
                ]);
            }

            $i++;
            $pct = (int)floor($i * 100 / $total);
            Cache::put($this->key($jobId, 'progress'), $pct, 600);
        }

        Cache::put($this->key($jobId, 'status'), 'finalizing', 600);
        $zip->close();

        // Clean up temporary files after the ZIP is created
        foreach ($tmpFiles as $tmpFile) {
            Storage::delete($tmpFile);
        }

        Cache::put($this->key($jobId, 'status'), 'ready', 600);
        Cache::put($this->key($jobId, 'file'), $tmpPath, 600);
        Cache::put($this->key($jobId, 'name'), $downloadName, 600);

        return $tmpPath;
    }

    /**
     * @param  Collection<Assignment>  $items
     */
    private function buildInfoCsv(Collection $items): string
    {
        $rows = [];
        $rows[] = ['filename', 'hash', 'size_mb', 'start', 'end', 'note', 'bundle', 'role', 'submitted_by'];

        foreach ($items as $assignment) {
            $video = $assignment->video;
            $clips = $video->clips ?? collect();

            if ($clips->isEmpty()) {
                $rows[] = [
                    $video->original_name ?: basename($video->path),
                    $video->hash,
                    number_format(($video->bytes ?? 0) / 1048576, 1, '.', ''),
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                ];
            } else {
                foreach ($clips as $clip) {
                    $rows[] = [
                        $video->original_name ?: basename($video->path),
                        $video->hash,
                        number_format(($video->bytes ?? 0) / 1048576, 1, '.', ''),
                        isset($clip->start_sec) ? gmdate('i:s', (int)$clip->start_sec) : null,
                        isset($clip->end_sec) ? gmdate('i:s', (int)$clip->end_sec) : null,
                        $clip->note,
                        $clip->bundle_key,
                        $clip->role,
                        $clip->submitted_by,
                    ];
                }
            }
        }

        $fp = fopen('php://temp', 'w+');
        fwrite($fp, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fp, $row, ';');
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    public function key(string $jobId, string $suffix): string
    {
        return "zipjob:{$jobId}:{$suffix}";
    }
}