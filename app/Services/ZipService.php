<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\DownloadStatusEnum;
use App\Enum\StatusEnum;
use App\Models\{Assignment, Batch, Channel, Download, Video};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ZipService
{
    public function __construct(private DownloadCacheService $cache)
    {
    }

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
        $jobId = $this->jobId($batch, $channel);
        $downloadName = $this->downloadName($batch, $channel);
        $tmpPath = $this->zipPath($jobId);

        $this->prepareDirectories();

        $zip = $this->createZipArchive($tmpPath, $items);

        $this->cache->setStatus($jobId, DownloadStatusEnum::PREPARING->value);
        $this->cache->setProgress($jobId, 0);

        $tmpFiles = $this->addAssignmentsToZip($zip, $jobId, $items, $ip, $userAgent);

        $this->finalizeZip($zip, $tmpFiles, $jobId, $tmpPath, $downloadName);

        return $tmpPath;
    }

    private function jobId(Batch $batch, Channel $channel): string
    {
        return $batch->getKey().'_'.$channel->getKey();
    }

    private function downloadName(Batch $batch, Channel $channel): string
    {
        return sprintf(
            'videos_%s_%s_selected.zip',
            $batch->getKey(),
            Str::slug((string)$channel->getAttribute('name')),
        );
    }

    private function zipPath(string $jobId): string
    {
        return "zips/{$jobId}.zip";
    }

    private function prepareDirectories(): void
    {
        Storage::makeDirectory('zips');
        Storage::makeDirectory('zips/tmp');
    }

    /**
     * @param  Collection<Assignment>  $items
     */
    private function createZipArchive(string $tmpPath, Collection $items): ZipArchive
    {
        $zip = new ZipArchive();
        $zip->open(Storage::path($tmpPath), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('info.csv', $this->buildInfoCsv($items));

        return $zip;
    }

    /**
     * @param  Collection<Assignment>  $items
     * @return array<int, string>  temporary files created during download
     */
    private function addAssignmentsToZip(
        ZipArchive $zip,
        string $jobId,
        Collection $items,
        string $ip,
        ?string $userAgent
    ): array {
        $tmpFiles = [];
        $total = max($items->count(), 1);
        $processed = 0;

        foreach ($items as $assignment) {
            $this->processAssignment($zip, $jobId, $assignment, $ip, $userAgent, $tmpFiles);

            $processed++;
            $this->updateProgress($jobId, $processed, $total);
        }

        return $tmpFiles;
    }

    /**
     * @param  array<int, string>  $tmpFiles
     */
    private function processAssignment(
        ZipArchive $zip,
        string $jobId,
        Assignment $assignment,
        string $ip,
        ?string $userAgent,
        array &$tmpFiles
    ): void {
        /** @var Video $video */
        $video = $assignment->video;
        $disk = $video->getDisk();
        $path = $video->getAttribute('path');

        if (!$disk->exists($path)) {
            return;
        }

        $nameInZip = $this->sanitizeName($video);
        $localPath = $this->localVideoPath($video, $disk->path($path), $jobId, $tmpFiles);

        if ($localPath === null) {
            return;
        }

        $this->cache->setStatus($jobId, DownloadStatusEnum::ADDING->value);
        $zip->addFile($localPath, $nameInZip);

        /**
         * @var AssignmentService $assigmentService
         */
        $assigmentService = app(AssignmentService::class);
        $assigmentService->markDownloaded($assignment, $ip, $userAgent);
    }

    /**
     * @param  array<int, string>  $tmpFiles
     */
    private function localVideoPath(Video $video, string $localDiskPath, string $jobId, array &$tmpFiles): ?string
    {
        if ($video->getAttribute('disk') !== 'dropbox') {
            return $localDiskPath;
        }

        $this->cache->setStatus($jobId, DownloadStatusEnum::DOWNLOADING->value);
        $disk = $video->getDisk();
        $path = $video->getAttribute('path');
        $stream = $disk->readStream($path);

        if (!is_resource($stream)) {
            return null;
        }

        $tmpFile = 'zips/tmp/'.Str::uuid()->toString();
        $tmpFiles[] = $tmpFile;
        $localPath = Storage::path($tmpFile);
        $localHandle = fopen($localPath, 'w+b');

        if ($localHandle === false) {
            fclose($stream);
            return null;
        }

        stream_copy_to_stream($stream, $localHandle);
        fclose($localHandle);
        fclose($stream);

        return $localPath;
    }

    private function sanitizeName(Video $video): string
    {
        $name = $video->getAttribute('original_name') ?: basename($video->getAttribute('path'));

        return preg_replace('/[\\\\\/:*?"<>|]+/', '_', $name);
    }

    private function markDownloaded(Assignment $assignment, string $ip, ?string $userAgent): void
    {
        $assignment->update(['status' => StatusEnum::PICKEDUP->value]);

        Download::query()->create([
            'assignment_id' => $assignment->getKey(),
            'downloaded_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent,
            'bytes_sent' => null,
        ]);
    }

    private function updateProgress(string $jobId, int $processed, int $total): void
    {
        $pct = (int)floor($processed * 100 / max($total, 1));
        $this->cache->setProgress($jobId, $pct);
    }

    /**
     * @param  array<int, string>  $tmpFiles
     */
    private function finalizeZip(
        ZipArchive $zip,
        array $tmpFiles,
        string $jobId,
        string $tmpPath,
        string $downloadName
    ): void {
        $this->cache->setStatus($jobId, DownloadStatusEnum::FINALIZING->value);
        $zip->close();

        foreach ($tmpFiles as $file) {
            Storage::delete($file);
        }

        $this->cache->setStatus($jobId, DownloadStatusEnum::READY->value);
        $this->cache->setFile($jobId, $tmpPath);
        $this->cache->setName($jobId, $downloadName);
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
}

