<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
 * Kolejka zadań (np. generowanie PDF zaświadczeń) – na hostingu bez Supervisora
 * uruchamiana co minutę przez cron (schedule:run). Zob. docs/QUEUE_SEOHOST.md
 */
Schedule::command('queue:work --stop-when-empty --max-time=300')
    ->everyMinute()
    ->withoutOverlapping(5);
