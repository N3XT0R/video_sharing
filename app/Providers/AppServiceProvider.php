<?php

namespace App\Providers;

use App\Models\Config;
use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AutoRefreshTokenProvider::class, function ($app) {
            return new AutoRefreshTokenProvider(
                config('services.dropbox.client_id'),
                config('services.dropbox.client_secret'),
                optional(Config::query()->where('key', 'dropbox_refresh_token')->first())->value,
                Cache::store(), // default cache
                config('services.dropbox.token_url', 'https://api.dropboxapi.com/oauth2/token'),
                'dropbox.access_token',
                fn(string $rt) => Config::query()->updateOrCreate(['key' => 'dropbox_refresh_token'],
                    ['value' => $rt])
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::extend('dropbox', function ($app, $config) {
            $client = new DropboxClient(app(AutoRefreshTokenProvider::class));
            $root = trim((string)($config['root'] ?? ''), '/'); // <— neu
            $adapter = new DropboxAdapter($client, $root);

            $filesystem = new Filesystem($adapter);

            // Für Laravel 11/12 funktioniert diese Signatur:
            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
