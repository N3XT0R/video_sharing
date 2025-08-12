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
    /** Happy path: refresh works, token cached, refresh token rotated in DB. */
    public function testRefreshesTokenCachesItAndPersistsRotatedRefreshToken(): void
    {
        // Seed DB so the provider can read a refresh token
        Config::query()->updateOrCreate(
            ['key' => 'dropbox_refresh_token'],
            ['value' => 'OLD_REFRESH_000']
        );

        // Use in-memory cache for deterministic asserts
        config()->set('cache.default', 'array');
        /** @var CacheRepository $cache */
        $cache = Cache::store('array');

        // Ensure provider config is present; URL itself is irrelevant because we fake '*'
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

        // Contextual binding: for THIS command, inject a fresh provider wired to our cache + DB RT
        $this->app->when(RefreshDropboxToken::class)
            ->needs(AutoRefreshTokenProvider::class)
            ->give(function () use ($cache) {
                return new AutoRefreshTokenProvider(
                    clientId: (string)config('services.dropbox.client_id'),
                    clientSecret: (string)config('services.dropbox.client_secret'),
                    refreshToken: (string)Config::query()->where('key', 'dropbox_refresh_token')->value('value'),
                    cache: $cache,
                    cacheKey: 'dropbox.access_token'
                );
            });

        // Act
        $this->artisan('dropbox:refresh-token')
            ->expectsOutput('Dropbox Token refreshed')
            ->assertExitCode(Command::SUCCESS);

        // Assert: access token cached on the same store we injected
        $this->assertSame('ACCESS_123', $cache->get('dropbox.access_token'));

        // Assert: rotated refresh token persisted
        $this->assertDatabaseHas('configs', [
            'key' => 'dropbox_refresh_token',
            'value' => 'NEW_REFRESH_456',
        ]);
    }

    /** Failure: no refresh token in DB -> provider throws, command returns FAILURE. */
    public function testFailsWhenNoRefreshTokenConfigured(): void
    {
        // Ensure there is no refresh token row
        Config::query()->where('key', 'dropbox_refresh_token')->delete();

        config()->set('cache.default', 'array');
        /** @var CacheRepository $cache */
        $cache = Cache::store('array');

        config()->set('services.dropbox.client_id', 'cid_x');
        config()->set('services.dropbox.client_secret', 'sec_y');
        config()->set('services.dropbox.token_url', 'https://api.dropboxapi.com/oauth2/token');

        Http::preventStrayRequests(); // nothing should be called

        // Contextual binding: inject a provider without RT to mirror app state
        $this->app->when(RefreshDropboxToken::class)
            ->needs(AutoRefreshTokenProvider::class)
            ->give(fn() => new AutoRefreshTokenProvider(
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
