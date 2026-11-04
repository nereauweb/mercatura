<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:import')
    ->dailyAt('03:00')
    ->days([1, 2, 3, 4, 5, 6])
    ->withoutOverlapping();

Schedule::command('app:import --process-customization-data=true')
    ->weeklyOn(6, '23:00')
    ->withoutOverlapping();

// Sitemap runs at the end of app:import (Import command), not on its own clock.
// The admin page can still regenerate it on demand.

// Report attachments past their retention period (docs/06_USER_REPORTS.md §4).
Schedule::command('cleanup:report-attachments')->dailyAt('04:20')->withoutOverlapping();
