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
        if (!$this->app->runningInConsole() || $this->isComposerPostCmd() || !$this->hasRequiredTables()) {
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
        $result = true;
        foreach (self::REQUIRED_TABLES as $table) {
            $tmpResult = Schema::hasTable($table);
            if ($tmpResult === false) {
                $result = false;
                break;
            }
        }
        return $result;
    }

    private function isComposerPostCmd(): bool
    {
        if (!app()->runningInConsole()) {
            return false;
        }
        $isComposer = getenv('COMPOSER_BINARY') !== false
            || str_contains($_SERVER['argv'][0] ?? '', 'composer');

        $laravelPostCmds = [
            'package:discover',
            'config:cache',
            'event:cache',
            'route:cache',
            'view:cache',
        ];
        $isKnownArtisanPostCmd = in_array($_SERVER['argv'][1] ?? '', $laravelPostCmds, true);

        return $isComposer && $isKnownArtisanPostCmd;
    }
}