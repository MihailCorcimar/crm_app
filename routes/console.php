<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('deals:process-follow-ups')->everyFiveMinutes();
Schedule::command('deals:run-automation-rules')->hourly();
Schedule::command('ai:refresh-sales-suggestions')->dailyAt('08:00');
