<?php

namespace App\Providers;

use App\Models\Config;
use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Filesystem\FilesystemAdapter;
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
                // Table möglicherweise noch nicht migriert
            }
            return new AutoRefreshTokenProvider(
                (string)($cfg['client_id'] ?? env('DROPBOX_CLIENT_ID') ?? ''),
                (string)($cfg['client_secret'] ?? env('DROPBOX_CLIENT_SECRET') ?? ''),
                $refresh
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
            $root = (string)($config['root'] ?? '');
            $adapter = new DropboxAdapter($client, $root);

            $filesystem = new Filesystem($adapter);

            // Für Laravel 11/12 funktioniert diese Signatur:
            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
