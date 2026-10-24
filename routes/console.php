<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tâches planifiées (fuseau de l'application : Africa/Djibouti). En exploitation, `php artisan schedule:run`
// doit être lancé chaque minute (Planificateur de tâches Windows ou cron).
Schedule::command('dls:expirer-workflows')->hourly()->withoutOverlapping()->runInBackground();
Schedule::command('dls:appliquer-effets')->dailyAt('00:05')->withoutOverlapping()->runInBackground();
