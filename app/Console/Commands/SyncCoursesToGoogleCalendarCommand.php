<?php

namespace App\Console\Commands;

use App\Jobs\SyncCourseToGoogleCalendarJob;
use App\Models\Course;
use App\Services\CourseGoogleCalendarSyncService;
use Illuminate\Console\Command;

class SyncCoursesToGoogleCalendarCommand extends Command
{
    protected $signature = 'courses:sync-google-calendar
                            {--course= : ID pojedynczego szkolenia}
                            {--only-errors : Tylko szkolenia ze statusem error}
                            {--since= : Data od (YYYY-MM-DD) — start_date >=}
                            {--dry-run : Pokaż listę bez wysyłania jobów}';

    protected $description = 'Synchronizuje szkolenia z wspólnym kalendarzem Google (API).';

    public function handle(CourseGoogleCalendarSyncService $syncService): int
    {
        if (! $syncService->isEnabled()) {
            $this->error('Google Calendar sync jest wyłączony lub brak konfiguracji (GOOGLE_CALENDAR_ENABLED / credentials).');

            return self::FAILURE;
        }

        $query = Course::query()->with(['instructor', 'location', 'onlineDetails']);

        if ($courseId = $this->option('course')) {
            $query->where('id', (int) $courseId);
        }

        if ($this->option('only-errors')) {
            $query->where('google_calendar_sync_status', Course::GOOGLE_CALENDAR_SYNC_ERROR);
        }

        if ($since = $this->option('since')) {
            $query->where('start_date', '>=', $since.' 00:00:00');
        }

        $courses = $query->orderBy('id')->get();

        if ($courses->isEmpty()) {
            $this->info('Brak szkoleń do synchronizacji.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Tytuł', 'Status', 'Event ID'],
                $courses->map(fn (Course $course) => [
                    $course->id,
                    strip_tags((string) $course->title),
                    $course->google_calendar_sync_status,
                    $course->google_calendar_event_id ?? '-',
                ])->all()
            );

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($courses->count());
        $bar->start();

        foreach ($courses as $course) {
            if ($this->option('course')) {
                $syncService->sync($course);
            } else {
                SyncCourseToGoogleCalendarJob::dispatch($course->id);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Zlecono synchronizację dla '.$courses->count().' szkoleń.');

        return self::SUCCESS;
    }
}
