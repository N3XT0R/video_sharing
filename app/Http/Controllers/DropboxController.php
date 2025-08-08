<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DropboxController extends Controller
{
    /**
     * Leitet zum Dropbox-OAuth – mit CSRF/Replay-Schutz via state.
     */
    public function connect(Request $request)
    {
        $appKey = config('services.dropbox.client_id', env('DROPBOX_CLIENT_ID'));
        $scopes = config('services.dropbox.scopes', 'files.content.write files.content.read');
        $redirect = route('dropbox.callback');

        if (!$appKey) {
            abort(Response::HTTP_PRECONDITION_FAILED,
                'Fehlende Konfiguration: services.dropbox.client_id / DROPBOX_CLIENT_ID');
        }

        // CSRF/Replay-Schutz
        $state = Str::random(40);
        $request->session()->put('dropbox_oauth_state', $state);

        $params = http_build_query([
            'client_id' => $appKey,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'token_access_type' => 'offline', // liefert einen refresh_token
            'scope' => $scopes,
            'state' => $state,
        ]);

        return redirect("https://www.dropbox.com/oauth2/authorize?{$params}");
    }

    /**
     * Empfängt den Code, tauscht ihn gegen Tokens und speichert den refresh_token in der Datenbank.
     */
    public function callback(Request $request)
    {
        // State prüfen
        if (!$request->filled('code')) {
            abort(Response::HTTP_BAD_REQUEST, 'Kein Code erhalten');
        }

        $appKey = config('services.dropbox.client_id', env('DROPBOX_CLIENT_ID'));
        $appSecret = config('services.dropbox.client_secret', env('DROPBOX_CLIENT_SECRET'));
        $redirect = route('dropbox.callback');

        if (!$appKey || !$appSecret) {
            abort(Response::HTTP_PRECONDITION_FAILED, 'Fehlende Konfiguration: DROPBOX_CLIENT_ID/SECRET');
        }

        // Code gegen Token tauschen
        $resp = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => (string)$request->string('code'),
            'client_id' => $appKey,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirect,
        ])->throw()->json();

        $refreshToken = $resp['refresh_token'] ?? null;
        $accessToken = $resp['access_token'] ?? null;
        $expiresIn = $resp['expires_in'] ?? null;

        if ($refreshToken) {
            Config::query()->updateOrCreate(
                ['key' => 'dropbox_refresh_token'],
                ['value' => $refreshToken]
            );
        }

        return response()->json([
            'status' => 'ok',
            'message' => $refreshToken
                ? 'Refresh Token gespeichert. App neu laden.'
                : 'Kein refresh_token erhalten (prüfe token_access_type=offline & Scopes).',
            'access_token_preview' => $accessToken ? substr($accessToken, 0, 12).'…' : null,
            'access_token_expires_in' => $expiresIn,
            'redirect_uri_used' => $redirect,
            'note' => 'Stelle sicher, dass die Redirect-URI exakt in der Dropbox-App hinterlegt ist.',
            'raw' => config('app.debug') ? $resp : null, // nur im Debug sinnvoll
        ]);
    }
}
