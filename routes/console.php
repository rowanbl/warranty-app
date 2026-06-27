<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh fuel prices hourly. The feed only changes within 30 minutes of a price
// change, so hourly keeps us current without hammering it. Geocoding of new
// forecourts is capped per run inside the command.
Schedule::command('fuel:ingest')->hourly()->withoutOverlapping();
