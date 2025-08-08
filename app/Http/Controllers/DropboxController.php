<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        // ... state-Check & Token-Abruf wie vorher ...

        $refreshToken = $resp['refresh_token'] ?? null;

        if ($refreshToken) {
            $envPath = base_path('.env');
            $envContent = File::get($envPath);
            $newLine = 'DROPBOX_REFRESH_TOKEN='.$refreshToken;

            if (preg_match('/^DROPBOX_REFRESH_TOKEN=.*/m', $envContent)) {
                $envContent = preg_replace('/^DROPBOX_REFRESH_TOKEN=.*/m', $newLine, $envContent);
            } else {
                $envContent .= PHP_EOL.$newLine;
            }

            File::put($envPath, $envContent);

            Artisan::call('config:clear');
            Artisan::call('config:cache');
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Refresh Token gespeichert. Bitte App einmal neu starten.',
            'refresh_token' => $refreshToken,
        ]);
    }
}
