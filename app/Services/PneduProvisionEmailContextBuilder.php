<?php

namespace App\Services;

use App\Models\Course;
use App\Support\PneduProvisionLiveAccessContext;
use Carbon\CarbonInterface;

class PneduProvisionEmailContextBuilder
{
    /**
     * @param  array{
     *   success?: bool,
     *   token?: string|null,
     *   room_url?: string|null,
     *   access_type?: int|null,
     *   status?: string
     * }|null  $clickMeetingResult
     */
    public function build(Course $course, ?array $clickMeetingResult = null): PneduProvisionLiveAccessContext
    {
        $course->loadMissing('onlineDetails');

        $showPostEventSection = $course->type === 'online';

        if (! $this->isLiveUpcoming($course)) {
            return new PneduProvisionLiveAccessContext(
                showPostEventSection: $showPostEventSection,
            );
        }

        $platform = strtolower(trim((string) optional($course->onlineDetails)->platform));
        $meetingLink = trim((string) optional($course->onlineDetails)->meeting_link);
        $meetingPassword = trim((string) optional($course->onlineDetails)->meeting_password);
        $eventId = trim((string) optional($course->onlineDetails)->clickmeeting_event_id);

        if ($platform === 'clickmeeting' && $eventId !== '') {
            if (($clickMeetingResult['status'] ?? '') !== 'success') {
                return new PneduProvisionLiveAccessContext(
                    showPostEventSection: $showPostEventSection,
                );
            }

            $liveAccess = $this->buildClickMeetingLiveAccess(
                $clickMeetingResult,
                $meetingLink,
                $meetingPassword,
                $showPostEventSection
            );

            if ($liveAccess !== null) {
                return $liveAccess;
            }

            return new PneduProvisionLiveAccessContext(
                showPostEventSection: $showPostEventSection,
            );
        }

        if ($meetingLink !== '') {
            return new PneduProvisionLiveAccessContext(
                showLiveSection: true,
                platformLabel: $this->platformLabel($platform),
                joinUrl: $meetingLink,
                password: $meetingPassword !== '' ? $meetingPassword : null,
                showPostEventSection: $showPostEventSection,
            );
        }

        return new PneduProvisionLiveAccessContext(
            showPostEventSection: $showPostEventSection,
        );
    }

    public function isLiveUpcoming(Course $course): bool
    {
        if ($course->type !== 'online') {
            return false;
        }

        if (! $course->start_date) {
            return false;
        }

        if ($course->end_date instanceof CarbonInterface && $course->end_date->isPast()) {
            return false;
        }

        if (! $course->end_date && $course->start_date->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{
     *   success?: bool,
     *   token?: string|null,
     *   room_url?: string|null,
     *   access_type?: int|null,
     *   status?: string
     * }|null  $clickMeetingResult
     */
    private function buildClickMeetingLiveAccess(
        ?array $clickMeetingResult,
        string $meetingLinkFallback,
        string $meetingPassword,
        bool $showPostEventSection
    ): ?PneduProvisionLiveAccessContext {
        $roomUrl = trim((string) ($clickMeetingResult['room_url'] ?? ''));
        if ($roomUrl === '') {
            $roomUrl = $meetingLinkFallback;
        }

        if ($roomUrl === '') {
            return null;
        }

        $accessType = isset($clickMeetingResult['access_type'])
            ? (int) $clickMeetingResult['access_type']
            : null;
        $token = trim((string) ($clickMeetingResult['token'] ?? ''));

        if ($accessType === ClickMeetingService::ACCESS_TYPE_TOKEN && $token === '') {
            return null;
        }

        $joinUrl = app(ClickMeetingService::class)->buildJoinUrl($roomUrl, $token !== '' ? $token : null);

        $password = null;
        if ($accessType === ClickMeetingService::ACCESS_TYPE_PASSWORD || $meetingPassword !== '') {
            $password = $meetingPassword !== '' ? $meetingPassword : null;
        }

        return new PneduProvisionLiveAccessContext(
            showLiveSection: true,
            platformLabel: 'ClickMeeting',
            joinUrl: $joinUrl,
            token: $token !== '' ? $token : null,
            password: $password,
            showSpamNote: true,
            showPostEventSection: $showPostEventSection,
        );
    }

    private function platformLabel(string $platform): string
    {
        return match ($platform) {
            'clickmeeting' => 'ClickMeeting',
            'youtube' => 'YouTube',
            'google meet', 'googlemeet', 'meet' => 'Google Meet',
            'zoom' => 'Zoom',
            default => $platform !== '' ? ucfirst($platform) : 'Spotkanie online',
        };
    }
}
