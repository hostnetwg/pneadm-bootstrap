<?php

namespace App\Http\Controllers;

use App\Models\CourseOnlineDetails;
use App\Services\ClickMeetingAccessSnapshotService;
use App\Services\ClickMeetingCourseRoomUrlSyncService;
use App\Services\ClickMeetingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClickMeetingTrainingController extends Controller
{
    /**
     * Wyświetla listę szkoleń ClickMeeting.
     */
    public function index(
        ClickMeetingService $clickMeetingService,
        ClickMeetingAccessSnapshotService $accessSnapshots
    ): View {
        $result = $clickMeetingService->listConferences();
        abort_if(! ($result['success'] ?? false), 502, $result['error'] ?? 'Błąd pobierania listy konferencji');

        $trainings = collect($result['active_conferences'] ?? [])
            ->merge($result['scheduled_conferences'] ?? [])
            ->map(function (array $room) {
                $raw = $room['starts_at'] ?? $room['start_time'] ?? null;
                $room['pretty_date'] = $raw
                    ? Carbon::parse($raw)->tz('Europe/Warsaw')->format('d.m.Y H:i')
                    : '—';

                return $room;
            })
            ->sortBy(fn ($t) => $t['starts_at'] ?? $t['start_time'] ?? null)
            ->values();

        $linkedDetails = CourseOnlineDetails::coursesKeyedByEventId(
            $trainings->pluck('id')->map(fn ($id) => (string) $id)->all()
        );

        $trainings = $trainings->map(function (array $room) use ($linkedDetails, $clickMeetingService, $accessSnapshots) {
            $eventId = trim((string) ($room['id'] ?? ''));
            $details = $eventId !== '' ? $linkedDetails->get($eventId) : null;
            $course = $details?->course;
            $room['linked_course'] = $course;
            $apiRoomUrl = $clickMeetingService->extractRoomUrl($room);
            $storedLink = $details ? $clickMeetingService->normalizeRoomUrl($details->meeting_link) : null;
            $room['meeting_link_stale'] = $details !== null
                && $apiRoomUrl !== null
                && $clickMeetingService->roomUrlsDiffer($storedLink, $apiRoomUrl);

            $cmStartRaw = $room['starts_at'] ?? $room['start_time'] ?? null;
            $courseStart = $course?->start_date;
            $room['course_pretty_start'] = $courseStart
                ? Carbon::parse($courseStart)->format('d.m.Y H:i')
                : null;
            $room['start_time_stale'] = $details !== null
                && $clickMeetingService->startTimesDiffer($cmStartRaw, $courseStart);

            $accessType = $clickMeetingService->extractAccessType($room);
            if ($accessType === null && $course !== null && $eventId !== '') {
                $detail = $clickMeetingService->getConference($eventId);
                if ($detail['success'] ?? false) {
                    $accessType = isset($detail['access_type'])
                        ? (int) $detail['access_type']
                        : $clickMeetingService->extractAccessType($detail['conference'] ?? []);
                }
            }
            $room['access_type'] = $accessType;
            $room['access_type_label'] = $clickMeetingService->accessTypeLabel($accessType);
            $room['closed_should_be_open'] = $course !== null
                && $accessSnapshots->closedCourseShouldUseOpenAccess($course, $accessType);
            $room['closed_access_warning'] = $room['closed_should_be_open']
                ? $accessSnapshots->closedAccessWarning($accessType)
                : null;

            if ($course !== null) {
                $accessSnapshots->reconcileCourse((int) $course->id, $accessType);
            }

            return $room;
        });

        return view('clickmeeting.trainings.index', ['trainings' => $trainings]);
    }

    public function syncRoomUrl(
        string $eventId,
        ClickMeetingCourseRoomUrlSyncService $syncService
    ): RedirectResponse {
        $eventId = trim($eventId);
        abort_if($eventId === '', 404);

        $course = CourseOnlineDetails::courseForEventId($eventId);
        if ($course === null) {
            return redirect()
                ->route('clickmeeting.trainings.index')
                ->with('error', 'To wydarzenie nie jest jeszcze powiązane ze szkoleniem w courses.');
        }

        return $this->redirectAfterSync(
            $syncService->refreshFromApi($course),
            route('clickmeeting.trainings.index')
        );
    }

    /**
     * @param  array{success: bool, changed: bool, room_url?: string|null, error?: string, live_access_updated?: int}  $result
     */
    private function redirectAfterSync(array $result, string $url): RedirectResponse
    {
        if (! ($result['success'] ?? false)) {
            return redirect($url)->with('error', $result['error'] ?? 'Nie udało się zaktualizować linku z ClickMeeting.');
        }

        if (! ($result['changed'] ?? false)) {
            return redirect($url)->with('success', 'Link do spotkania jest już zgodny z ClickMeeting.');
        }

        $message = 'Zaktualizowano link do spotkania z ClickMeeting.';
        $liveUpdated = (int) ($result['live_access_updated'] ?? 0);
        if ($liveUpdated > 0) {
            $message .= ' Odświeżono też link w dostępach uczestników ('.$liveUpdated.').';
        }

        return redirect($url)->with('success', $message);
    }
}
