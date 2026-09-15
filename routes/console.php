<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:import')
    ->dailyAt('05:00')
    ->days([1, 2, 3, 4, 5, 6])
    ->withoutOverlapping();

Schedule::command('app:import --process-print-data=true')
    ->weeklyOn(6, '21:00')
    ->withoutOverlapping();

// Sitemap for search engines, nightly (docs/02_V2B_ADMIN.md §3.10); the admin page can regenerate it on demand.
Schedule::command('app:GenerateSitemap')->dailyAt('03:30')->withoutOverlapping();
