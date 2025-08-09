<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

$email = config('mail.log.email');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('weekly:run')
    ->sundays()
    ->at('18:00');


// video-Import aus Upload-Ordner – alle 30 Minuten
Schedule::command('ingest:scan', [
    '--inbox' => '/srv/ingest/pending/',
])->everyThirtyMinutes()
    ->emailOutputOnFailure($email);

// Abgelaufene Offers aufräumen – täglich 03:00
Schedule::command('assign:expire')
    ->dailyAt('03:00');

// Videos neu verteilen, falls nicht heruntergeladen – freitags 17:00
Schedule::command('assign:distribute')
    ->fridays()
    ->at('19:00');

// Kanäle benachrichtigen, wenn neue Inhalte da sind – freitags 19:00
Schedule::command('notify:offers')
    ->sundays()
    ->at('20:00')
    ->emailOutputTo($email);

// Dropbox Refresh Token regelmäßig aktualisieren
Schedule::command('dropbox:refresh-token')
    ->everyMinute();
