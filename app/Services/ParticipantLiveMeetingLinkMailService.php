<?php

namespace App\Services;

use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\Participant;
use App\Models\ParticipantLiveAccess;
use App\Notifications\ParticipantLiveMeetingLinkNotification;
use App\Services\Mail\SystemMailDiagnostics;
use App\Support\PneduProvisionLiveAccessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class ParticipantLiveMeetingLinkMailService
{
    public function __construct(
        private readonly ParticipantLiveAccessService $liveAccessService,
        private readonly PneduProvisionEmailContextBuilder $emailContextBuilder,
    ) {}

    /**
     * Czy dla szkolenia można w ogóle wysyłać e-maile z linkiem live.
     */
    public function courseSupportsLiveMeetingEmails(Course $course): bool
    {
        if ($course->hasEnded()) {
            return false;
        }

        $course->loadMissing('onlineDetails');
        $platform = strtolower(trim((string) optional($course->onlineDetails)->platform));
        $eventId = trim((string) optional($course->onlineDetails)->clickmeeting_event_id);
        $meetingLink = trim((string) optional($course->onlineDetails)->meeting_link);

        if ($platform === 'clickmeeting' && $eventId !== '') {
            return true;
        }

        return $meetingLink !== '';
    }

    /**
     * Czy pokój wymaga indywidualnego tokenu (access_type = 3).
     */
    public function courseRequiresAccessToken(Course $course): bool
    {
        $accessType = ParticipantLiveAccess::query()
            ->where('course_id', $course->id)
            ->where('status', 'success')
            ->whereNotNull('access_type')
            ->orderByDesc('id')
            ->value('access_type');

        if ($accessType !== null) {
            return (int) $accessType === ClickMeetingService::ACCESS_TYPE_TOKEN;
        }

        return ParticipantLiveAccess::query()
            ->where('course_id', $course->id)
            ->whereNotNull('token')
            ->where('token', '!=', '')
            ->exists();
    }

    public function eligibleParticipantsCount(Course $course): int
    {
        return (int) $this->eligibleParticipantsQuery($course)->count();
    }

    /**
     * @return Builder<Participant>
     */
    public function eligibleParticipantsQuery(Course $course, string $mode = 'resend_all'): Builder
    {
        $query = Participant::query()
            ->where('participants.course_id', $course->id)
            ->whereNotNull('participants.email')
            ->where('participants.email', '!=', '');

        if ($this->courseRequiresAccessToken($course)) {
            $query->whereHas('liveAccess', function ($liveQuery) {
                $liveQuery->where('status', 'success')
                    ->whereNotNull('token')
                    ->where('token', '!=', '');
            });
        }

        if ($mode === 'unsent') {
            $query->whereNotExists(function ($q) use ($course) {
                $q->selectRaw('1')
                    ->from('certificate_email_logs')
                    ->whereColumn('certificate_email_logs.participant_id', 'participants.id')
                    ->where('certificate_email_logs.course_id', $course->id)
                    ->where('certificate_email_logs.type', CertificateEmailLog::TYPE_LIVE_MEETING_LINK)
                    ->where('certificate_email_logs.status', CertificateEmailLog::STATUS_SENT);
            });
        }

        return $query;
    }

    /**
     * @return Collection<int, Participant>
     */
    public function eligibleParticipants(Course $course, string $mode = 'resend_all'): Collection
    {
        return $this->eligibleParticipantsQuery($course, $mode)
            ->with('liveAccess')
            ->orderBy('participants.id')
            ->get();
    }

    public function participantCanReceiveLiveLink(Participant $participant, Course $course): bool
    {
        return $this->resolveLiveContext($participant, $course) !== null;
    }

    /**
     * @return array{success: bool, error?: string, join_url?: string|null, has_token?: bool}
     */
    public function sendToParticipant(
        Course $course,
        Participant $participant,
        ?CertificateEmailLog $log = null,
        ?int $createdBy = null,
    ): array {
        if ((int) $participant->course_id !== (int) $course->id) {
            return $this->fail($log, 'Uczestnik nie należy do tego kursu.');
        }

        if ($course->hasEnded()) {
            return $this->fail($log, 'Szkolenie zostało zakończone — link do spotkania na żywo nie jest już wysyłany.');
        }

        $email = trim((string) ($participant->email ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            return $this->fail($log, 'Uczestnik nie ma prawidłowego adresu e-mail.');
        }

        $liveContext = $this->resolveLiveContext($participant, $course);
        if ($liveContext === null) {
            return $this->fail(
                $log,
                $this->courseRequiresAccessToken($course)
                    ? 'Brak tokenu ClickMeeting — nie można wysłać linku live dla tego uczestnika.'
                    : 'Nie udało się zbudować linku do spotkania (brak room URL / meeting_link).'
            );
        }

        $course->loadMissing('instructor');
        $instructorLine = null;
        if ($course->instructor) {
            $name = trim((string) ($course->instructor->full_name ?? ''));
            $title = trim((string) ($course->instructor->title ?? ''));
            $display = trim(($title !== '' ? $title.' ' : '').$name);
            if ($display !== '') {
                $instructorLine = 'Prowadzący: '.$display;
            }
        }

        $scheduleLine = $this->formatCourseScheduleLine($course);
        $dashboardUrl = rtrim((string) config('services.pnedu_frontend_url', 'http://localhost:8081'), '/').'/dashboard/szkolenia';

        if ($log === null) {
            $log = CertificateEmailLog::create([
                'course_id' => $course->id,
                'participant_id' => $participant->id,
                'type' => CertificateEmailLog::TYPE_LIVE_MEETING_LINK,
                'status' => CertificateEmailLog::STATUS_QUEUED,
                'created_by' => $createdBy,
                'queued_at' => now(),
                'meta' => [
                    'join_url' => $liveContext->joinUrl,
                    'platform' => $liveContext->platformLabel,
                    'has_token' => filled($liveContext->token),
                ],
            ]);
        } else {
            $log->update([
                'meta' => array_merge($log->meta ?? [], [
                    'join_url' => $liveContext->joinUrl,
                    'platform' => $liveContext->platformLabel,
                    'has_token' => filled($liveContext->token),
                ]),
            ]);
        }

        try {
            Notification::route('mail', $email)->notify(new ParticipantLiveMeetingLinkNotification(
                courseTitle: (string) $course->title,
                participantFirstName: (string) ($participant->first_name ?? ''),
                instructorLine: $instructorLine,
                scheduleLine: $scheduleLine,
                liveAccess: $liveContext,
                dashboardSzkoleniaUrl: $dashboardUrl,
            ));

            $log->update([
                'status' => CertificateEmailLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
                'meta' => array_merge($log->meta ?? [], [
                    'delivery' => SystemMailDiagnostics::currentConfig(),
                ]),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Nie udało się wysłać e-maila z linkiem live: '.$e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'join_url' => $liveContext->joinUrl,
            'has_token' => filled($liveContext->token),
        ];
    }

    public function resolveLiveContext(Participant $participant, Course $course): ?PneduProvisionLiveAccessContext
    {
        $course->loadMissing('onlineDetails');
        $participant->loadMissing('liveAccess');
        $liveAccess = $participant->liveAccess;

        if ($this->courseRequiresAccessToken($course)) {
            if ($liveAccess === null || ! $liveAccess->isSuccessful()) {
                return null;
            }

            if (trim((string) ($liveAccess->token ?? '')) === '') {
                return null;
            }

            $context = $this->emailContextBuilder->build(
                $course,
                $this->liveAccessService->toEmailClickMeetingPayload($liveAccess)
            );

            return ($context->showLiveSection && $context->joinUrl) ? $context : null;
        }

        // Pokój bez tokenu: preferuj dane z liveAccess, inaczej wspólny meeting_link / room_url kursu.
        if ($liveAccess !== null && $liveAccess->isSuccessful()) {
            $context = $this->emailContextBuilder->build(
                $course,
                $this->liveAccessService->toEmailClickMeetingPayload($liveAccess)
            );
            if ($context->showLiveSection && $context->joinUrl) {
                return $context;
            }
        }

        $sharedPayload = $this->sharedOpenRoomPayload($course);
        if ($sharedPayload === null) {
            return null;
        }

        $context = $this->emailContextBuilder->build($course, $sharedPayload);

        return ($context->showLiveSection && $context->joinUrl) ? $context : null;
    }

    /**
     * @return array{status: string, room_url: string, token: null, access_type: int|null}|null
     */
    private function sharedOpenRoomPayload(Course $course): ?array
    {
        $course->loadMissing('onlineDetails');
        $meetingLink = trim((string) optional($course->onlineDetails)->meeting_link);

        $roomUrl = ParticipantLiveAccess::query()
            ->where('course_id', $course->id)
            ->where('status', 'success')
            ->whereNotNull('room_url')
            ->where('room_url', '!=', '')
            ->orderByDesc('id')
            ->value('room_url');

        $roomUrl = trim((string) ($roomUrl ?: $meetingLink));
        if ($roomUrl === '') {
            return null;
        }

        $accessType = ParticipantLiveAccess::query()
            ->where('course_id', $course->id)
            ->where('status', 'success')
            ->whereNotNull('access_type')
            ->orderByDesc('id')
            ->value('access_type');

        return [
            'status' => 'success',
            'room_url' => $roomUrl,
            'token' => null,
            'access_type' => $accessType !== null ? (int) $accessType : null,
        ];
    }

    private function formatCourseScheduleLine(Course $course): ?string
    {
        if (! $course->start_date) {
            return null;
        }

        $tz = (string) config('app.timezone', 'Europe/Warsaw');
        $start = $course->start_date->copy()->timezone($tz)->format('d.m.Y G:i');

        if ($course->end_date) {
            $end = $course->end_date->copy()->timezone($tz);
            if ($course->start_date->copy()->timezone($tz)->isSameDay($end)) {
                return 'Termin: '.$start.'–'.$end->format('G:i');
            }

            return 'Termin: '.$start.' – '.$end->format('d.m.Y G:i');
        }

        return 'Termin: '.$start;
    }

    /**
     * @return array{success: false, error: string}
     */
    private function fail(?CertificateEmailLog $log, string $error): array
    {
        if ($log !== null) {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $error,
            ]);
        }

        return [
            'success' => false,
            'error' => $error,
        ];
    }
}
