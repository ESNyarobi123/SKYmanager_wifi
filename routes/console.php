<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:reconcile-expired-access')->everyMinute();

if (config('skymanager.schedule_router_hotspot_sessions_sync')) {
    Schedule::command('skymanager:sync-router-hotspot-sessions --all-ready')->everyFiveMinutes();
}
