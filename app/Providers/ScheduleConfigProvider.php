<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Schedule\ScheduleConfigFactory;
use App\Services\Schedule\ScheduleConfigFactoryInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ScheduleConfigProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->singleton(ScheduleConfigFactoryInterface::class, ScheduleConfigFactory::class);
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole() || $this->isPackageDiscoveryRun()) {
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

    private function isPackageDiscoveryRun(): bool
    {
        $argv1 = $_SERVER['argv'][1] ?? null;
        return $argv1 === 'package:discover';
    }
}