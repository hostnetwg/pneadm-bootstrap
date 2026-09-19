<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Models\ParticipantLiveAccess;
use Illuminate\Support\Facades\Log;

class ClickMeetingCourseRoomUrlSyncService
{
    /**
     * @var array<int, array{success: bool, changed: bool, room_url?: string|null, error?: string, live_access_updated?: int}>
     */
    private array $refreshMemo = [];

    public function __construct(
        private readonly ClickMeetingService $clickMeeting,
        private readonly CourseGoogleCalendarSyncService $googleCalendar,
        private readonly ClickMeetingAccessSnapshotService $accessSnapshots,
    ) {}

    /**
     * @return array{success: bool, changed: bool, room_url?: string|null, error?: string, live_access_updated?: int}
     */
    public function refreshFromApi(Course $course, bool $syncCalendarWhenChanged = true): array
    {
        $courseId = (int) $course->id;
        if ($courseId > 0 && isset($this->refreshMemo[$courseId])) {
            return $this->refreshMemo[$courseId];
        }

        $course->loadMissing('onlineDetails');
        $eventId = trim((string) optional($course->onlineDetails)->clickmeeting_event_id);
        if ($eventId === '') {
            $result = [
                'success' => false,
                'changed' => false,
                'error' => 'Brak ID wydarzenia ClickMeeting w szkoleniu.',
            ];
            $this->remember($courseId, $result);

            return $result;
        }

        $conferenceResult = $this->clickMeeting->getConference($eventId);
        if (! ($conferenceResult['success'] ?? false)) {
            $result = [
                'success' => false,
                'changed' => false,
                'room_url' => $this->clickMeeting->normalizeRoomUrl(
                    optional($course->onlineDetails)->meeting_link
                ),
                'error' => (string) ($conferenceResult['error'] ?? 'Nie udało się pobrać wydarzenia z ClickMeeting.'),
            ];
            $this->remember($courseId, $result);

            return $result;
        }

        $conference = $conferenceResult['conference'] ?? [];
        $accessType = isset($conferenceResult['access_type'])
            ? (int) $conferenceResult['access_type']
            : $this->clickMeeting->extractAccessType($conference);
        $roomUrl = $this->clickMeeting->extractRoomUrl($conference);
        $result = $this->applyRoomUrl($course, $roomUrl, $syncCalendarWhenChanged);
        $this->accessSnapshots->reconcileCourse($courseId, $accessType);
        $this->remember($courseId, $result);

        return $result;
    }

    /**
     * @return array{
     *     success: bool,
     *     meeting_link_stale: bool,
     *     access_type: int|null,
     *     access_type_label: string,
     *     closed_should_be_open: bool,
     *     closed_access_warning: string|null,
     *     error: string|null
     * }
     */
    public function inspectFromApi(Course $course): array
    {
        $empty = [
            'success' => false,
            'meeting_link_stale' => false,
            'access_type' => null,
            'access_type_label' => $this->clickMeeting->accessTypeLabel(null),
            'closed_should_be_open' => false,
            'closed_access_warning' => null,
            'error' => null,
        ];

        $course->loadMissing('onlineDetails');
        $eventId = trim((string) optional($course->onlineDetails)->clickmeeting_event_id);
        if ($eventId === '') {
            return $empty;
        }

        $conferenceResult = $this->clickMeeting->getConference($eventId);
        if (! ($conferenceResult['success'] ?? false)) {
            $empty['error'] = (string) ($conferenceResult['error'] ?? 'Nie udało się pobrać wydarzenia z ClickMeeting.');

            return $empty;
        }

        $conference = $conferenceResult['conference'] ?? [];
        $accessType = isset($conferenceResult['access_type'])
            ? (int) $conferenceResult['access_type']
            : $this->clickMeeting->extractAccessType($conference);
        $this->accessSnapshots->reconcileCourse((int) $course->id, $accessType);

        $apiRoomUrl = $this->clickMeeting->extractRoomUrl($conference);
        $storedLink = $this->clickMeeting->normalizeRoomUrl(
            optional($course->onlineDetails)->meeting_link
        );
        $meetingLinkStale = $apiRoomUrl !== null
            && $this->clickMeeting->roomUrlsDiffer($storedLink, $apiRoomUrl);
        $closedShouldBeOpen = $this->accessSnapshots->closedCourseShouldUseOpenAccess($course, $accessType);

        return [
            'success' => true,
            'meeting_link_stale' => $meetingLinkStale,
            'access_type' => $accessType,
            'access_type_label' => $this->clickMeeting->accessTypeLabel($accessType),
            'closed_should_be_open' => $closedShouldBeOpen,
            'closed_access_warning' => $closedShouldBeOpen
                ? $this->accessSnapshots->closedAccessWarning($accessType)
                : null,
            'error' => null,
        ];
    }

    public function isMeetingLinkStale(Course $course): bool
    {
        return $this->inspectFromApi($course)['meeting_link_stale'];
    }

    /**
     * @return array{success: bool, changed: bool, room_url?: string|null, error?: string, live_access_updated?: int}
     */
    public function applyRoomUrl(
        Course $course,
        ?string $roomUrl,
        bool $syncCalendarWhenChanged = true
    ): array {
        $normalized = $this->clickMeeting->normalizeRoomUrl($roomUrl);
        if ($normalized === null) {
            return [
                'success' => false,
                'changed' => false,
                'error' => 'ClickMeeting nie zwrócił aktualnego linku do pokoju.',
            ];
        }

        $course->loadMissing('onlineDetails');
        $details = $course->onlineDetails;
        if (! $details instanceof CourseOnlineDetails || ! $details->exists) {
            return [
                'success' => true,
                'changed' => false,
                'room_url' => $normalized,
                'live_access_updated' => 0,
            ];
        }

        $current = $this->clickMeeting->normalizeRoomUrl($details->meeting_link);
        $linkChanged = $current !== $normalized;

        if ($linkChanged) {
            $details->meeting_link = $normalized;
            $details->save();
            $course->setRelation('onlineDetails', $details->fresh());
        }

        $liveAccessUpdated = ParticipantLiveAccess::query()
            ->where('course_id', $course->id)
            ->where(function ($query) use ($normalized) {
                $query->whereNull('room_url')
                    ->orWhere('room_url', '')
                    ->orWhere('room_url', '!=', $normalized);
            })
            ->update([
                'room_url' => $normalized,
                'synced_at' => now(),
            ]);

        $changed = $linkChanged || $liveAccessUpdated > 0;

        if ($changed && $syncCalendarWhenChanged) {
            try {
                $this->googleCalendar->sync(
                    $course->fresh(['instructor', 'location', 'onlineDetails']) ?? $course
                );
            } catch (\Throwable $e) {
                Log::warning('ClickMeetingCourseRoomUrlSyncService: Google Calendar sync failed', [
                    'course_id' => $course->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'changed' => $changed,
            'room_url' => $normalized,
            'live_access_updated' => $liveAccessUpdated,
        ];
    }

    /**
     * @param  array{success: bool, changed: bool, room_url?: string|null, error?: string, live_access_updated?: int}  $result
     */
    private function remember(int $courseId, array $result): void
    {
        if ($courseId > 0) {
            $this->refreshMemo[$courseId] = $result;
        }
    }
}
