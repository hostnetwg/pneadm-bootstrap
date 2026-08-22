<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\Instructor;
use App\Models\Participant;
use App\Models\PneduUser;
use App\Notifications\PneduFormOrderProvisionedExistingUser;
use App\Notifications\PneduFormOrderProvisionedNewUser;
use App\Support\PneduProvisionLiveAccessContext;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class FormOrderPneduProvisionService
{
    /**
     * Czy krok 2 (ClickMeeting) liczy się jako pełny sukces w pasku X/3.
     * Pominięcie „inna platforma” = OK; błąd rejestracji / brak tokenu / brak event ID = nie-OK.
     */
    public static function isClickMeetingStepOk(
        ?string $status,
        ?string $token = null,
        ?int $accessType = null,
        bool $hasWarning = false
    ): bool {
        if ($hasWarning) {
            return false;
        }

        $status = $status !== null ? trim($status) : '';

        if ($status === 'skipped_not_clickmeeting') {
            return true;
        }

        if (in_array($status, ['failed', 'token_missing', 'skipped_missing_event_id'], true)) {
            return false;
        }

        if ($status === 'success') {
            if ($accessType === \App\Services\ClickMeetingService::ACCESS_TYPE_TOKEN
                && ! filled(trim((string) $token))) {
                return false;
            }

            return true;
        }

        // Brak statusu / nieznany — nie uznajemy za pełny sukces (operator widzi szczegóły).
        return false;
    }

    /**
     * Provision jednego uczestnika zamówienia (domyślnie główny; opcjonalnie po ID wiersza).
     *
     * @return array{success: bool, error?: string, message?: string, http_code: int, email_warning?: string, clickmeeting_warning?: string}
     */
    public function provision(int $formOrderId, bool $addParticipantToSendy = false, ?int $formOrderParticipantId = null): array
    {
        $emailWarning = null;
        $clickMeetingWarning = null;
        $sendyWarning = null;

        try {
            $afterCommit = null;

            $payload = DB::connection('mysql')->transaction(function () use ($formOrderId, $formOrderParticipantId, &$afterCommit) {
                $order = FormOrder::with(['primaryParticipant', 'participants'])->lockForUpdate()->find($formOrderId);

                if (! $order) {
                    return ['success' => false, 'error' => 'Zamówienie nie zostało znalezione.', 'http_code' => 404];
                }

                $course = Course::query()->with(['instructor', 'onlineDetails'])->find($order->product_id);
                if (! $course) {
                    return ['success' => false, 'error' => 'Nie znaleziono szkolenia (kursu) dla product_id tego zamówienia.', 'http_code' => 400];
                }

                $p = $this->resolveFormOrderParticipant($order, $formOrderParticipantId);
                if (! $p) {
                    return [
                        'success' => false,
                        'error' => $formOrderParticipantId
                            ? 'Nie znaleziono wskazanego uczestnika w tym zamówieniu.'
                            : 'Brak uczestnika w zamówieniu (form_order_participants).',
                        'http_code' => 400,
                    ];
                }

                $ops = app(FormOrderOperationalStatusService::class);
                if ($ops->isParticipantProvisioned($p, (int) $course->id)) {
                    return [
                        'success' => false,
                        'error' => 'Ten uczestnik ma już dostęp PNEDU do szkolenia.',
                        'http_code' => 400,
                    ];
                }

                $emailRaw = trim((string) ($p->participant_email ?? ''));
                $email = strtolower($emailRaw);
                $firstName = trim((string) ($p->participant_firstname ?? ''));
                $lastName = trim((string) ($p->participant_lastname ?? ''));

                if ($email === '' || ! str_contains($email, '@')) {
                    return ['success' => false, 'error' => 'Brak prawidłowego e-maila uczestnika (form_order_participants).', 'http_code' => 400];
                }

                if ($firstName === '' || $lastName === '') {
                    return ['success' => false, 'error' => 'Brak imienia lub nazwiska uczestnika (form_order_participants).', 'http_code' => 400];
                }

                $existingParticipant = Participant::query()
                    ->where('course_id', $course->id)
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                    ->first();

                ['existed' => $userExisted] = $this->findOrCreatePneduUser($email, $firstName, $lastName);

                $reusedParticipant = $existingParticipant !== null;

                if ($reusedParticipant) {
                    // Po „Wycofaj dostęp PNEDU” bez usunięcia uczestnik zostaje na liście szkolenia —
                    // ponowne dodanie odtwarza powiązanie i status, bez duplikatu w participants.
                    $courseParticipant = $existingParticipant;
                } else {
                    $birthData = $this->copyBirthDataFromPreviousParticipant($email);
                    $variant = null;
                    if ($order->course_price_variant_id) {
                        $variant = CoursePriceVariant::query()
                            ->where('id', $order->course_price_variant_id)
                            ->where('course_id', $course->id)
                            ->first();
                    }
                    $accessExpiresAt = app(ParticipantAccessExpiryService::class)
                        ->resolveAccessExpiresAtForFormOrderProvisioning(
                            $variant,
                            $course,
                            now(),
                            $order->order_date,
                            $order->submission_source === FormOrder::SUBMISSION_SOURCE_PNEDU_ORDER_FORM
                        );

                    $courseParticipant = Participant::query()->create([
                        'course_id' => $course->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $emailRaw !== '' ? $emailRaw : $email,
                        'birth_date' => $birthData['birth_date'],
                        'birth_place' => $birthData['birth_place'],
                        'order' => Participant::query()->where('course_id', $course->id)->count() + 1,
                        'access_expires_at' => $accessExpiresAt,
                    ]);
                }

                if ($p) {
                    $p->participant_id = $courseParticipant->id;
                    $p->save();
                }

                $order->load('participants');
                $this->syncOrderPneduProvisionedFlag($order, (int) $course->id, $userExisted);
                if (! $order->save()) {
                    return ['success' => false, 'error' => 'Nie udało się zapisać statusu PNEDU przy zamówieniu.', 'http_code' => 500];
                }

                $afterCommit = [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'user_existed' => $userExisted,
                    'participant_id' => (int) $courseParticipant->id,
                    'form_order_participant_id' => (int) $p->id,
                    'course_title' => (string) $course->title,
                    'course_id' => (int) $course->id,
                    'platform' => trim((string) optional($course->onlineDetails)->platform),
                    'clickmeeting_event_id' => trim((string) optional($course->onlineDetails)->clickmeeting_event_id),
                    'instructor_line' => $this->instructorLineForProvisionEmail($course->instructor),
                    'start_date_line' => $this->startDateLineForProvisionEmail($course),
                    'reused_participant' => $reusedParticipant,
                ];

                $provisionedAtLabel = $order->pnedu_provisioned_at
                    ? $order->pnedu_provisioned_at->timezone('Europe/Warsaw')->format('d.m.Y H:i')
                    : now()->timezone('Europe/Warsaw')->format('d.m.Y H:i');

                return [
                    'success' => true,
                    'message' => $reusedParticipant
                        ? 'Status PNEDU odtworzony — powiązano istniejącego uczestnika szkolenia. Wysłano wiadomość e-mail.'
                        : 'Uczestnik dodany, konto PNEDU obsłużone. Wysłano wiadomość e-mail do uczestnika.',
                    'http_code' => 200,
                    'provisioned_at' => $provisionedAtLabel,
                    'user_existed' => $userExisted,
                    'reused_participant' => $reusedParticipant,
                    'form_order_participant_id' => (int) $p->id,
                ];
            });

            if (! ($payload['success'] ?? false) || $afterCommit === null) {
                return $payload;
            }

            $pneduUser = PneduUser::query()
                ->where('email', $afterCommit['email'])
                ->first();

            if (! $pneduUser) {
                Log::error('FormOrderPneduProvisionService: brak PneduUser po provision', ['email' => $afterCommit['email'], 'form_order_id' => $formOrderId]);

                return array_merge($payload, [
                    'success' => true,
                    'message' => 'Uczestnik i konto PNEDU zapisane. Uwaga: nie znaleziono rekordu użytkownika w bazie PNEDU do wysyłki e-maila.',
                    'email_warning' => 'Nie wysłano e-maila — brak użytkownika w bazie pnedu.',
                    'ok_steps' => 1,
                    'total_steps' => 3,
                ]);
            }

            $clickMeetingResult = $this->provisionClickMeetingIfConfigured($formOrderId, $afterCommit);
            if (! empty($clickMeetingResult['warning'])) {
                $clickMeetingWarning = $clickMeetingResult['warning'];
            }

            $orderForSendy = FormOrder::query()->with(['primaryParticipant', 'course', 'participants'])->find($formOrderId);
            $fopForSendy = isset($afterCommit['form_order_participant_id'])
                ? FormOrderParticipant::query()->find((int) $afterCommit['form_order_participant_id'])
                : $orderForSendy?->primaryParticipant;
            $includeParticipantInSendy = $orderForSendy && $fopForSendy
                ? $this->shouldIncludeParticipantInSendy($orderForSendy, $addParticipantToSendy, $fopForSendy)
                : true;
            $sendyResult = $orderForSendy && $fopForSendy
                ? app(FormOrderSendySyncService::class)->syncOrderWithParticipant(
                    $orderForSendy,
                    $fopForSendy,
                    $includeParticipantInSendy
                )
                : app(FormOrderSendySyncService::class)->syncByFormOrderId(
                    $formOrderId,
                    $includeParticipantInSendy
                );
            if (($sendyResult['failed'] ?? 0) > 0) {
                $sendyWarning = 'Uwaga: nie wszystkie kontakty zostały dodane do listy Sendy.';
                Log::warning('FormOrderPneduProvisionService: problem sync Sendy', [
                    'form_order_id' => $formOrderId,
                    'sendy_result' => $sendyResult,
                ]);
            }

            $course = Course::query()->with('onlineDetails')->find($afterCommit['course_id']);
            $liveAccess = $course
                ? app(PneduProvisionEmailContextBuilder::class)->build(
                    $course,
                    $clickMeetingResult,
                    (int) ($afterCommit['participant_id'] ?? 0)
                )
                : new PneduProvisionLiveAccessContext;

            try {
                if ($afterCommit['user_existed']) {
                    $pneduUser->notify(new PneduFormOrderProvisionedExistingUser(
                        $afterCommit['course_title'],
                        $afterCommit['instructor_line'] ?? null,
                        $afterCommit['start_date_line'] ?? null,
                        $liveAccess,
                    ));
                } else {
                    $token = Password::broker('pnedu_users')->createToken($pneduUser);
                    $pneduUser->notify(new PneduFormOrderProvisionedNewUser(
                        $token,
                        $afterCommit['course_title'],
                        $afterCommit['instructor_line'] ?? null,
                        $afterCommit['start_date_line'] ?? null,
                        $liveAccess,
                    ));
                }
            } catch (Throwable $e) {
                Log::error('FormOrderPneduProvisionService: błąd wysyłki e-maila', [
                    'form_order_id' => $formOrderId,
                    'email' => $afterCommit['email'],
                    'exception' => $e->getMessage(),
                ]);
                $emailWarning = 'Dane zapisano, ale wysłanie e-maila nie powiodło się: '.$e->getMessage();
            }

            if ($emailWarning !== null) {
                $payload['message'] = ($payload['message'] ?? '').' '.$emailWarning;
                $payload['email_warning'] = $emailWarning;
            }

            if ($clickMeetingWarning !== null) {
                $payload['message'] = ($payload['message'] ?? '').' '.$clickMeetingWarning;
                $payload['clickmeeting_warning'] = $clickMeetingWarning;
            }

            if ($sendyWarning !== null) {
                $payload['message'] = ($payload['message'] ?? '').' '.$sendyWarning;
                $payload['sendy_warning'] = $sendyWarning;
            }

            $orderFresh = FormOrder::query()->find($formOrderId);
            $participantFresh = Participant::query()
                ->with('liveAccess')
                ->find($afterCommit['participant_id'] ?? 0);
            $cmStatus = $participantFresh?->liveAccess?->status
                ?? $orderFresh?->pnedu_clickmeeting_status;
            $cmToken = $participantFresh?->liveAccess?->token;
            $cmAccessType = $participantFresh?->liveAccess?->access_type !== null
                ? (int) $participantFresh->liveAccess->access_type
                : null;
            $step2Ok = self::isClickMeetingStepOk(
                $cmStatus,
                $cmToken,
                $cmAccessType,
                $clickMeetingWarning !== null
            );
            $step3Ok = $emailWarning === null;
            $okSteps = 1 + ($step2Ok ? 1 : 0) + ($step3Ok ? 1 : 0);

            $payload['ok_steps'] = $okSteps;
            $payload['total_steps'] = 3;
            $payload['steps'] = [
                'participant' => ['ok' => true],
                'clickmeeting' => [
                    'ok' => $step2Ok,
                    'status' => $cmStatus,
                    'message' => $participantFresh?->liveAccess?->message
                        ?? $orderFresh?->pnedu_clickmeeting_message,
                    'token' => $participantFresh?->liveAccess?->token,
                ],
                'email' => ['ok' => $step3Ok],
            ];

            Log::info('FormOrderPneduProvisionService: sukces', [
                'form_order_id' => $formOrderId,
                'email' => $afterCommit['email'],
                'user_existed' => $afterCommit['user_existed'],
                'form_order_participant_id' => $afterCommit['form_order_participant_id'] ?? null,
                'ok_steps' => $okSteps,
            ]);

            return $payload;
        } catch (Throwable $e) {
            Log::error('FormOrderPneduProvisionService: wyjątek', [
                'form_order_id' => $formOrderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Wystąpił błąd: '.$e->getMessage(),
                'http_code' => 500,
            ];
        }
    }

    /**
     * Provision wszystkich nieobsłużonych uczestników zamówienia.
     *
     * @param  array<int, bool>  $addToSendyByFopId  mapa form_order_participant_id => czy dodać do Sendy
     * @return array{success: bool, error?: string, message?: string, http_code: int, provisioned?: int, failed?: int, results?: list<array>}
     */
    public function provisionAll(int $formOrderId, bool $addParticipantToSendy = false, array $addToSendyByFopId = []): array
    {
        $order = FormOrder::with('participants')->find($formOrderId);
        if (! $order) {
            return ['success' => false, 'error' => 'Zamówienie nie zostało znalezione.', 'http_code' => 404];
        }

        $courseId = app(FormOrderOperationalStatusService::class)->resolveCourseId($order);
        if ($courseId === null) {
            return ['success' => false, 'error' => 'Nie znaleziono szkolenia dla tego zamówienia.', 'http_code' => 400];
        }

        $ops = app(FormOrderOperationalStatusService::class);
        $targets = $ops->activeOrderParticipants($order)
            ->filter(fn (FormOrderParticipant $fop) => ! $ops->isParticipantProvisioned($fop, $courseId))
            ->values();

        if ($targets->isEmpty()) {
            return [
                'success' => false,
                'error' => 'Wszyscy uczestnicy mają już dostęp PNEDU.',
                'http_code' => 400,
            ];
        }

        $results = [];
        $ok = 0;
        $failed = 0;
        foreach ($targets as $fop) {
            $fopId = (int) $fop->id;
            $sendyForThis = array_key_exists($fopId, $addToSendyByFopId)
                ? (bool) $addToSendyByFopId[$fopId]
                : $addParticipantToSendy;
            $result = $this->provision($formOrderId, $sendyForThis, $fopId);
            $results[] = [
                'form_order_participant_id' => $fopId,
                'email' => $fop->participant_email,
                'success' => (bool) ($result['success'] ?? false),
                'error' => $result['error'] ?? null,
                'message' => $result['message'] ?? null,
            ];
            if ($result['success'] ?? false) {
                $ok++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $ok > 0,
            'message' => $failed === 0
                ? "Dodano wszystkich uczestników do PNEDU ({$ok})."
                : "Dodano {$ok} z ".($ok + $failed).' uczestników. Nieudane: '.$failed.'.',
            'http_code' => $ok > 0 ? 200 : 400,
            'provisioned' => $ok,
            'failed' => $failed,
            'results' => $results,
            'error' => $ok === 0 ? 'Nie udało się dodać żadnego uczestnika.' : null,
        ];
    }

    private function resolveFormOrderParticipant(FormOrder $order, ?int $formOrderParticipantId): ?FormOrderParticipant
    {
        if ($formOrderParticipantId) {
            return FormOrderParticipant::query()
                ->where('form_order_id', $order->id)
                ->where('id', $formOrderParticipantId)
                ->whereNull('deleted_at')
                ->first();
        }

        return $order->primaryParticipant
            ?? $order->participants->first(fn (FormOrderParticipant $p) => $p->deleted_at === null);
    }

    private function syncOrderPneduProvisionedFlag(FormOrder $order, int $courseId, bool $lastUserExisted): void
    {
        $ops = app(FormOrderOperationalStatusService::class);
        $active = $ops->activeOrderParticipants($order);
        $allDone = $active->isNotEmpty()
            && $active->every(fn (FormOrderParticipant $fop) => $ops->isParticipantProvisioned($fop, $courseId));

        if ($allDone) {
            $order->pnedu_provisioned_at = $order->pnedu_provisioned_at ?? now();
            $order->pnedu_user_existed_before = $lastUserExisted;
        } else {
            $order->pnedu_provisioned_at = null;
        }
    }

    /**
     * Podgląd e-maila z kroku 3 provisionu (bez wysyłki).
     *
     * @return array{success: bool, error?: string, http_code: int, to?: string, subject?: string, body?: string, body_html?: string, variant?: string, variant_label?: string, participant_name?: string, form_order_participant_id?: int}
     */
    public function previewProvisionAccessEmail(int $formOrderId, ?int $formOrderParticipantId = null): array
    {
        try {
            // Bez wymogu konta pnedu — podgląd nie może wisieć na drugim połączeniu DB.
            $resolved = $this->resolveProvisionAccessEmailContext(
                $formOrderId,
                requirePneduUser: false,
                formOrderParticipantId: $formOrderParticipantId
            );
            if (! ($resolved['success'] ?? false)) {
                return $resolved;
            }

            $notification = $this->makeProvisionAccessNotification(
                $resolved,
                previewPlaceholderToken: true
            );
            $mail = $notification->toMail($this->mailNotifiableStub($resolved['email']));

            return [
                'success' => true,
                'http_code' => 200,
                'to' => $resolved['email'],
                'subject' => (string) ($mail->subject ?? ''),
                'body' => $this->mailMessageToPlainText($mail),
                'body_html' => $this->mailMessageToHtml($mail),
                'variant' => $resolved['is_new_account'] ? 'new_user' : 'existing_user',
                'variant_label' => $resolved['is_new_account']
                    ? 'E-mail z linkiem do ustawienia hasła (konto bez hasła)'
                    : 'E-mail informacyjny (konto aktywne)',
                'participant_name' => $resolved['participant_name'] ?? null,
                'form_order_participant_id' => $resolved['form_order_participant_id'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('FormOrderPneduProvisionService: błąd podglądu e-maila dostępu', [
                'form_order_id' => $formOrderId,
                'form_order_participant_id' => $formOrderParticipantId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Nie udało się przygotować podglądu: '.$e->getMessage(),
                'http_code' => 500,
            ];
        }
    }

    /**
     * Ponowna wysyłka e-maila z kroku 3 provisionu.
     *
     * @return array{success: bool, error?: string, message?: string, http_code: int}
     */
    public function resendProvisionAccessEmail(int $formOrderId, ?int $formOrderParticipantId = null): array
    {
        $resolved = $this->resolveProvisionAccessEmailContext(
            $formOrderId,
            requirePneduUser: true,
            formOrderParticipantId: $formOrderParticipantId
        );
        if (! ($resolved['success'] ?? false)) {
            return $resolved;
        }

        try {
            $notification = $this->makeProvisionAccessNotification(
                $resolved,
                previewPlaceholderToken: false
            );
            $resolved['pnedu_user']->notify($notification);

            Log::info('FormOrderPneduProvisionService: ponowna wysyłka e-maila dostępu', [
                'form_order_id' => $formOrderId,
                'form_order_participant_id' => $resolved['form_order_participant_id'] ?? null,
                'email' => $resolved['email'],
                'variant' => $resolved['is_new_account'] ? 'new_user' : 'existing_user',
            ]);

            return [
                'success' => true,
                'http_code' => 200,
                'message' => $resolved['is_new_account']
                    ? 'Wysłano e-mail z linkiem do ustawienia hasła.'
                    : 'Wysłano e-mail informacyjny na adres uczestnika.',
                'to' => $resolved['email'],
                'participant_name' => $resolved['participant_name'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('FormOrderPneduProvisionService: błąd ponownej wysyłki e-maila', [
                'form_order_id' => $formOrderId,
                'form_order_participant_id' => $formOrderParticipantId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Nie udało się wysłać e-maila: '.$e->getMessage(),
                'http_code' => 500,
            ];
        }
    }

    /**
     * @return array{
     *     success: bool,
     *     error?: string,
     *     http_code: int,
     *     email?: string,
     *     pnedu_user?: PneduUser|null,
     *     course_title?: string,
     *     instructor_line?: ?string,
     *     start_date_line?: ?string,
     *     live_access?: PneduProvisionLiveAccessContext,
     *     is_new_account?: bool,
     *     participant_name?: string,
     *     form_order_participant_id?: int
     * }
     */
    private function resolveProvisionAccessEmailContext(
        int $formOrderId,
        bool $requirePneduUser = true,
        ?int $formOrderParticipantId = null
    ): array {
        $order = FormOrder::with([
            'primaryParticipant.participant.liveAccess',
            'participants.participant.liveAccess',
            'course.instructor',
            'course.onlineDetails',
        ])->find($formOrderId);

        if (! $order) {
            return ['success' => false, 'error' => 'Zamówienie nie zostało znalezione.', 'http_code' => 404];
        }

        $p = $this->resolveFormOrderParticipant($order, $formOrderParticipantId);
        if (! $p) {
            return [
                'success' => false,
                'error' => $formOrderParticipantId
                    ? 'Nie znaleziono wskazanego uczestnika w tym zamówieniu.'
                    : 'Brak uczestnika w zamówieniu (form_order_participants).',
                'http_code' => 400,
            ];
        }

        $course = $order->course ?? Course::query()->with(['instructor', 'onlineDetails'])->find($order->product_id);
        if (! $course) {
            return [
                'success' => false,
                'error' => 'Nie znaleziono szkolenia dla tego zamówienia.',
                'http_code' => 400,
            ];
        }

        $ops = app(FormOrderOperationalStatusService::class);
        $isThisProvisioned = $ops->isParticipantProvisioned($p, (int) $course->id);
        $allowLegacyPrimary = $order->pnedu_provisioned_at !== null
            && ($formOrderParticipantId === null || (bool) $p->is_primary);

        if (! $isThisProvisioned && ! $allowLegacyPrimary) {
            return [
                'success' => false,
                'error' => $order->pnedu_provisioned_at === null
                    ? 'Najpierw przyznaj dostęp PNEDU temu uczestnikowi — e-mail z dostępem dotyczy już provisionowanej osoby.'
                    : 'Ten uczestnik nie ma jeszcze dostępu PNEDU do szkolenia.',
                'http_code' => 400,
            ];
        }

        $emailRaw = trim((string) ($p->participant_email ?? ''));
        $email = strtolower($emailRaw);
        if ($email === '' || ! str_contains($email, '@')) {
            return [
                'success' => false,
                'error' => 'Brak prawidłowego e-maila uczestnika.',
                'http_code' => 400,
            ];
        }

        $pneduUser = null;
        try {
            $pneduUser = PneduUser::query()->where('email', $email)->first();
        } catch (Throwable $e) {
            if ($requirePneduUser) {
                Log::error('FormOrderPneduProvisionService: błąd odczytu użytkownika pnedu', [
                    'form_order_id' => $formOrderId,
                    'email' => $email,
                    'exception' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Nie udało się odczytać konta użytkownika w bazie pnedu.',
                    'http_code' => 500,
                ];
            }
        }

        if ($requirePneduUser && ! $pneduUser) {
            return [
                'success' => false,
                'error' => 'Brak konta użytkownika w bazie pnedu dla tego e-maila.',
                'http_code' => 400,
            ];
        }

        $participant = $p->participant;
        $clickMeetingResult = $this->clickMeetingResultFromLiveAccess($participant?->liveAccess);
        $liveAccess = app(PneduProvisionEmailContextBuilder::class)->build(
            $course,
            $clickMeetingResult,
            $participant?->id
        );

        $participantName = trim((string) ($p->full_name ?? ''));
        if ($participantName === '') {
            $participantName = trim($p->participant_firstname.' '.$p->participant_lastname);
        }

        return [
            'success' => true,
            'http_code' => 200,
            'email' => $email,
            'pnedu_user' => $pneduUser,
            'course_title' => (string) $course->title,
            'instructor_line' => $this->instructorLineForProvisionEmail($course->instructor),
            'start_date_line' => $this->startDateLineForProvisionEmail($course),
            'live_access' => $liveAccess,
            // Podgląd i wysyłka: wariant z bieżącego stanu konta pnedu (gdy dostępne).
            'is_new_account' => $this->resolveIsNewAccountForResend($order, $pneduUser),
            'participant_name' => $participantName !== '' ? $participantName : $email,
            'form_order_participant_id' => (int) $p->id,
        ];
    }

    /**
     * Wariant maila przy ponownej wysyłce — stan konta pnedu w chwili wysyłki / podglądu.
     */
    private function resolveIsNewAccountForResend(FormOrder $order, ?PneduUser $pneduUser): bool
    {
        if ($pneduUser !== null) {
            return $pneduUser->needsProvisionPasswordSetupEmail($order);
        }

        if ($order->pnedu_user_existed_before === true) {
            return false;
        }

        return true;
    }

    private function mailNotifiableStub(string $email): object
    {
        return new class($email)
        {
            public function __construct(private string $email) {}

            public function getEmailForPasswordReset(): string
            {
                return $this->email;
            }

            public function routeNotificationFor($channel = null): string
            {
                return $this->email;
            }
        };
    }

    /**
     * @param  array{
     *     pnedu_user: PneduUser,
     *     course_title: string,
     *     instructor_line: ?string,
     *     start_date_line: ?string,
     *     live_access: PneduProvisionLiveAccessContext,
     *     is_new_account: bool,
     *     email: string
     * }  $resolved
     */
    private function makeProvisionAccessNotification(array $resolved, bool $previewPlaceholderToken): PneduFormOrderProvisionedNewUser|PneduFormOrderProvisionedExistingUser
    {
        if (! $resolved['is_new_account']) {
            return new PneduFormOrderProvisionedExistingUser(
                $resolved['course_title'],
                $resolved['instructor_line'],
                $resolved['start_date_line'],
                $resolved['live_access'],
            );
        }

        $token = $previewPlaceholderToken
            ? 'PODGLAD-LINK-WYGENEROWANY-PRZY-WYSYLCE'
            : Password::broker('pnedu_users')->createToken($resolved['pnedu_user']);

        return new PneduFormOrderProvisionedNewUser(
            $token,
            $resolved['course_title'],
            $resolved['instructor_line'],
            $resolved['start_date_line'],
            $resolved['live_access'],
        );
    }

    private function clickMeetingResultFromLiveAccess(?\App\Models\ParticipantLiveAccess $liveAccess): ?array
    {
        if (! $liveAccess) {
            return null;
        }

        $roomUrl = trim((string) ($liveAccess->room_url ?? ''));
        $token = trim((string) ($liveAccess->token ?? ''));
        if ($roomUrl === '' && $token === '') {
            return null;
        }

        return [
            'success' => true,
            'status' => 'success',
            'token' => $token !== '' ? $token : null,
            'room_url' => $roomUrl !== '' ? $roomUrl : null,
            'access_type' => $liveAccess->access_type,
        ];
    }

    private function mailMessageToHtml(MailMessage $mail): string
    {
        $theme = $mail->theme ?? 'default';
        $view = $mail->markdown ?? 'mail::message';

        return (string) (new Markdown(view(), config('mail.markdown')))
            ->theme($theme)
            ->render($view, $mail->data());
    }

    private function mailMessageToPlainText(MailMessage $mail): string
    {
        $lines = [];

        if (filled($mail->greeting)) {
            $lines[] = $this->plainTextFromMailLine($mail->greeting);
            $lines[] = '';
        }

        foreach ($mail->introLines as $line) {
            $text = $this->plainTextFromMailLine($line);
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        if (filled($mail->actionText) && filled($mail->actionUrl)) {
            $lines[] = '';
            $lines[] = (string) $mail->actionText.':';
            $lines[] = (string) $mail->actionUrl;
        }

        foreach ($mail->outroLines as $line) {
            $text = $this->plainTextFromMailLine($line);
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        if (filled($mail->salutation)) {
            $lines[] = '';
            $lines[] = $this->plainTextFromMailLine($mail->salutation);
        }

        return trim(implode("\n", $lines));
    }

    private function plainTextFromMailLine(mixed $line): string
    {
        $raw = (string) $line;
        $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw) ?? $raw;
        $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[ \t]+/u", ' ', $raw) ?? $raw);
    }

    private function shouldIncludeParticipantInSendy(
        FormOrder $order,
        bool $addParticipantToSendy,
        ?FormOrderParticipant $fop = null
    ): bool {
        $fop = $fop ?? $order->primaryParticipant;
        $participantEmail = strtolower(trim((string) ($fop?->participant_email ?? $order->display_participant_email ?? '')));
        $ordererEmail = strtolower(trim((string) ($order->orderer_email ?? '')));

        if ($participantEmail === '' || $ordererEmail === '') {
            return true;
        }

        if ($participantEmail === $ordererEmail) {
            return true;
        }

        return $addParticipantToSendy;
    }

    /**
     * @return array{existed: bool}
     */
    private function findOrCreatePneduUser(string $email, string $firstName, string $lastName): array
    {
        return DB::connection('pnedu')->transaction(function () use ($email, $firstName, $lastName) {
            $existing = PneduUser::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return ['existed' => true];
            }

            try {
                PneduUser::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'email_unique_slot' => PneduUser::buildEmailUniqueSlot($email, null),
                    'password' => Hash::make(Str::password(48)),
                    'email_verified_at' => now(),
                ]);

                return ['existed' => false];
            } catch (QueryException $e) {
                if (! $this->isPneduUserEmailUniqueViolation($e)) {
                    throw $e;
                }

                return ['existed' => true];
            }
        });
    }

    private function isPneduUserEmailUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }

    /**
     * @return array{birth_date: ?\Carbon\Carbon, birth_place: ?string}
     */
    private function copyBirthDataFromPreviousParticipant(string $email): array
    {
        $previous = Participant::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
            ->where(function ($q) {
                $q->whereNotNull('birth_date')->orWhereNotNull('birth_place');
            })
            ->orderByDesc('created_at')
            ->first();

        if (! $previous) {
            return ['birth_date' => null, 'birth_place' => null];
        }

        return [
            'birth_date' => $previous->birth_date,
            'birth_place' => $previous->birth_place ? (string) $previous->birth_place : null,
        ];
    }

    /**
     * Etykieta + tytuł zawodowy i imię oraz nazwisko prowadzącego (jak na pnedu.pl).
     */
    private function instructorLineForProvisionEmail(?Instructor $instructor): ?string
    {
        if (! $instructor) {
            return null;
        }

        $gender = strtolower((string) ($instructor->gender ?? ''));
        $label = match ($gender) {
            'female', 'f', 'kobieta' => 'Prowadząca',
            default => 'Prowadzący',
        };

        return $label.': '.$instructor->full_title_name;
    }

    /**
     * Data rozpoczęcia — tylko gdy szkolenie nie jest uznane za zakończone (end_date w przeszłości).
     * Po zakończeniu na żywo (np. samo nagranie) daty nie pokazujemy.
     */
    private function startDateLineForProvisionEmail(Course $course): ?string
    {
        if (! $course->start_date) {
            return null;
        }

        if ($course->end_date && $course->end_date->isPast()) {
            return null;
        }

        // Bez end_date: jeśli start już minął, nie pokazujemy terminu (np. dostęp tylko do nagrania po szkoleniu).
        if (! $course->end_date && $course->start_date->isPast()) {
            return null;
        }

        $formatted = $course->start_date->copy()->timezone(config('app.timezone'))->format('d.m.Y G:i');

        return 'Data rozpoczęcia: '.$formatted;
    }

    /**
     * @param  array<string, mixed>  $afterCommit
     * @return array<string, mixed>
     */
    private function provisionClickMeetingIfConfigured(int $formOrderId, array $afterCommit): array
    {
        $participant = Participant::query()->find($afterCommit['participant_id'] ?? 0);
        $course = Course::query()->with('onlineDetails')->find($afterCommit['course_id'] ?? 0);

        if (! $participant || ! $course) {
            $result = [
                'success' => false,
                'status' => 'failed',
                'detail' => 'Nie znaleziono uczestnika lub kursu do integracji ClickMeeting.',
            ];
            app(ParticipantLiveAccessService::class)->syncFormOrderClickMeetingSnapshot($formOrderId, $result);

            return $result;
        }

        $liveAccessService = app(ParticipantLiveAccessService::class);
        $result = $liveAccessService->provisionClickMeetingForParticipant(
            $participant,
            $course,
            $formOrderId
        );
        $liveAccessService->syncFormOrderClickMeetingSnapshot($formOrderId, $result);

        return $result;
    }

    /**
     * Cofa status PNEDU dla wskazanego uczestnika (domyślnie główny — kompatybilność).
     * Zawsze czyści flagi PNEDU na zamówieniu; opcjonalnie soft-delete uczestnika szkolenia.
     *
     * @return array{
     *     success: bool,
     *     error?: string,
     *     message?: string,
     *     http_code: int,
     *     warnings?: list<string>,
     *     removed_participant?: bool,
     *     token_invalidated?: bool,
     *     form_order_participant_id?: int|null
     * }
     */
    public function resetStatus(
        FormOrder $order,
        bool $removeParticipant,
        ?int $formOrderParticipantId = null
    ): array {
        $warnings = [];
        $removedParticipant = false;
        $tokenInvalidated = false;

        $order->loadMissing(['participants.participant.liveAccess', 'primaryParticipant.participant.liveAccess', 'course']);

        $fop = $this->resolveFormOrderParticipant($order, $formOrderParticipantId);
        if (! $fop) {
            return [
                'success' => false,
                'error' => $formOrderParticipantId
                    ? 'Nie znaleziono wskazanego uczestnika w tym zamówieniu.'
                    : 'Brak uczestnika w zamówieniu.',
                'http_code' => 400,
            ];
        }

        $participantName = trim((string) ($fop->full_name ?? ''));
        if ($participantName === '') {
            $participantName = trim($fop->participant_firstname.' '.$fop->participant_lastname);
        }
        $participantEmail = trim((string) ($fop->participant_email ?? ''));

        $participantId = $fop->participant_id;
        if ($removeParticipant && $participantId) {
            $participant = Participant::query()->find($participantId);
            if ($participant) {
                $course = $order->course ?? Course::query()->find($participant->course_id);
                $hasToken = filled(trim((string) ($participant->liveAccess?->token ?? '')));

                if ($hasToken && $course) {
                    $invalidateResult = app(ParticipantLiveAccessService::class)
                        ->invalidateClickMeetingToken($participant, $course);
                    if ($invalidateResult['success'] ?? false) {
                        $tokenInvalidated = true;
                    } else {
                        $warnings[] = (string) ($invalidateResult['detail']
                            ?? 'Nie udało się unieważnić tokenu ClickMeeting w API.');
                    }
                }

                app(ParticipantLiveAccessService::class)->deleteForParticipant((int) $participant->id);
                $participant->delete();
                $removedParticipant = true;

                $fop->participant_id = null;
                $fop->save();
            }
        }

        $order->pnedu_provisioned_at = null;
        $order->pnedu_user_existed_before = null;
        if (Schema::connection('mysql')->hasColumn('form_orders', 'pnedu_clickmeeting_status')) {
            $order->pnedu_clickmeeting_status = null;
        }
        if (Schema::connection('mysql')->hasColumn('form_orders', 'pnedu_clickmeeting_synced_at')) {
            $order->pnedu_clickmeeting_synced_at = null;
        }
        if (Schema::connection('mysql')->hasColumn('form_orders', 'pnedu_clickmeeting_message')) {
            $order->pnedu_clickmeeting_message = null;
        }

        if (! $order->save()) {
            return [
                'success' => false,
                'error' => 'Nie udało się zresetować statusu PNEDU.',
                'http_code' => 500,
            ];
        }

        $who = $participantName !== '' ? $participantName : ($participantEmail !== '' ? $participantEmail : '#'.$fop->id);
        $message = $removedParticipant
            ? "Status PNEDU zresetowany dla: {$who}. Uczestnik usunięty z listy szkolenia."
            : "Status PNEDU zresetowany dla: {$who}. Można ponownie dodać do PNEDU.";
        if ($tokenInvalidated) {
            $message .= ' Token dostępowy ClickMeeting został unieważniony.';
        }

        ActivityLog::logCustom(
            'Reset statusu PNEDU',
            "Reset statusu PNEDU dla zamówienia #{$order->id}, uczestnik FOP #{$fop->id} ({$who})"
            .($removedParticipant ? ' (usunięto uczestnika ze szkolenia)' : ' (bez usuwania uczestnika)')
            .($tokenInvalidated ? ', unieważniono token CM' : '')
            .($warnings !== [] ? '. Ostrzeżenia: '.implode(' ', $warnings) : ''),
            [
                'model_type' => FormOrder::class,
                'model_id' => $order->id,
                'model_name' => "Zamówienie #{$order->id}",
                'new_values' => [
                    'form_order_participant_id' => (int) $fop->id,
                    'remove_participant' => $removeParticipant,
                    'removed_participant' => $removedParticipant,
                    'token_invalidated' => $tokenInvalidated,
                ],
            ]
        );

        return [
            'success' => true,
            'message' => $message,
            'http_code' => 200,
            'warnings' => $warnings,
            'removed_participant' => $removedParticipant,
            'token_invalidated' => $tokenInvalidated,
            'form_order_participant_id' => (int) $fop->id,
        ];
    }

    /**
     * Reset PNEDU dla wszystkich provisionowanych uczestników zamówienia.
     *
     * @return array{
     *     success: bool,
     *     error?: string,
     *     message?: string,
     *     http_code: int,
     *     reset?: int,
     *     failed?: int,
     *     results?: list<array>
     * }
     */
    public function resetStatusAll(FormOrder $order, bool $removeParticipant): array
    {
        $order->loadMissing(['participants.participant.liveAccess', 'course']);
        $courseId = app(FormOrderOperationalStatusService::class)->resolveCourseId($order);
        $ops = app(FormOrderOperationalStatusService::class);

        $targets = $ops->activeOrderParticipants($order)->filter(function (FormOrderParticipant $fop) use ($ops, $courseId) {
            if ($courseId) {
                return $ops->isParticipantProvisioned($fop, $courseId);
            }

            return $fop->participant_id !== null;
        })->values();

        if ($targets->isEmpty()) {
            // Legacy: flaga zamówienia bez powiązań — wyczyść flagi jak dotychczasowy reset primary.
            return $this->resetStatus($order, $removeParticipant, null);
        }

        $results = [];
        $ok = 0;
        $failed = 0;
        foreach ($targets as $fop) {
            $result = $this->resetStatus($order->fresh(['participants.participant.liveAccess', 'course']), $removeParticipant, (int) $fop->id);
            $results[] = [
                'form_order_participant_id' => (int) $fop->id,
                'success' => (bool) ($result['success'] ?? false),
                'error' => $result['error'] ?? null,
                'message' => $result['message'] ?? null,
            ];
            if ($result['success'] ?? false) {
                $ok++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $ok > 0,
            'message' => $failed === 0
                ? "Zresetowano status PNEDU dla wszystkich uczestników ({$ok})."
                : "Zresetowano {$ok} z ".($ok + $failed).'. Nieudane: '.$failed.'.',
            'http_code' => $ok > 0 ? 200 : 400,
            'reset' => $ok,
            'failed' => $failed,
            'results' => $results,
            'error' => $ok === 0 ? 'Nie udało się zresetować żadnego uczestnika.' : null,
            'warnings' => [],
            'removed_participant' => $removeParticipant,
            'token_invalidated' => false,
        ];
    }
}
