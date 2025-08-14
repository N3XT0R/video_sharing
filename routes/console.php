<?php

use App\Facades\Cfg;
use App\Services\Schedule\ScheduleConfigFactory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

$email = config('mail.log.email');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('weekly:run')
    ->mondays()
    ->at('08:00')
    ->emailOutputTo($email);


// video-Import aus Upload-Ordner – alle 30 Minuten
Schedule::command('ingest:scan', [
    '--inbox' => Cfg::get('ingest_inbox_absolute_path', null, '/srv/ingest/pending/'),
])->hourly()
    ->emailOutputOnFailure($email);

// Abgelaufene Offers aufräumen – täglich 03:00
Schedule::command('assign:expire')
    ->dailyAt('03:00');


// Dropbox Refresh Token regelmäßig aktualisieren
Schedule::command('dropbox:refresh-token')
    ->everyMinute();

// Dynamische Cronjobs aus Datenbank-Konfiguration
if (Schema::hasTable('configs')) {
    app(ScheduleConfigFactory::class)->register(app(\Illuminate\Console\Scheduling\Schedule::class));
}
