<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

final class RefreshDropboxTokenTest extends DatabaseTestCase
{
    /** Happy path: refresh via provider, cache gets access token, refresh token rotates in DB. */
    public function testRefreshesTokenCachesItAndPersistsRotatedRefreshToken(): void
    {
        // Seed DB so the provider finds a refresh token
        Config::query()->updateOrCreate(
            ['key' => 'dropbox_refresh_token'],
            ['value' => 'OLD_REFRESH_000']
        );

        // Use predictable in-memory cache
        config()->set('cache.default', 'array');

        // Provider config (URL is required; we fake all requests anyway)
        config()->set('services.dropbox.client_id', 'cid_x');
        config()->set('services.dropbox.client_secret', 'sec_y');
        config()->set('services.dropbox.token_url', 'https://api.dropboxapi.com/oauth2/token');

        // Fake ALL HTTP so no real network happens
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

        // Act: run the real command (uses the app's container binding)
        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox Token refreshed')
            ->assertExitCode(Command::SUCCESS);

        // Assert: token was cached on default store
        $this->assertSame('ACCESS_123', Cache::get('dropbox.access_token'));

        // Assert: rotated RT persisted
        $this->assertDatabaseHas('configs', [
            'key' => 'dropbox_refresh_token',
            'value' => 'NEW_REFRESH_456',
        ]);
    }

    /** Failure: no refresh token present -> provider throws, command returns FAILURE. */
    public function testFailsWhenNoRefreshTokenConfigured(): void
    {
        // Ensure there is no RT
        Config::query()->where('key', 'dropbox_refresh_token')->delete();

        config()->set('cache.default', 'array');
        config()->set('services.dropbox.client_id', 'cid_x');
        config()->set('services.dropbox.client_secret', 'sec_y');
        config()->set('services.dropbox.token_url', 'https://api.dropboxapi.com/oauth2/token');

        Http::preventStrayRequests(); // nothing should be called

        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox: Kein Refresh Token konfiguriert.')
            ->assertExitCode(Command::FAILURE);
    }
}
