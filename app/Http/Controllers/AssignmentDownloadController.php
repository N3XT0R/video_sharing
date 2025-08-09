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
        // Link-Signatur prüfen
        abort_unless($req->hasValidSignature(), 403);

        // Status und Ablauf prüfen
        abort_if($assignment->status !== 'notified' || now()->gt($assignment->expires_at), 410);

        // Token validieren
        $token = (string)$req->query('t');
        $valid = hash_equals($assignment->download_token, hash('sha256', $token));
        abort_unless($valid, 403);

        // Videodaten holen
        $video = $assignment->video;
        $diskName = $video->disk ?? 'local';
        $disk = Storage::disk($diskName);
        $filePath = $video->path;

        // Existenz prüfen
        abort_unless($disk->exists($filePath), 404);

        // Direkt als Stream öffnen (Dropbox liefert schon gültiges Stream-Handle)
        $stream = $disk->readStream($filePath);
        abort_unless(is_resource($stream), 404);

        $size = $disk->size($filePath);

        // Response als gestreamte Ausgabe
        $response = new StreamedResponse(function () use ($stream) {
            $output = fopen('php://output', 'wb');
            if ($output !== false) {
                stream_copy_to_stream($stream, $output);
                fclose($output);
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => (string)$size,
            'Accept-Ranges' => 'bytes',
            'ETag' => $video->hash,
        ]);

        // Audit + Status aktualisieren
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
