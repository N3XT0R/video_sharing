<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use Illuminate\Console\Scheduling\Schedule;

interface ScheduleConfigFactoryInterface
{
    public function register(Schedule $schedule): void;
}