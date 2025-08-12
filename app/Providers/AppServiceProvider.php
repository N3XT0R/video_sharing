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
            $cfg = config('filesystems.disks.dropbox');
            $refresh = null;
            try {
                $refresh = Config::query()->firstWhere('key', 'dropbox_refresh_token')?->value;
            } catch (\Throwable $e) {
            }
            return new AutoRefreshTokenProvider(
                (string)($cfg['client_id'] ?: ''),
                (string)($cfg['client_secret'] ?: ''),
                $refresh,
                Cache::store()
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
