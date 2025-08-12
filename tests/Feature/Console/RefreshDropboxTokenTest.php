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
