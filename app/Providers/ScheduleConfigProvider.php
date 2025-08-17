<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ConfigService;
use App\Services\Contracts\ConfigRepositoryInterface;
use App\Services\Contracts\ConfigServiceInterface;
use App\Services\Repository\EloquentConfigRepository;
use App\Services\Schedule\ScheduleConfigFactory;
use App\Services\Schedule\ScheduleConfigFactoryInterface;
use Illuminate\Cache\NullStore;
use Illuminate\Cache\Repository;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\Container as Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ScheduleConfigProvider extends ServiceProvider implements DeferrableProvider
{
    private const REQUIRED_TABLES = [
        'configs',
        'config_categories',
        'config_sub_settings',
    ];

    public function register(): void
    {
        parent::register();
        $this->app->bind(ConfigRepositoryInterface::class, EloquentConfigRepository::class);
        $this->app->bind(ConfigServiceInterface::class, static function (Application $app) {
            /**
             * @var Repository $cache
             */
            try {
                $cache = $app['cache']->store();
            } catch (\Throwable $e) {
                $cache = new Repository(new NullStore());
            }
            
            return new ConfigService($cache, $app->get(ConfigRepositoryInterface::class));
        });
        $this->app->singleton(ScheduleConfigFactoryInterface::class, ScheduleConfigFactory::class);
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $e) {
            $name = $e->command ?? '';
            if (!str_starts_with($name, 'schedule:')) {
                return;
            }

            if (!$this->dbIsReachable() || !$this->hasTables(self::REQUIRED_TABLES)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $this->app->make(ScheduleConfigFactoryInterface::class)->register($schedule);
        });
    }

    public function provides(): array
    {
        return [ScheduleConfigFactoryInterface::class];
    }

    protected function hasTables(array $tables, ?string $connection = null): bool
    {
        try {
            foreach ($tables as $table) {
                if (!Schema::connection($connection)->hasTable($table)) {
                    return false;
                }
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }


    protected function dbIsReachable(?string $connection = null): bool
    {
        try {
            DB::connection($connection)->getPdo();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}