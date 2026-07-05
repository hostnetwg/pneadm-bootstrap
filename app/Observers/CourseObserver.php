<?php

namespace App\Observers;

use App\Models\Course;
use App\Services\CourseGoogleCalendarSyncService;
use App\Services\PneduFrontendCacheInvalidationService;
use Illuminate\Support\Facades\DB;

class CourseObserver
{
    public function saved(Course $course): void
    {
        $this->syncGoogleCalendar($course);
        $this->invalidatePneduUpcomingCoursesCache();
    }

    public function deleted(Course $course): void
    {
        $this->syncGoogleCalendar($course);
        $this->invalidatePneduUpcomingCoursesCache();
    }

    public function restored(Course $course): void
    {
        $this->syncGoogleCalendar($course);
        $this->invalidatePneduUpcomingCoursesCache();
    }

    private function invalidatePneduUpcomingCoursesCache(): void
    {
        DB::afterCommit(function () {
            app(PneduFrontendCacheInvalidationService::class)->invalidateUpcomingCourses();
        });
    }

    private function syncGoogleCalendar(Course $course): void
    {
        if (! config('services.google_calendar.enabled', false)) {
            return;
        }

        $courseId = $course->id;

        DB::afterCommit(function () use ($courseId) {
            $course = Course::withTrashed()
                ->with(['instructor', 'location', 'onlineDetails'])
                ->find($courseId);

            if (! $course) {
                return;
            }

            app(CourseGoogleCalendarSyncService::class)->sync($course);
        });
    }
}
