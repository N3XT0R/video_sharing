<?php

namespace App\Providers;

use App\Services\FileGrabbingService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

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
        $this->app->bind(FileGrabbingService::class, static function (Application $app) {
            $config = $app['config']->get('services.sharing');
            $storage = Storage::disk($config['storage']);

            return new FileGrabbingService($storage);
        });
    }
}
