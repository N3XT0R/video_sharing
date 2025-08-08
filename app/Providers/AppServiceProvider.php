<?php

namespace App\Providers;

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
            return new AutoRefreshTokenProvider(
                $cfg['client_id'] ?? env('DROPBOX_CLIENT_ID'),
                $cfg['client_secret'] ?? env('DROPBOX_CLIENT_SECRET'),
                $cfg['refresh_token'] ?? env('DROPBOX_REFRESH_TOKEN')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::extend('dropbox', function ($app, $config) {
            $client = new DropboxClient($config['authorization_token']);
            $root = $config['root'] ?? '';
            $adapter = new DropboxAdapter($client, $root);

            // Flysystem-Instanz
            $filesystem = new Filesystem($adapter);

            // WICHTIG: Laravel-Adapter zurückgeben, nicht $filesystem!
            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
