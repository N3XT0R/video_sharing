<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('weekly:run')
    ->sundays()
    ->at('23:00');

// CSV-Import aus Upload-Ordner – alle 30 Minuten
Schedule::command('info:import --inbox=/srv/ingest/pending')
    ->everyThirtyMinutes();

// Abgelaufene Offers aufräumen – täglich 03:00
Schedule::command('assign:expire')
    ->dailyAt('03:00');

// Videos neu verteilen, falls nicht heruntergeladen – Sonntag 03:00
Schedule::command('assign:distribute')
    ->sundays()
    ->at('03:00');

// Kanäle benachrichtigen, wenn neue Inhalte da sind – Sonntag 06:00
Schedule::command('notify:offers')
    ->sundays()
    ->at('06:00');