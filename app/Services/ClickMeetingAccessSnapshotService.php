<?php

namespace App\Services;

use App\Models\Course;
use App\Models\ParticipantLiveAccess;

/**
 * Dopasowuje snapshot participant_live_access.access_type do aktualnego wydarzenia CM.
 */
class ClickMeetingAccessSnapshotService
{
    public function __construct(
        private readonly ClickMeetingService $clickMeeting,
    ) {}

    public function reconcileCourse(int $courseId, ?int $accessType): int
    {
        if ($courseId < 1 || $accessType === null) {
            return 0;
        }

        return ParticipantLiveAccess::query()
            ->where('course_id', $courseId)
            ->where(function ($query) use ($accessType) {
                $query->whereNull('access_type')
                    ->orWhere('access_type', '!=', $accessType);
            })
            ->update([
                'access_type' => $accessType,
                'synced_at' => now(),
            ]);
    }

    public function closedCourseShouldUseOpenAccess(Course $course, ?int $clickMeetingAccessType): bool
    {
        if ($course->category !== 'closed') {
            return false;
        }

        if ($clickMeetingAccessType === null) {
            return false;
        }

        return $clickMeetingAccessType !== ClickMeetingService::ACCESS_TYPE_OPEN;
    }

    public function closedAccessWarning(?int $clickMeetingAccessType): string
    {
        $current = $this->clickMeeting->accessTypeLabel($clickMeetingAccessType);

        return 'Szkolenie zamknięte: w ClickMeeting ustaw dostęp „Dla wszystkich” (teraz: '.$current
            .'). Dyrektor dostaje jeden link i rozsyła nauczycielom — tokeny albo hasło zablokują wejście.';
    }
}
