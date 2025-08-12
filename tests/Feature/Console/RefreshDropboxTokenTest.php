<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

/**
 * Feature tests for the "dropbox:refresh-token" console command using the
 * container-bound AutoRefreshTokenProvider. We:
 *  - seed the configs table with an initial refresh token,
 *  - fake ALL outbound HTTP calls,
 *  - force the default cache to 'array' so we can assert the cached token.
 */
final class RefreshDropboxTokenTest extends DatabaseTestCase
{
    public function testRefreshesTokenCachesItAndPersistsRotatedRefreshToken(): void
    {
        // Arrange: ensure provider reads a refresh token from DB
        Config::query()->updateOrCreate(
            ['key' => 'dropbox_refresh_token'],
            ['value' => 'OLD_REFRESH_000']
        );

        // Make cache predictable for assertions
        config()->set('cache.default', 'array');

        // Minimal services config (provider reads from config())
        config()->set('services.dropbox.client_id', 'cid_x');
        config()->set('services.dropbox.client_secret', 'sec_y');
        // token_url egal — wir faken '*' unten

        // Fake ALL HTTP requests (no stray network)
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

        // Act
        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox Token refreshed')
            ->assertExitCode(Command::SUCCESS);

        // Assert: access token was cached on the default store
        $this->assertSame('ACCESS_123', Cache::get('dropbox.access_token'));

        // Assert: rotated refresh token persisted to DB
        $this->assertDatabaseHas('configs', [
            'key' => 'dropbox_refresh_token',
            'value' => 'NEW_REFRESH_456',
        ]);
    }

    public function testFailsWhenNoRefreshTokenConfigured(): void
    {
        // Arrange: ensure there is NO refresh token in DB
        Config::query()->where('key', 'dropbox_refresh_token')->delete();

        config()->set('cache.default', 'array');
        config()->set('services.dropbox.client_id', 'cid_x');
        config()->set('services.dropbox.client_secret', 'sec_y');

        Http::preventStrayRequests(); // nothing should be called

        // Act & Assert
        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox: Kein Refresh Token konfiguriert.')
            ->assertExitCode(Command::FAILURE);
    }
}
