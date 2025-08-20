<?php

namespace App\Services\Dropbox;

use App\Models\Config;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Http;
use Spatie\Dropbox\TokenProvider;

class AutoRefreshTokenProvider implements TokenProvider
{
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private ?string $refreshToken,
        private Repository $cache,
        private string $cacheKey = 'dropbox.access_token',
        private string $expireCacheKey = 'dropbox.expire_at'
    ) {
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function hasRefreshToken(): bool
    {
        return !empty($this->getRefreshToken());
    }

    public function setRefreshToken(?string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    public function getToken(): string
    {
        if ($token = $this->cache->get($this->cacheKey)) {
            return $token;
        }

        if (!$this->hasRefreshToken()) {
            throw new \RuntimeException('Dropbox: Kein Refresh Token konfiguriert.');
        }

        $resp = $this->getTokenResponse();
        $token = $resp['access_token'] ?? null;
        $ttl = max(60, (int)($resp['expires_in'] ?? 0) - 60); // 1 Min Puffer
        $this->cache->forever($this->expireCacheKey, Carbon::now()->addSeconds($ttl));

        if (!$token) {
            throw new \RuntimeException('Dropbox: Kein access_token in Token-Response.');
        }

        if ($this->isValidRefreshToken($resp)) {
            $this->setRefreshToken($resp['refresh_token']);
            Config::query()->updateOrCreate(
                ['key' => 'dropbox_refresh_token'],
                ['value' => $this->getRefreshToken()]
            );
        }

        $this->cache->put($this->cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    protected function isValidRefreshToken(array $token): bool
    {
        return !empty($token['refresh_token']) && $token['refresh_token'] !== $this->getRefreshToken();
    }

    protected function getTokenResponse(): array
    {
        $tokenUrl = (string)config('services.dropbox.token_url');
        return Http::asForm()->post($tokenUrl, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->getRefreshToken(),
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ])->throw()->json();
    }
}
