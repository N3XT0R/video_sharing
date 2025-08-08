<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

$email = config('mail.log.email');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('weekly:run')
    ->fridays()
    ->at('19:00');


// video-Import aus Upload-Ordner – alle 30 Minuten
Schedule::command('ingest:scan', [
    '--inbox' => '/srv/ingest/pending/',
])->everyThirtyMinutes()
    ->emailOutputTo($email);

// CSV-Import aus Upload-Ordner – alle 30 Minuten
Schedule::command('info:import', [
    '--inbox' => '/srv/ingest/pending/',
])
    ->everyThirtyMinutes()
    ->emailOutputTo($email);

// Abgelaufene Offers aufräumen – täglich 03:00
Schedule::command('assign:expire')
    ->dailyAt('03:00');

// Videos neu verteilen, falls nicht heruntergeladen – freitags 17:00
Schedule::command('assign:distribute')
    ->fridays()
    ->at('16:00');

// Kanäle benachrichtigen, wenn neue Inhalte da sind – freitags 19:00
Schedule::command('notify:offers')
    ->fridays()
    ->at('19:00')
    ->emailOutputTo($email);

Schedule::command('previews:generate')
    ->everyThirtyMinutes();

// Dropbox Refresh Token regelmäßig aktualisieren
Schedule::command('dropbox:refresh-token')
    ->everyMinute();
