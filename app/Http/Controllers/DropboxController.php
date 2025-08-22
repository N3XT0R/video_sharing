<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Config;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DropboxController extends Controller
{
    /**
     * Also redirects to Dropbox OAuth, with CSRF/replay protection via the state parameter.
     */
    public function connect(Request $request)
    {
        $authorize = (string)config('services.dropbox.authorize_url');
        $appKey = (string)config('services.dropbox.client_id');
        $redirect = route('dropbox.callback');

        if (empty($appKey)) {
            abort(Response::HTTP_PRECONDITION_FAILED,
                'Fehlende Konfiguration: services.dropbox.client_id');
        }

        // CSRF/Replay-Schutz
        $state = Str::random(40);
        $request->session()->put('dropbox_oauth_state', $state);

        $params = http_build_query([
            'client_id' => $appKey,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'scope' => 'files.content.write files.content.read',
            'state' => $state,
        ]);
        return redirect()->away($authorize."?{$params}");
    }

    /**
     * Receives the authorization code, exchanges it for tokens, and stores the refresh_token in the database.
     */
    public function callback(Request $request)
    {
        // check code
        if (!$request->filled('code')) {
            abort(Response::HTTP_BAD_REQUEST, 'Kein Code erhalten');
        }

        $tokenUrl = (string)config('services.dropbox.token_url');
        $appKey = (string)config('services.dropbox.client_id');
        $appSecret = (string)config('services.dropbox.client_secret');
        $redirect = route('dropbox.callback');

        if (empty($appKey) || empty($appSecret)) {
            abort(Response::HTTP_PRECONDITION_FAILED, 'Fehlende Konfiguration: DROPBOX_CLIENT_ID/SECRET');
        }

        // exchange code against token
        $resp = Http::asForm()->post($tokenUrl, [
            'grant_type' => 'authorization_code',
            'code' => (string)$request->string('code'),
            'client_id' => $appKey,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirect,
        ])->throw()->json();

        $refreshToken = $resp['refresh_token'] ?? null;
        /*
        $accessToken = $resp['access_token'] ?? null;
        */

        if ($refreshToken) {
            Log::debug('response', ['response' => $resp]);
            Cache::delete('dropbox.access_token');
            Config::query()->updateOrCreate(
                ['key' => 'dropbox_refresh_token'],
                ['value' => $refreshToken]
            );

            $expiresIn = $resp['expires_in'] ?? null;
            $ttl = max(60, (int)($expiresIn ?? 0) - 60);
            Cache::forever('dropbox.expire_at', Carbon::now()->addSeconds($ttl));
        }

        return response()->redirectToRoute('filament.admin.pages.dropbox-connect');
    }
}
