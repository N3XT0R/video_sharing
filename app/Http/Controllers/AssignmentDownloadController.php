<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Assignment, Download};
use GuzzleHttp\Psr7\StreamWrapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentDownloadController extends Controller
{
    public function download(Request $req, Assignment $assignment)
    {
        // 1) Link-/Status-Guards
        abort_unless($req->hasValidSignature(), 403);
        abort_if($assignment->status !== 'notified' || now()->gt($assignment->expires_at), 410);

        $token = (string)$req->query('t');
        $valid = hash_equals($assignment->download_token, hash('sha256', $token));
        abort_unless($valid, 403);

        // 2) Datei bestimmen
        $video = $assignment->video;
        $disk = Storage::disk($video->disk ?? 'local');
        $filePath = ltrim((string)$video->path, '/');
        abort_unless($disk->exists($filePath), 404);

        // 3) Stream öffnen
        $stream = $disk->readStream($filePath);

        // Manche Treiber könnten (theoretisch) ein StreamInterface liefern:
        if ($stream instanceof StreamInterface) {
            $stream = StreamWrapper::getResource($stream); // -> resource
        }

        abort_unless(is_resource($stream), 404);

        // 4) Metadaten/Headers
        $size = (int)$disk->size($filePath);
        $mime = $disk->mimeType($filePath) ?: 'video/mp4';
        $filename = basename($filePath);
        $disposition = 'attachment; filename="'.addslashes($filename).'"';

        // 5) Streamen
        $response = new StreamedResponse(function () use ($stream) {
            $out = fopen('php://output', 'wb');
            try {
                stream_copy_to_stream($stream, $out);
            } finally {
                if (is_resource($out)) {
                    fclose($out);
                }
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string)$size,
            'Content-Disposition' => $disposition,
            'Accept-Ranges' => 'bytes',
            'ETag' => $video->hash, // falls vorhanden
        ]);

        // 6) Audit + Status (bei Start des Downloads markieren)
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
