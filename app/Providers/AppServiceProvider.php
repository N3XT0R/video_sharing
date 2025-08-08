<?php

namespace App\Providers;

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
        //
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
