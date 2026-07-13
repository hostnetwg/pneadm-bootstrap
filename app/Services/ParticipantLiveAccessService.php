<?php

namespace App\Services;

use App\Models\Course;
use App\Models\FormOrder;
use App\Models\Participant;
use App\Models\ParticipantLiveAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ParticipantLiveAccessService
{
    /**
     * Rejestruje uczestnika w ClickMeeting (gdy skonfigurowano) i zapisuje dostęp na żywo.
     *
     * @return array{
     *   success: bool,
     *   status?: string,
     *   detail?: string,
     *   token?: string|null,
     *   room_url?: string|null,
     *   access_type?: int|null,
     *   warning?: string
     * }
     */
    public function provisionClickMeetingForParticipant(
        Participant $participant,
        Course $course,
        ?int $formOrderId = null
    ): array {
        $course->loadMissing('onlineDetails');

        $platform = strtolower(trim((string) optional($course->onlineDetails)->platform));
        $eventId = trim((string) optional($course->onlineDetails)->clickmeeting_event_id);

        if ($platform !== 'clickmeeting') {
            return [
                'success' => true,
                'status' => 'skipped_not_clickmeeting',
                'detail' => 'Krok ClickMeeting pominięty: platforma kursu nie jest ustawiona na ClickMeeting.',
            ];
        }

        if ($eventId === '') {
            $result = [
                'success' => false,
                'status' => 'skipped_missing_event_id',
                'detail' => 'Brak ID wydarzenia ClickMeeting w konfiguracji kursu online.',
                'warning' => 'Uwaga: uczestnik zapisany, ale nie dodano do ClickMeeting (brak ID wydarzenia w konfiguracji kursu online).',
            ];
            $this->persistLiveAccess($participant, $course, $result, $formOrderId);

            return $result;
        }

        $email = strtolower(trim((string) $participant->email));
        if ($email === '' || ! str_contains($email, '@')) {
            return [
                'success' => false,
                'status' => 'failed',
                'detail' => 'Brak prawidłowego adresu e-mail uczestnika.',
            ];
        }

        $clickMeetingService = app(ClickMeetingService::class);
        $registerResult = $clickMeetingService->registerParticipant(
            $eventId,
            (string) $participant->first_name,
            (string) $participant->last_name,
            $email
        );

        if (! ($registerResult['success'] ?? false)) {
            Log::warning('ParticipantLiveAccessService: ClickMeeting registration failed', [
                'participant_id' => $participant->id,
                'course_id' => $course->id,
                'event_id' => $eventId,
                'email' => $email,
                'error' => $registerResult['error'] ?? 'unknown',
            ]);

            $result = [
                'success' => false,
                'status' => 'failed',
                'detail' => (string) ($registerResult['error'] ?? 'Nieznany błąd ClickMeeting.'),
                'warning' => 'Uwaga: nie udało się dodać uczestnika do ClickMeeting. '.($registerResult['error'] ?? ''),
            ];
            $this->persistLiveAccess($participant, $course, $result, $formOrderId);

            return $result;
        }

        $result = $this->buildSuccessfulClickMeetingResult($clickMeetingService, $eventId, $email);
        $this->persistLiveAccess($participant, $course, $result, $formOrderId);

        return $result;
    }

    /**
     * @param  array{status?: string, detail?: string, token?: string|null, room_url?: string|null, access_type?: int|null}  $clickMeetingResult
     */
    public function persistLiveAccess(
        Participant $participant,
        Course $course,
        array $clickMeetingResult,
        ?int $formOrderId = null
    ): ParticipantLiveAccess {
        $eventId = trim((string) optional($course->onlineDetails)->clickmeeting_event_id);

        return ParticipantLiveAccess::query()->updateOrCreate(
            ['participant_id' => $participant->id],
            [
                'course_id' => $course->id,
                'form_order_id' => $formOrderId,
                'platform' => 'clickmeeting',
                'clickmeeting_event_id' => $eventId !== '' ? $eventId : null,
                'access_type' => $clickMeetingResult['access_type'] ?? null,
                'room_url' => $clickMeetingResult['room_url'] ?? null,
                'token' => $clickMeetingResult['token'] ?? null,
                'status' => $clickMeetingResult['status'] ?? null,
                'message' => $clickMeetingResult['detail'] ?? null,
                'synced_at' => now(),
                'expires_at' => $this->resolveCourseEndExpiry($course),
            ]
        );
    }

    /**
     * Snapshot statusu kroku ClickMeeting na zamówieniu (widok form-orders).
     *
     * @param  array{status?: string, detail?: string}  $clickMeetingResult
     */
    public function syncFormOrderClickMeetingSnapshot(int $formOrderId, array $clickMeetingResult): void
    {
        try {
            FormOrder::query()->whereKey($formOrderId)->update([
                'pnedu_clickmeeting_status' => $clickMeetingResult['status'] ?? null,
                'pnedu_clickmeeting_synced_at' => now(),
                'pnedu_clickmeeting_message' => $clickMeetingResult['detail'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('ParticipantLiveAccessService: błąd zapisu snapshotu ClickMeeting na form_orders', [
                'form_order_id' => $formOrderId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function deleteForParticipant(int $participantId): void
    {
        ParticipantLiveAccess::query()->where('participant_id', $participantId)->delete();
    }

    public function cleanupExpiredRecords(): int
    {
        return ParticipantLiveAccess::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Payload zgodny z PneduProvisionEmailContextBuilder (clickMeetingResult).
     *
     * @return array{status?: string, room_url?: string|null, token?: string|null, access_type?: int|null}
     */
    public function toEmailClickMeetingPayload(?ParticipantLiveAccess $access): array
    {
        if ($access === null) {
            return [];
        }

        return [
            'status' => $access->status,
            'room_url' => $access->room_url,
            'token' => $access->token,
            'access_type' => $access->access_type,
        ];
    }

    public function resolveCourseEndExpiry(Course $course): ?Carbon
    {
        if ($course->end_date !== null) {
            return $course->end_date->copy();
        }

        if ($course->start_date !== null) {
            return $course->start_date->copy();
        }

        return null;
    }

    /**
     * @return array{
     *   success: true,
     *   status: string,
     *   detail: string,
     *   token?: string|null,
     *   room_url?: string|null,
     *   access_type?: int|null,
     *   warning?: string
     * }
     */
    private function buildSuccessfulClickMeetingResult(
        ClickMeetingService $clickMeetingService,
        string $eventId,
        string $email
    ): array {
        $detail = 'Uczestnik został dodany do ClickMeeting (event_id: '.$eventId.').';
        $token = null;
        $warning = null;
        $roomUrl = null;
        $accessType = null;

        $conference = $clickMeetingService->getConference($eventId);
        if ($conference['success'] ?? false) {
            $accessType = isset($conference['access_type']) ? (int) $conference['access_type'] : null;
            $roomUrl = $clickMeetingService->extractRoomUrl($conference['conference'] ?? []);

            if ($accessType === ClickMeetingService::ACCESS_TYPE_TOKEN) {
                $tokenResult = $clickMeetingService->getAccessTokenForEmail($eventId, $email);
                if ($tokenResult['success'] ?? false) {
                    $token = trim((string) ($tokenResult['token'] ?? ''));
                    if ($token !== '') {
                        $detail .= ' Pobrano token dostępu.';
                    }
                } else {
                    $warning = 'Uwaga: uczestnik dodany do ClickMeeting, ale nie udało się pobrać tokenu dostępu. '
                        .($tokenResult['error'] ?? '');
                    Log::warning('ParticipantLiveAccessService: ClickMeeting token fetch failed', [
                        'event_id' => $eventId,
                        'email' => $email,
                        'error' => $tokenResult['error'] ?? 'unknown',
                    ]);
                }
            }
        }

        $payload = [
            'success' => true,
            'status' => 'success',
            'detail' => $detail,
            'token' => $token !== '' ? $token : null,
            'room_url' => $roomUrl,
            'access_type' => $accessType,
        ];

        if ($warning !== null) {
            $payload['warning'] = $warning;
        }

        return $payload;
    }
}
