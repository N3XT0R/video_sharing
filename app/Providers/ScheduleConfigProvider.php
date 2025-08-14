<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Schedule\ScheduleConfigFactory;
use App\Services\Schedule\ScheduleConfigFactoryInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ScheduleConfigProvider extends ServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->bind(ScheduleConfigFactoryInterface::class, ScheduleConfigFactory::class);
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            $this->app->make(ScheduleConfigFactoryInterface::class)->register($schedule);
        });
    }
}