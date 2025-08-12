<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Config;
use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

/**
 * Feature tests for the "dropbox:refresh-token" console command.
 * We ensure the container uses a fresh provider instance seeded with a DB refresh token.
 */
final class RefreshDropboxTokenTest extends DatabaseTestCase
{
    public function testRefreshesTokenCachesItAndPersistsRotatedRefreshToken(): void
    {
        // Seed DB with an existing refresh token (what your app expects)
        Config::query()->updateOrCreate(
            ['key' => 'dropbox_refresh_token'],
            ['value' => 'OLD_REFRESH_000']
        );

        // Make cache predictable and use the same store for provider + assertions
        config()->set('cache.default', 'array');
        /** @var CacheRepository $cache */
        $cache = Cache::store('array');

        // Minimal Dropbox service config; URL is irrelevant because we fake '*'
        config()->set('services.dropbox.client_id', 'cid_x');
        config()->set('services.dropbox.client_secret', 'sec_y');

        // Fake ALL outbound HTTP so no real network is hit
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response([
                'access_token' => 'ACCESS_123',
                'expires_in' => 14400,
                'refresh_token' => 'NEW_REFRESH_456',
                'token_type' => 'bearer',
                'scope' => 'files.content.write files.content.read',
            ], 200),
        ]);

        // IMPORTANT: override any pre-bound singleton with a fresh instance that has the DB RT
        $this->app->forgetInstance(AutoRefreshTokenProvider::class);
        $provider = new AutoRefreshTokenProvider(
            clientId: (string)config('services.dropbox.client_id'),
            clientSecret: (string)config('services.dropbox.client_secret'),
            refreshToken: 'OLD_REFRESH_000',   // same as in DB
            cache: $cache,
            cacheKey: 'dropbox.access_token'
        );
        $this->app->instance(AutoRefreshTokenProvider::class, $provider);

        // Run command
        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox Token refreshed')
            ->assertExitCode(Command::SUCCESS);

        // Assert: token cached on the SAME store instance we injected
        $this->assertSame('ACCESS_123', $cache->get('dropbox.access_token'));

        // Assert: rotated refresh token persisted
        $this->assertDatabaseHas('configs', [
            'key' => 'dropbox_refresh_token',
            'value' => 'NEW_REFRESH_456',
        ]);
    }

    public function testFailsWhenNoRefreshTokenConfigured(): void
    {
        // Ensure there is no refresh token in DB
        Config::query()->where('key', 'dropbox_refresh_token')->delete();

        config()->set('cache.default', 'array');
        /** @var CacheRepository $cache */
        $cache = Cache::store('array');

        // No HTTP should be made; prevent stray
        Http::preventStrayRequests();

        // Override singleton with an instance that has no RT (mirrors app behavior)
        $this->app->forgetInstance(AutoRefreshTokenProvider::class);
        $this->app->instance(AutoRefreshTokenProvider::class, new AutoRefreshTokenProvider(
            clientId: 'cid_x',
            clientSecret: 'sec_y',
            refreshToken: null,
            cache: $cache,
            cacheKey: 'dropbox.access_token'
        ));

        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox: Kein Refresh Token konfiguriert.')
            ->assertExitCode(Command::FAILURE);
    }
}
