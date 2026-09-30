<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily database backup at 2am (storage/app/backups, newest 14 kept).
Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping();
