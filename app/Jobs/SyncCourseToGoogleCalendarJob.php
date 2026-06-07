<?php

namespace App\Jobs;

use App\Models\Course;
use App\Services\CourseGoogleCalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCourseToGoogleCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public int $courseId
    ) {
        $this->afterCommit();
    }

    public function handle(CourseGoogleCalendarSyncService $syncService): void
    {
        if (! $syncService->isEnabled()) {
            return;
        }

        $course = Course::withTrashed()
            ->with(['instructor', 'location', 'onlineDetails'])
            ->find($this->courseId);

        if (! $course) {
            return;
        }

        $syncService->sync($course);
    }
}
