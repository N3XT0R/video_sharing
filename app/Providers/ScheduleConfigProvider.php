<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Schedule\ScheduleConfigFactory;
use App\Services\Schedule\ScheduleConfigFactoryInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Support\DeferrableProvider;
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
        $this->app->singleton(ScheduleConfigFactoryInterface::class, ScheduleConfigFactory::class);
    }

    public function boot(): void
    {
        if (
            !$this->app->runningInConsole()
            || !ScheduleConfigFactory::composerHasFinished()
            || !$this->hasRequiredTables()
        ) {
            return;
        }
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            $this->app->make(ScheduleConfigFactoryInterface::class)->register($schedule);
        });
    }

    public function provides(): array
    {
        return [ScheduleConfigFactoryInterface::class];
    }

    protected function hasRequiredTables(): bool
    {
        try {
            foreach (self::REQUIRED_TABLES as $table) {
                if (!Schema::hasTable($table)) {
                    return false;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

}
