<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\RefreshDropboxToken;
use App\Models\Config;
use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

final class RefreshDropboxTokenTest extends DatabaseTestCase
{
    public function testRefreshesTokenCachesItAndPersistsRotatedRefreshToken(): void
    {
        // Seed DB with existing refresh token (what the app expects).
        Config::query()->updateOrCreate(
            ['key' => 'dropbox_refresh_token'],
            ['value' => 'OLD_REFRESH_000']
        );

        // Use array cache and get the SAME store instance for assertions.
        config()->set('cache.default', 'array');
        /** @var CacheRepository $cache */
        $cache = Cache::store('array');

        // Provide a VALID token URL so the HTTP client doesn't fail before fake intercepts.
        config()->set('services.dropbox.client_id', 'cid_x');
        config()->set('services.dropbox.client_secret', 'sec_y');
        config()->set('services.dropbox.token_url', 'https://api.dropboxapi.com/oauth2/token');

        // Fake ALL outbound HTTP.
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

        // Ensure the command and provider are resolved fresh with our test binding.
        $this->app->forgetInstance(AutoRefreshTokenProvider::class);
        $this->app->forgetInstance(RefreshDropboxToken::class);

        // Bind a fresh provider instance seeded with the DB refresh token and our cache store.
        $this->app->bind(AutoRefreshTokenProvider::class, function () use ($cache) {
            return new AutoRefreshTokenProvider(
                clientId: (string)config('services.dropbox.client_id'),
                clientSecret: (string)config('services.dropbox.client_secret'),
                refreshToken: (string)(Config::query()->where('key', 'dropbox_refresh_token')->value('value')),
                cache: $cache,
                cacheKey: 'dropbox.access_token'
            );
        });

        // Act
        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox Token refreshed')
            ->assertExitCode(Command::SUCCESS);

        // Assert: token cached on the SAME array store.
        $this->assertSame('ACCESS_123', $cache->get('dropbox.access_token'));

        // Assert: rotated refresh token persisted to DB.
        $this->assertDatabaseHas('configs', [
            'key' => 'dropbox_refresh_token',
            'value' => 'NEW_REFRESH_456',
        ]);
    }

    public function testFailsWhenNoRefreshTokenConfigured(): void
    {
        // Ensure there's NO refresh token row.
        Config::query()->where('key', 'dropbox_refresh_token')->delete();

        config()->set('cache.default', 'array');
        config()->set('services.dropbox.client_id', 'cid_x');
        config()->set('services.dropbox.client_secret', 'sec_y');
        config()->set('services.dropbox.token_url', 'https://api.dropboxapi.com/oauth2/token');

        Http::preventStrayRequests();

        // Ensure fresh resolution (no stale singleton).
        $this->app->forgetInstance(AutoRefreshTokenProvider::class);
        $this->app->forgetInstance(RefreshDropboxToken::class);

        // Bind provider explicitly with NULL refresh token to mirror app state.
        /** @var CacheRepository $cache */
        $cache = Cache::store('array');
        $this->app->bind(AutoRefreshTokenProvider::class, fn() => new AutoRefreshTokenProvider(
            clientId: (string)config('services.dropbox.client_id'),
            clientSecret: (string)config('services.dropbox.client_secret'),
            refreshToken: null,
            cache: $cache,
            cacheKey: 'dropbox.access_token'
        ));

        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox: Kein Refresh Token konfiguriert.')
            ->assertExitCode(Command::FAILURE);
    }
}
