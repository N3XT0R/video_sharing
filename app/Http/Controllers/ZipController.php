<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\BuildZipJob;
use App\Models\Batch;
use App\Models\Channel;
use App\Services\AssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ZipController extends Controller
{
    public function __construct(private AssignmentService $assignments)
    {
    }

    // POST /zips/{batch}/{channel} -> Startet Job
    public function start(Request $req, Batch $batch, Channel $channel)
    {
        $validated = $req->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
        ]);

        $batchId = $batch->getKey();
        $jobId = $batchId.'_'.$channel->getAttribute('name');

        $ids = collect($validated['assignment_ids'])
            ->filter(fn($v) => ctype_digit((string)$v))
            ->map('intval')
            ->values();

        $items = $this->assignments->fetchForZip($batch, $channel, $ids);

        if ($items->isEmpty()) {
            return response()->json(['error' => 'Die Auswahl ist nicht mehr verfügbar.'], 422);
        }

        // initialer Status
        Cache::put("zipjob:{$jobId}:status", 'queued', 600);
        Cache::put("zipjob:{$jobId}:progress", 0, 600);

        BuildZipJob::dispatch($batchId, $channel->getKey(), $ids->all(), $req->ip(), $req->userAgent());

        return response()->json(['id' => $jobId]);
    }

    // GET /zips/{id}/progress ->  Polling fürs Frontend
    public function progress(string $id)
    {
        $status = Cache::get("zipjob:{$id}:status", 'unknown');
        $progress = (int)Cache::get("zipjob:{$id}:progress", 0);

        return response()->json(compact('status', 'progress'));
    }

    // GET /zips/{id}/download -> liefert die ZIP
    public function download(string $id)
    {
        $path = Cache::get("zipjob:{$id}:file");
        $name = Cache::get("zipjob:{$id}:name", "{$id}.zip");
        if (!$path || !Storage::exists($path)) {
            abort(404);
        }

        return response()->download(Storage::path($path), $name)->deleteFileAfterSend();
    }
}
