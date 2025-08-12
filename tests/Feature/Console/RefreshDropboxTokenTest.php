<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

/**
 * Feature tests for the "dropbox:refresh-token" console command.
 *
 * We run the real AutoRefreshTokenProvider but fake HTTP, so no real network calls happen.
 * Assertions are based on console output, exit codes, cache contents and DB side-effects.
 */
final class RefreshDropboxTokenTest extends DatabaseTestCase
{
    /** Success: token fetched, cached, and rotated refresh_token persisted. */
    public function testRefreshesTokenCachesItAndPersistsRotatedRefreshToken(): void
    {
        // Arrange: isolate cache repository (array driver) for the provider
        /** @var CacheRepository $cache */
        $cache = Cache::store('array');

        // Provider config
        config()->set('services.dropbox.token_url', 'https://dropbox.test/oauth2/token');

        // Fake the Dropbox token endpoint response
        Http::preventStrayRequests();
        Http::fake([
            'https://dropbox.test/oauth2/token' => Http::response([
                'access_token' => 'ACCESS_123',
                'expires_in' => 14400,
                'refresh_token' => 'NEW_REFRESH_456',
                'token_type' => 'bearer',
                'scope' => 'files.content.write files.content.read',
            ], 200),
        ]);

        // Bind a real provider instance into the container so the command receives it
        $provider = new AutoRefreshTokenProvider(
            clientId: 'cid_x',
            clientSecret: 'sec_y',
            refreshToken: 'OLD_REFRESH_000',
            cache: $cache,
            cacheKey: 'dropbox.access_token'
        );
        $this->app->instance(AutoRefreshTokenProvider::class, $provider);

        // Act: run the command
        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox Token refreshed')
            ->assertExitCode(Command::SUCCESS);

        // Assert: access token was cached
        $this->assertSame('ACCESS_123', $cache->get('dropbox.access_token'));

        // Assert: rotated refresh token persisted to DB (configs table)
        $this->assertDatabaseHas('configs', [
            'key' => 'dropbox_refresh_token',
            'value' => 'NEW_REFRESH_456',
        ]);
    }

    /** Failure: no refresh token configured → command prints error and returns FAILURE. */
    public function testFailsWhenNoRefreshTokenConfigured(): void
    {
        // Arrange: isolate cache again
        /** @var CacheRepository $cache */
        $cache = Cache::store('array');

        // Ensure no HTTP calls are attempted
        Http::preventStrayRequests();

        // Bind provider with NULL refresh token
        $provider = new AutoRefreshTokenProvider(
            clientId: 'cid_x',
            clientSecret: 'sec_y',
            refreshToken: null,              // <- missing RT
            cache: $cache,
            cacheKey: 'dropbox.access_token'
        );
        $this->app->instance(AutoRefreshTokenProvider::class, $provider);

        // Act & Assert
        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox: Kein Refresh Token konfiguriert.')
            ->assertExitCode(Command::FAILURE);
    }
}
