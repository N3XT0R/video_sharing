<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Dropbox;

use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests for AutoRefreshTokenProvider.
 *
 * - Uses array cache store (in-memory)
 * - Uses Http::fake() to avoid real HTTP calls
 * - Verifies caching, refresh flow, rotated refresh token persistence and error cases
 */
class AutoRefreshTokenProviderTest extends TestCase
{
    protected CacheRepository $cache;
    protected string $tokenUrl = 'https://api.dropboxapi.com/oauth2/token';

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure isolated in-memory cache
        config()->set('cache.default', 'array');
        $this->cache = Cache::store(); // default store
        $this->cache->clear();
        Http::preventStrayRequests(); // safety net
    }

    public function testReturnsCachedTokenWithoutHttpCall(): void
    {
        $this->cache->put('dropbox.access_token', 'CACHED123', now()->addMinutes(10));

        $provider = new AutoRefreshTokenProvider(
            clientId: 'id',
            clientSecret: 'secret',
            refreshToken: 'RT',
            cache: $this->cache,
            tokenUrl: $this->tokenUrl,
        );

        $token = $provider->getToken();

        $this->assertSame('CACHED123', $token);
        // No HTTP requests should have been made
        Http::assertNothingSent();
    }

    public function testFetchesTokenCachesItAndPersistsRotatedRefreshToken(): void
    {
        $saved = null;

        Http::fake([
            $this->tokenUrl => Http::response([
                'access_token' => 'ACCESS_X',
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'refresh_token' => 'RT_NEW',
            ], 200),
        ]);

        $provider = new AutoRefreshTokenProvider(
            clientId: 'id',
            clientSecret: 'secret',
            refreshToken: 'RT_OLD',
            cache: $this->cache,
            tokenUrl: $this->tokenUrl,
            cacheKey: 'dropbox.access_token',
            persistRefreshToken: function (string $rt) use (&$saved) {
                $saved = $rt; // emulate DB persistence without touching the DB
            }
        );

        // 1st call hits HTTP and caches token
        $token1 = $provider->getToken();
        $this->assertSame('ACCESS_X', $token1);
        $this->assertSame('RT_NEW', $saved);
        $this->assertSame('RT_NEW', $provider->getRefreshToken());

        // 2nd call must use cache; prepare a different HTTP response to ensure it is NOT used
        Http::fake([$this->tokenUrl => Http::response(['access_token' => 'SHOULD_NOT_BE_USED'], 200)]);
        $token2 = $provider->getToken();
        $this->assertSame('ACCESS_X', $token2);

        // Only one HTTP request total (from the first call)
        Http::assertSentCount(1);
    }

    public function testDoesNotPersistWhenRefreshTokenUnchanged(): void
    {
        $saved = null;

        Http::fake([
            $this->tokenUrl => Http::response([
                'access_token' => 'ACCESS_Y',
                'expires_in' => 1200,
                'refresh_token' => 'RT_SAME',
            ], 200),
        ]);

        $provider = new AutoRefreshTokenProvider(
            clientId: 'id',
            clientSecret: 'secret',
            refreshToken: 'RT_SAME',
            cache: $this->cache,
            tokenUrl: $this->tokenUrl,
            persistRefreshToken: function (string $rt) use (&$saved) {
                $saved = $rt;
            }
        );

        $token = $provider->getToken();
        $this->assertSame('ACCESS_Y', $token);
        $this->assertNull($saved, 'Refresh token should not be persisted when unchanged');
    }

    public function testThrowsWhenNoRefreshTokenIsConfigured(): void
    {
        $provider = new AutoRefreshTokenProvider(
            clientId: 'id',
            clientSecret: 'secret',
            refreshToken: null,
            cache: $this->cache,
            tokenUrl: $this->tokenUrl,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kein Refresh Token konfiguriert');

        $provider->getToken();
    }

    public function testThrowsWhenAccessTokenMissingInResponse(): void
    {
        Http::fake([$this->tokenUrl => Http::response(['expires_in' => 3600], 200)]);

        $provider = new AutoRefreshTokenProvider(
            clientId: 'id',
            clientSecret: 'secret',
            refreshToken: 'RT',
            cache: $this->cache,
            tokenUrl: $this->tokenUrl,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kein access_token');

        $provider->getToken();
    }
}
