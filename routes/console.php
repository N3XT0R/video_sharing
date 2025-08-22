<?php

use App\Facades\Cfg;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

$email = Cfg::get('email_admin_mail', 'email', 'info@example.tld', true);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('weekly:run')
    ->mondays()
    ->at('08:00')
    ->emailOutputTo($email);

Schedule::command('ingest:scan', [
    '--inbox' => Cfg::get('ingest_inbox_absolute_path', 'default', '/srv/ingest/pending/', true),
])->hourly()
    ->emailOutputOnFailure($email);

Schedule::command('ingest:unzip', [
    '--inbox' => Cfg::get('ingest_inbox_absolute_path', 'default', '/srv/ingest/pending/', true),
])->everyTenMinutes()
    ->emailOutputOnFailure($email);

Schedule::command('assign:expire')
    ->dailyAt('03:00');

Schedule::command('video:cleanup', [
    '--weeks' => Cfg::get('post_expiry_retention_weeks', 'default', 1, true),
])
    ->dailyAt('04:00')
    ->emailOutputOnFailure($email);

Schedule::command('notify:reminders')
    ->dailyAt('09:00')
    ->emailOutputOnFailure($email);

// Dropbox Refresh Token regelmäßig aktualisieren
Schedule::command('dropbox:refresh-token')
    ->everyMinute();
