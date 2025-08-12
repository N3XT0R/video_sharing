<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Dropbox;

use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoRefreshTokenProviderTest extends TestCase
{
    private CacheRepository $cache;
    private string $tokenUrl = 'https://api.dropboxapi.com/oauth2/token';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        $this->cache = Cache::store(); // default array store
        $this->cache->clear();

        Http::preventStrayRequests();
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
                $saved = $rt; // capture rotated RT without touching DB
            }
        );

        $token1 = $provider->getToken();

        $this->assertSame('ACCESS_X', $token1);
        $this->assertSame('RT_NEW', $saved);
        $this->assertSame('RT_NEW', $provider->getRefreshToken());

        // Second call hits cache (no extra HTTP)
        Http::fake([$this->tokenUrl => Http::response(['access_token' => 'SHOULD_NOT_BE_USED'], 200)]);
        $token2 = $provider->getToken();
        $this->assertSame('ACCESS_X', $token2);

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
        $this->assertNull($saved, 'Should not persist when refresh token did not rotate');
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

    public function testSendsCorrectFormPayloadAndHonorsTtlBufferAndCacheExpiry(): void
    {
        // Freeze time so we can simulate cache expiry
        Carbon::setTestNow('2025-08-12 10:00:00');

        // First response: short lifetime (120s) -> effective TTL = 60s (buffer of 60)
        Http::fake([
            $this->tokenUrl => Http::response([
                'access_token' => 'ACCESS_1',
                'expires_in' => 120,
                'refresh_token' => 'RT1',
            ], 200),
        ]);

        $saved = null;
        $provider = new AutoRefreshTokenProvider(
            clientId: 'CID',
            clientSecret: 'CSECRET',
            refreshToken: 'RT0',
            cache: $this->cache,
            tokenUrl: $this->tokenUrl,
            persistRefreshToken: function (string $rt) use (&$saved) {
                $saved = $rt;
            }
        );

        $tokenA = $provider->getToken();
        $this->assertSame('ACCESS_1', $tokenA);
        $this->assertSame('RT1', $saved);

        // Assert form payload of the first request
        Http::assertSent(function ($request) {
            $data = $request->data();
            return $request->url() === 'https://api.dropboxapi.com/oauth2/token'
                && ($data['grant_type'] ?? null) === 'refresh_token'
                && ($data['refresh_token'] ?? null) === 'RT0'
                && ($data['client_id'] ?? null) === 'CID'
                && ($data['client_secret'] ?? null) === 'CSECRET';
        });

        // Advance time beyond effective TTL (60s buffer applied) -> cache should expire
        Carbon::setTestNow('2025-08-12 10:01:01');

        // Second response after expiry
        Http::fake([
            $this->tokenUrl => Http::response([
                'access_token' => 'ACCESS_2',
                'expires_in' => 3600,
                'refresh_token' => 'RT1', // unchanged this time
            ], 200),
        ]);

        $tokenB = $provider->getToken();
        $this->assertSame('ACCESS_2', $tokenB);

        // Two total HTTP calls (one per refresh)
        Http::assertSentCount(2);

        // Cleanup test time
        Carbon::setTestNow();
    }
}
