<?php

namespace App\Services;

use App\Models\Course;
use App\Support\CourseCalendarEventBuilder;
use Google\Service\Calendar\Event;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseGoogleCalendarSyncService
{
    public function __construct(
        private readonly GoogleCalendarClientFactory $clientFactory
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('services.google_calendar.enabled', false)
            && $this->clientFactory->isConfigured();
    }

    public function shouldSync(Course $course): bool
    {
        if ($course->trashed()) {
            return false;
        }

        if (! $course->is_active) {
            return false;
        }

        if (! $course->start_date || ! $course->end_date) {
            return false;
        }

        return true;
    }

    public function sync(Course $course): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $course->loadMissing(['instructor', 'location', 'onlineDetails']);

        if (! $this->shouldSync($course)) {
            $this->removeFromGoogle($course);

            return;
        }

        try {
            $calendar = $this->clientFactory->makeCalendarService();
            $calendarId = (string) config('services.google_calendar.calendar_id');
            $payload = CourseCalendarEventBuilder::for($course)->toApiEventArray();
            $eventResource = new Event($payload);

            $event = $course->google_calendar_event_id
                ? $this->upsertGoogleEvent($calendar, $calendarId, $course->google_calendar_event_id, $eventResource)
                : $calendar->events->insert($calendarId, $eventResource);

            $course->updateQuietly([
                'google_calendar_event_id' => $event->getId(),
                'google_calendar_html_link' => $event->getHtmlLink(),
                'google_calendar_sync_status' => Course::GOOGLE_CALENDAR_SYNC_SYNCED,
                'google_calendar_synced_at' => now(),
                'google_calendar_sync_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Google Calendar sync failed', [
                'course_id' => $course->id,
                'message' => $e->getMessage(),
            ]);

            $course->updateQuietly([
                'google_calendar_sync_status' => Course::GOOGLE_CALENDAR_SYNC_ERROR,
                'google_calendar_sync_error' => Str::limit($e->getMessage(), 1000),
            ]);
        }
    }

    /**
     * Aktualizuje wydarzenie lub tworzy nowe, gdy stare zostało usunięte / anulowane w Google Calendar.
     */
    protected function upsertGoogleEvent(
        \Google\Service\Calendar $calendar,
        string $calendarId,
        string $eventId,
        Event $eventResource
    ): Event {
        try {
            $existing = $calendar->events->get($calendarId, $eventId);

            if ($existing->getStatus() === 'cancelled') {
                return $calendar->events->insert($calendarId, $eventResource);
            }

            $updated = $calendar->events->patch($calendarId, $eventId, $eventResource);

            if ($updated->getStatus() === 'cancelled') {
                return $calendar->events->insert($calendarId, $eventResource);
            }

            return $updated;
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 404 || $e->getCode() === 410) {
                return $calendar->events->insert($calendarId, $eventResource);
            }

            throw $e;
        }
    }

    protected function removeFromGoogle(Course $course): void
    {
        if (! $course->google_calendar_event_id) {
            if ($course->google_calendar_sync_status !== Course::GOOGLE_CALENDAR_SYNC_SKIPPED) {
                $course->updateQuietly([
                    'google_calendar_sync_status' => Course::GOOGLE_CALENDAR_SYNC_SKIPPED,
                    'google_calendar_sync_error' => null,
                ]);
            }

            return;
        }

        try {
            $calendar = $this->clientFactory->makeCalendarService();
            $calendarId = (string) config('services.google_calendar.calendar_id');

            $calendar->events->delete($calendarId, $course->google_calendar_event_id);

            $course->updateQuietly([
                'google_calendar_event_id' => null,
                'google_calendar_html_link' => null,
                'google_calendar_sync_status' => Course::GOOGLE_CALENDAR_SYNC_SKIPPED,
                'google_calendar_synced_at' => now(),
                'google_calendar_sync_error' => null,
            ]);
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 404 || $e->getCode() === 410) {
                $course->updateQuietly([
                    'google_calendar_event_id' => null,
                    'google_calendar_html_link' => null,
                    'google_calendar_sync_status' => Course::GOOGLE_CALENDAR_SYNC_SKIPPED,
                    'google_calendar_synced_at' => now(),
                    'google_calendar_sync_error' => null,
                ]);

                return;
            }

            Log::error('Google Calendar delete failed', [
                'course_id' => $course->id,
                'message' => $e->getMessage(),
            ]);

            $course->updateQuietly([
                'google_calendar_sync_status' => Course::GOOGLE_CALENDAR_SYNC_ERROR,
                'google_calendar_sync_error' => Str::limit($e->getMessage(), 1000),
            ]);
        } catch (\Throwable $e) {
            Log::error('Google Calendar delete failed', [
                'course_id' => $course->id,
                'message' => $e->getMessage(),
            ]);

            $course->updateQuietly([
                'google_calendar_sync_status' => Course::GOOGLE_CALENDAR_SYNC_ERROR,
                'google_calendar_sync_error' => Str::limit($e->getMessage(), 1000),
            ]);
        }
    }
}
