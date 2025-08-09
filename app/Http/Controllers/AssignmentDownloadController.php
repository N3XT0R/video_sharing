<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Assignment, Download};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\StreamInterface;
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
        $stream = $disk->readStream($filePath);

        if ($stream instanceof StreamInterface) {
            $stream = $stream->detach();
        }

        if (is_string($stream)) {
            $temp = fopen('php://temp', 'wb+');
            fwrite($temp, $stream);
            rewind($temp);
            $stream = $temp;
        }

        abort_unless(is_resource($stream), 404);
        $size = $disk->size($filePath);

        // Einfache Ausgabe als Stream ohne fpassthru (stream_copy_to_stream funktioniert auch mit Dropbox)
        $response = new StreamedResponse(function () use ($stream) {
            $output = fopen('php://output', 'wb');
            if ($output !== false) {
                stream_copy_to_stream($stream, $output);
                fclose($output);
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => (string)$size,
            'Accept-Ranges' => 'bytes',
            'ETag' => $assignment->video->hash,
        ]);

        // Audit + Status (hier direkt auf picked_up setzen; alternativ erst nach vollständigem Transfer via Middleware/Hooks)
        $assignment->update(['status' => 'picked_up']);
        Download::query()->create([
            'assignment_id' => $assignment->id,
            'downloaded_at' => now(),
            'ip' => $req->ip(),
            'user_agent' => $req->userAgent(),
            'bytes_sent' => $size,
        ]);

        return $response;
    }
}