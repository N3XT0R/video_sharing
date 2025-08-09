<?php

namespace App\Services\Dropbox;

use App\Models\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Dropbox\TokenProvider;

class AutoRefreshTokenProvider implements TokenProvider
{
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private ?string $refreshToken,
        private string $cacheKey = 'dropbox.access_token'
    ) {
    }

    public function getToken(): string
    {
        if ($token = Cache::get($this->cacheKey)) {
            return $token;
        }

        if (!$this->refreshToken) {
            throw new \RuntimeException('Dropbox: Kein Refresh Token konfiguriert.');
        }

        $scopes = config('services.dropbox.scopes', 'files.content.write files.content.read');

        $resp = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ])->throw()->json();

        $token = $resp['access_token'] ?? null;
        $ttl = max(60, (int)($resp['expires_in'] ?? 0) - 60); // 1 Min Puffer

        if (!$token) {
            throw new \RuntimeException('Dropbox: Kein access_token in Token-Response.');
        }

        if (!empty($resp['refresh_token']) && $resp['refresh_token'] !== $this->refreshToken) {
            $this->refreshToken = $resp['refresh_token'];
            Config::query()->updateOrCreate(
                ['key' => 'dropbox_refresh_token'],
                ['value' => $this->refreshToken]
            );
        }

        Cache::put($this->cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }
}
