<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\Config;
use App\Services\ConfigService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleConfigFactory implements ScheduleConfigFactoryInterface
{
    public function __construct(private ConfigService $configService)
    {
    }

    /**
     * Determine if Composer has finished running to avoid triggering console
     * bootstrapping during install or update scripts.
     */
    public static function composerHasFinished(): bool
    {
        if (!app()->runningInConsole()) {
            return true;
        }

        return getenv('COMPOSER_BINARY') === false
            && !str_contains($_SERVER['argv'][0] ?? '', 'composer');
    }

    /**
     * Register schedule events for all configs within the "schedule" category.
     */
    public function register(Schedule $schedule): void
    {
        Config::query()
            ->whereHas('category', fn($q) => $q->where('key', 'schedule'))
            ->with(['subSettings', 'category'])
            ->get()
            ->each(fn(Config $config) => $this->make($schedule, $config));
    }

    /**
     * Build a schedule event from the given config.
     */
    public function make(Schedule $schedule, Config $config): ?Event
    {
        if (!$config->value) {
            return null;
        }

        $subs = $this->configService->rememberConfig($config);
        $params = $subs['params'] ?? [];
        if (!is_array($params)) {
            $params = [];
        }

        $event = $schedule->command($config->key, $params);

        if (!empty($subs['frequency'])) {
            $event->cron($subs['frequency']);
        }

        if (!empty($subs['email_on_failure'])) {
            $event->emailOutputOnFailure($subs['email_on_failure']);
        }

        if (!empty($subs['environments'])) {
            $event->environments((array)$subs['environments']);
        }

        if (!empty($subs['without_overlapping']) && $subs['without_overlapping']) {
            $event->withoutOverlapping();
        }

        return $event;
    }
}

