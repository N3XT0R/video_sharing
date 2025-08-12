<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Dropbox;

use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

class AutoRefreshTokenProviderTest extends DatabaseTestCase
{

    private CacheRepository $cache;
    private string $cacheKey = 'dropbox.access_token';
    private string $tokenUrl = 'https://api.dropboxapi.com/oauth2/token';

    protected function setUp(): void
    {
        parent::setUp();

        // Use in-memory cache for isolation
        config()->set('cache.default', 'array');
        $this->cache = Cache::store(); // array
        $this->cache->clear();

        // Set token URL used by the provider
        config()->set('services.dropbox.token_url', $this->tokenUrl);

        // Safety: block any real HTTP
        Http::preventStrayRequests();
    }

    public function testReturnsCachedTokenWithoutHttpCall(): void
    {
        // Arrange cached token
        $this->cache->put($this->cacheKey, 'CACHED_TOKEN', now()->addMinutes(10));

        $provider = new AutoRefreshTokenProvider(
            clientId: 'cid',
            clientSecret: 'secret',
            refreshToken: 'RT',
            cache: $this->cache,
            cacheKey: $this->cacheKey
        );

        // Act
        $token = $provider->getToken();

        // Assert
        $this->assertSame('CACHED_TOKEN', $token);
        Http::assertNothingSent();
    }

    public function testFetchesTokenCachesItPersistsRotatedRefreshTokenAndUpdatesInternalState(): void
    {
        // Arrange fake response with rotation and 1 hour lifetime
        Http::fake([
            $this->tokenUrl => Http::response([
                'access_token' => 'ACCESS_X',
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'refresh_token' => 'RT_NEW',
            ], 200),
        ]);

        $provider = new AutoRefreshTokenProvider(
            clientId: 'cid',
            clientSecret: 'secret',
            refreshToken: 'RT_OLD',
            cache: $this->cache,
            cacheKey: $this->cacheKey
        );

        // Act
        $token = $provider->getToken();

        // Assert access token
        $this->assertSame('ACCESS_X', $token);

        // Assert refresh token was rotated and persisted to DB
        $this->assertSame('RT_NEW', $provider->getRefreshToken());
        $this->assertDatabaseHas('configs', [
            'key' => 'dropbox_refresh_token',
            'value' => 'RT_NEW',
        ]);

        // Assert request payload correctness
        Http::assertSent(function ($req) {
            $data = $req->data();
            return $req->url() === 'https://api.dropboxapi.com/oauth2/token'
                && ($data['grant_type'] ?? null) === 'refresh_token'
                && ($data['refresh_token'] ?? null) === 'RT_OLD'
                && ($data['client_id'] ?? null) === 'cid'
                && ($data['client_secret'] ?? null) === 'secret';
        });

        Http::assertSentCount(1);
    }

    public function testTtlBufferCausesCacheExpiryAndSecondRefreshAfterEffectiveTtl(): void
    {
        // Freeze time for predictable cache lifetime
        Carbon::setTestNow('2025-08-12 10:00:00');

        // First response: expires_in=120s -> effective TTL = 60s (minus 60s buffer)
        // Second response used after cache expiry
        Http::fakeSequence()
            ->push([
                'access_token' => 'A1',
                'expires_in' => 120,
                'refresh_token' => 'RT1',
            ], 200)
            ->push([
                'access_token' => 'A2',
                'expires_in' => 3600,
                'refresh_token' => 'RT1', // unchanged
            ], 200);

        $provider = new AutoRefreshTokenProvider(
            clientId: 'cid',
            clientSecret: 'secret',
            refreshToken: 'RT0',
            cache: $this->cache,
            cacheKey: $this->cacheKey
        );

        // First refresh: caches A1 and persists RT1
        $this->assertSame('A1', $provider->getToken());
        $this->assertDatabaseHas('configs', ['key' => 'dropbox_refresh_token', 'value' => 'RT1']);

        // Within effective TTL (<= 60s) -> still A1 from cache
        Carbon::setTestNow('2025-08-12 10:00:59');
        $this->assertSame('A1', $provider->getToken());

        // After effective TTL -> triggers second HTTP call -> A2
        Carbon::setTestNow('2025-08-12 10:01:01');
        $this->assertSame('A2', $provider->getToken());

        Http::assertSentCount(2);

        Carbon::setTestNow(); // cleanup
    }

    public function testDoesNotPersistWhenRefreshTokenUnchanged(): void
    {
        Http::fake([
            $this->tokenUrl => Http::response([
                'access_token' => 'ACCESS_Y',
                'expires_in' => 600,
                'refresh_token' => 'RT_SAME',
            ], 200),
        ]);

        $provider = new AutoRefreshTokenProvider(
            clientId: 'cid',
            clientSecret: 'secret',
            refreshToken: 'RT_SAME',
            cache: $this->cache,
            cacheKey: $this->cacheKey
        );

        $token = $provider->getToken();
        $this->assertSame('ACCESS_Y', $token);

        // No DB writes because RT didn't rotate
        $this->assertDatabaseCount('configs', 0);
    }

    public function testAccessorAndMutatorForRefreshTokenWorkAsExpected(): void
    {
        $provider = new AutoRefreshTokenProvider(
            clientId: 'cid',
            clientSecret: 'secret',
            refreshToken: null,
            cache: $this->cache,
            cacheKey: $this->cacheKey
        );

        // Initially no RT configured
        $this->assertFalse($provider->hasRefreshToken());
        $this->assertNull($provider->getRefreshToken());

        // After setting RT, hasRefreshToken() and getter must reflect it
        $provider->setRefreshToken('RTX');
        $this->assertTrue($provider->hasRefreshToken());
        $this->assertSame('RTX', $provider->getRefreshToken());
    }

    public function testThrowsWhenNoRefreshTokenIsConfigured(): void
    {
        $provider = new AutoRefreshTokenProvider(
            clientId: 'cid',
            clientSecret: 'secret',
            refreshToken: null,
            cache: $this->cache,
            cacheKey: $this->cacheKey
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kein Refresh Token konfiguriert');

        $provider->getToken();
    }

    public function testThrowsWhenAccessTokenMissingInResponse(): void
    {
        Http::fake([$this->tokenUrl => Http::response(['expires_in' => 3600], 200)]);

        $provider = new AutoRefreshTokenProvider(
            clientId: 'cid',
            clientSecret: 'secret',
            refreshToken: 'RT',
            cache: $this->cache,
            cacheKey: $this->cacheKey
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kein access_token');

        $provider->getToken();
    }
}
