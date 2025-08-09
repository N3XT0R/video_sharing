<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Assignment, Download};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentDownloadController extends Controller
{
    public function download(Request $req, Assignment $assignment)
    {
        abort_unless($req->hasValidSignature(), 403);
        abort_if($assignment->status !== 'notified' || now()->gt($assignment->expires_at), 410);

        $token = (string)$req->query('t');
        $valid = hash_equals($assignment->download_token, hash('sha256', $token));
        abort_unless($valid, 403);

        $video = $assignment->video;
        $disk = Storage::disk($video->disk ?? 'local');
        $filePath = ltrim($video->path, '/');
        abort_unless($disk->exists($filePath), 404);
        $abs = $disk->path($filePath);
        $size = filesize($abs);

        // Einfacher Stream (Hinweis: Für echtes 206-Range-Handling kannst du eine dedizierte Stream-Klasse nutzen)
        $response = new StreamedResponse(function () use ($abs) {
            $fh = fopen($abs, 'rb');
            fpassthru($fh);
            fclose($fh);
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => (string)$size,
            'Accept-Ranges' => 'bytes',
            'ETag' => $assignment->video->hash,
        ]);

        // Audit + Status (hier direkt auf picked_up setzen; alternativ erst nach vollständigem Transfer via Middleware/Hooks)
        $assignment->update(['status' => 'picked_up']);
        Download::create([
            'assignment_id' => $assignment->id,
            'downloaded_at' => now(),
            'ip' => $req->ip(),
            'user_agent' => $req->userAgent(),
            'bytes_sent' => $size,
        ]);

        return $response;
    }
}