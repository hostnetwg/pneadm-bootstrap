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

Schedule::command('courses:sync-google-calendar --only-errors')
    ->hourly()
    ->when(fn () => (bool) config('services.google_calendar.enabled', false));

$reminderTime = (string) config('participant_access.expiry_reminder.schedule_time', '08:00');
$reminderTimezone = (string) config('participant_access.expiry_reminder.timezone', 'Europe/Warsaw');

Schedule::command('participants:send-access-expiry-reminders')
    ->dailyAt($reminderTime)
    ->timezone($reminderTimezone)
    ->when(fn () => (bool) config('participant_access.expiry_reminder.enabled', true));

$analyticsTimezone = (string) config('analytics.order_form_funnel.timezone', 'Europe/Warsaw');

Schedule::command('analytics:aggregate-order-forms')
    ->dailyAt('03:45')
    ->timezone($analyticsTimezone)
    ->withoutOverlapping(30);
