<?php

namespace App\Services\Dashboard;

use App\Models\Course;
use Carbon\Carbon;
use Throwable;

/**
 * Terminy szkoleń (start) w zakresie dat — do znaczników na wykresie dashboardu zamówień.
 */
class DashboardCourseScheduleService
{
    /**
     * @return list<array{
     *     course_id: int,
     *     title: string,
     *     start_date: string,
     *     start_time: string,
     *     schedule_key: string,
     *     instructor_label: string|null
     * }>
     */
    public function buildForRange(
        Carbon $dateFrom,
        Carbon $dateTo,
        string $timezone,
        string $granularity = 'day',
    ): array {
        $from = $dateFrom->copy()->timezone($timezone)->startOfDay();
        $to = $dateTo->copy()->timezone($timezone)->endOfDay();

        try {
            return Course::query()
                ->with(['instructor:id,title,first_name,last_name'])
                ->whereNotNull('start_date')
                ->whereBetween('start_date', [$from, $to])
                ->orderBy('start_date')
                ->orderBy('id')
                ->get(['id', 'title', 'start_date', 'instructor_id'])
                ->map(function (Course $course) use ($timezone, $granularity): array {
                    $start = Carbon::parse($course->start_date)->timezone($timezone);
                    $scheduleKey = $granularity === 'month'
                        ? $start->format('Y-m')
                        : $start->toDateString();

                    return [
                        'course_id' => (int) $course->id,
                        'title' => (string) $course->title,
                        'start_date' => $start->toDateString(),
                        'start_time' => $start->format('H:i'),
                        'schedule_key' => $scheduleKey,
                        'instructor_label' => $this->formatInstructorLabel($course),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function formatInstructorLabel(Course $course): ?string
    {
        $instructor = $course->instructor;
        if ($instructor === null) {
            return null;
        }

        $label = trim((string) $instructor->full_title_name);

        return $label !== '' ? $label : null;
    }
}
