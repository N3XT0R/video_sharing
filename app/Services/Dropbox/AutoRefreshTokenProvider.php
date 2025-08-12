<?php

declare(strict_types=1);

namespace App\Services\Dropbox;

use App\Models\Config;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Spatie\Dropbox\TokenProvider;

/**
 * Testable token provider:
 * - Cache repository is injected
 * - Token endpoint URL is injected
 * - Optional callback to persist a rotated refresh token (avoids DB in tests)
 */
final class AutoRefreshTokenProvider implements TokenProvider
{
    private string $clientId;
    private string $clientSecret;
    private ?string $refreshToken;
    private string $cacheKey;
    private string $tokenUrl;
    private CacheRepository $cache;

    /** @var null|callable(string $newRefreshToken):void */
    private $persistRefreshToken;

    public function __construct(
        string $clientId,
        string $clientSecret,
        ?string $refreshToken,
        CacheRepository $cache,
        string $tokenUrl,
        string $cacheKey = 'dropbox.access_token',
        ?callable $persistRefreshToken = null
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
        $this->cache = $cache;
        $this->tokenUrl = $tokenUrl;
        $this->cacheKey = $cacheKey;
        $this->persistRefreshToken = $persistRefreshToken ??
            fn(string $rt) => Config::query()->updateOrCreate(
                ['key' => 'dropbox_refresh_token'],
                ['value' => $rt]
            );
    }

    /** Retrieve a valid access token, using cache and refreshing via OAuth if needed. */
    public function getToken(): string
    {
        if ($token = $this->cache->get($this->cacheKey)) {
            return (string)$token;
        }

        if (!$this->refreshToken) {
            throw new \RuntimeException('Dropbox: Kein Refresh Token konfiguriert.');
        }

        $resp = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ])->throw()->json();

        $token = $resp['access_token'] ?? null;
        if (!$token) {
            throw new \RuntimeException('Dropbox: Kein access_token in Token-Response.');
        }

        // Apply a 60s safety buffer
        $ttl = max(60, (int)($resp['expires_in'] ?? 0) - 60);
        $this->cache->put($this->cacheKey, $token, now()->addSeconds($ttl));

        // Persist rotated refresh token if Dropbox returned a new one
        $newRt = $resp['refresh_token'] ?? null;
        if (is_string($newRt) && $newRt !== '' && $newRt !== $this->refreshToken) {
            $this->refreshToken = $newRt;

            if (is_callable($this->persistRefreshToken)) {
                ($this->persistRefreshToken)($newRt);
            } else {
                // Fallback: persist via Config model (kept for BC)
                Config::query()->updateOrCreate(
                    ['key' => 'dropbox_refresh_token'],
                    ['value' => $newRt]
                );
            }
        }

        return $token;
    }

    /** Helper for tests: clear cached token so the next call forces a refresh. */
    public function clearCachedToken(): void
    {
        $this->cache->forget($this->cacheKey);
    }

    /** Helper for tests/inspection. */
    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }
}
