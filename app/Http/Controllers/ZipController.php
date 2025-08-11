<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\BuildZipJob;
use App\Models\Batch;
use App\Models\Channel;
use App\Services\AssignmentService;
use Illuminate\Http\Request;
use App\Services\DownloadCacheService;
use Illuminate\Support\Facades\Storage;

class ZipController extends Controller
{
    public function __construct(
        private AssignmentService $assignments,
        private DownloadCacheService $cache
    ) {
    }

    // POST /zips/{batch}/{channel} -> Starts Job
    public function start(Request $req, Batch $batch, Channel $channel)
    {
        $validated = $req->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
        ]);

        $batchId = $batch->getKey();
        $jobId = $batchId.'_'.$channel->getKey();

        $ids = collect($validated['assignment_ids'])
            ->filter(fn($v) => ctype_digit((string)$v))
            ->map('intval')
            ->values();

        $items = $this->assignments->fetchForZip($batch, $channel, $ids);

        if ($items->isEmpty()) {
            return response()->json(['error' => 'Die Auswahl ist nicht mehr verfügbar.'], 422);
        }

        // initialer Status
        $this->cache->init($jobId);

        BuildZipJob::dispatch($batchId, $channel->getKey(), $ids->all(), $req->ip(), $req->userAgent());

        return response()->json(['jobId' => $jobId, 'status' => 'queued']);
    }

    // GET /zips/{id}/progress ->  Polling for Frontend
    public function progress(string $id)
    {
        $status = $this->cache->getStatus($id);
        $progress = $this->cache->getProgress($id);
        $name = $status === 'ready' ? $this->cache->getName($id) : null;

        return response()->json(compact('status', 'progress', 'name'));
    }

    // GET /zips/{id}/download -> delivers zip
    public function download(string $id)
    {
        $path = $this->cache->getFile($id);
        $name = $this->cache->getName($id, "{$id}.zip");
        if (!$path || !Storage::exists($path)) {
            abort(404);
        }

        return response()->download(Storage::path($path), $name)->deleteFileAfterSend();
    }
}
