<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Schedule\ScheduleConfigFactory;
use App\Services\Schedule\ScheduleConfigFactoryInterface;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Schedule;
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
        $this->app->singleton(ScheduleConfigFactoryInterface::class, ScheduleConfigFactory::class);
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole() || !$this->hasRequiredTables()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $e) {
            $name = $e->command ?? '';
            if (!str_starts_with($name, 'schedule:')) {
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

    protected function hasRequiredTables(): bool
    {
        $result = true;
        if ($this->dbIsReachable(config('database.default'))) {
            foreach (self::REQUIRED_TABLES as $table) {
                $tmpResult = Schema::hasTable($table);
                if ($tmpResult === false) {
                    $result = false;
                    break;
                }
            }
        } else {
            $result = false;
        }
        
        return $result;
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