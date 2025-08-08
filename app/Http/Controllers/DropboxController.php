<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DropboxController extends Controller
{
    public function connect(Request $request)
    {
        // CSRF/Replay-Schutz für den OAuth-Redirect
        $state = Str::random(40);
        $request->session()->put('dropbox_oauth_state', $state);

        $redirectUri = route('dropbox.callback');

        $params = http_build_query([
            'client_id' => env('DROPBOX_CLIENT_ID'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'token_access_type' => 'offline', // wichtig für refresh_token
            'scope' => 'files.content.write files.content.read',
            'state' => $state,
        ]);

        return redirect("https://www.dropbox.com/oauth2/authorize?{$params}");
    }

    public function callback(Request $request)
    {
        // State prüfen
        $expected = $request->session()->pull('dropbox_oauth_state');
        if (!$expected || $request->string('state') !== $expected) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid state');
        }

        if (!$request->filled('code')) {
            abort(Response::HTTP_BAD_REQUEST, 'Kein Code erhalten');
        }

        $redirectUri = route('dropbox.callback');

        $resp = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $request->string('code'),
            'client_id' => env('DROPBOX_CLIENT_ID'),
            'client_secret' => env('DROPBOX_CLIENT_SECRET'),
            'redirect_uri' => $redirectUri,
        ])->throw()->json();

        // Den refresh_token kopierst du einmalig in deine .env:
        // DROPBOX_REFRESH_TOKEN=...
        return response()->json([
            'save_these_to_env' => [
                'DROPBOX_REFRESH_TOKEN' => $resp['refresh_token'] ?? null,
            ],
            'access_token_expires_in' => $resp['expires_in'] ?? null,
            'note' => 'refresh_token in .env speichern und danach AutoRefreshTokenProvider verwenden.',
            'full_response' => $resp, // nur zum Debuggen
        ]);
    }
}
