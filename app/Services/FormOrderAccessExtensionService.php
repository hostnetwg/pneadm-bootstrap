<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Models\FormOrder;
use App\Models\Participant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class FormOrderAccessExtensionService
{
    public function preview(FormOrder $order): array
    {
        $order->loadMissing(['primaryParticipant', 'coursePriceVariant', 'onlinePaymentOrders']);

        $course = $this->resolveCourse($order);
        $participant = $course ? $this->findParticipantForOrder($order, $course) : null;
        $variant = $order->coursePriceVariant;

        return $this->buildPreview($order, $course, $participant, $variant);
    }

    /**
     * @return array{success: bool, error?: string, message?: string, http_code: int, participant_id?: int, previous_expires_at?: ?string, new_expires_at?: ?string}
     */
    public function extendByOrder(int $formOrderId): array
    {
        try {
            return DB::connection('mysql')->transaction(function () use ($formOrderId) {
                $order = FormOrder::query()
                    ->with(['primaryParticipant', 'coursePriceVariant', 'onlinePaymentOrders'])
                    ->lockForUpdate()
                    ->find($formOrderId);

                if (! $order) {
                    return ['success' => false, 'error' => 'Zamówienie nie zostało znalezione.', 'http_code' => 404];
                }

                $course = $this->resolveCourse($order);
                $participant = $course ? $this->findParticipantForOrder($order, $course, true) : null;
                $preview = $this->buildPreview($order, $course, $participant, $order->coursePriceVariant);

                if (! ($preview['eligible'] ?? false)) {
                    return [
                        'success' => false,
                        'error' => $preview['reason'] ?? 'To zamówienie nie kwalifikuje się do przedłużenia dostępu.',
                        'http_code' => 400,
                    ];
                }

                $previousExpiresAt = $participant->access_expires_at?->copy();
                $participant->access_expires_at = $preview['new_expires_at'];
                $participant->save();

                $order->pnedu_provisioned_at = now();
                $order->pnedu_user_existed_before = true;
                $order->notes = $this->appendExtensionNote(
                    (string) ($order->notes ?? ''),
                    $participant,
                    $previousExpiresAt,
                    $preview['new_expires_at']
                );
                $order->save();

                return [
                    'success' => true,
                    'message' => 'Dostęp uczestnika został przedłużony.',
                    'http_code' => 200,
                    'participant_id' => (int) $participant->id,
                    'previous_expires_at' => $this->formatDate($previousExpiresAt),
                    'new_expires_at' => $this->formatDate($preview['new_expires_at']),
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'success' => false,
                'error' => 'Wystąpił błąd podczas przedłużania dostępu: '.$e->getMessage(),
                'http_code' => 500,
            ];
        }
    }

    private function buildPreview(
        FormOrder $order,
        ?Course $course,
        ?Participant $participant,
        ?CoursePriceVariant $variant
    ): array
    {
        $base = [
            'eligible' => false,
            'reason' => null,
            'participant_id' => $participant?->id,
            'current_expires_at' => $participant?->access_expires_at,
            'new_expires_at' => null,
            'variant_label' => $this->variantLabel($variant),
            'payment_accepted' => $this->isPaymentAccepted($order),
            'variant_allows_extension' => $this->variantAllowsExtension($variant, $course),
        ];

        if ($course === null) {
            return array_merge($base, ['reason' => 'Brak powiązanego szkolenia w tabeli courses.']);
        }

        if ($participant === null) {
            return array_merge($base, ['reason' => 'Brak istniejącego uczestnika dla tego e-maila i szkolenia.']);
        }

        if ($order->pnedu_provisioned_at !== null) {
            return array_merge($base, ['reason' => 'To zamówienie ma już oznaczone przyznanie dostępu PNEDU.']);
        }

        if (! $this->isPaymentAccepted($order)) {
            return array_merge($base, ['reason' => 'Przedłużenie wymaga opłaconego zamówienia online albo wystawionej faktury.']);
        }

        if ($participant->access_expires_at === null) {
            return array_merge($base, ['reason' => 'Uczestnik ma już dostęp bezterminowy.']);
        }

        if ($variant === null) {
            return array_merge($base, ['reason' => 'Brak wariantu cenowego przy zamówieniu.']);
        }

        if (! $this->variantAllowsExtension($variant, $course)) {
            return array_merge($base, ['reason' => 'Wariant nie jest przeznaczony do przedłużania dostępu po zakończeniu szkolenia.']);
        }

        $now = now();
        $newExpiresAt = app(ParticipantAccessExpiryService::class)
            ->resolveAccessExpiresAtForExtension(
                $participant->access_expires_at->copy(),
                $variant,
                $course,
                $now,
                $order->order_date,
                $order->submission_source === FormOrder::SUBMISSION_SOURCE_PNEDU_ORDER_FORM
            );

        if ($newExpiresAt !== null && $newExpiresAt->lte($participant->access_expires_at)) {
            return array_merge($base, [
                'new_expires_at' => $newExpiresAt,
                'reason' => 'Nowe zamówienie nie wydłuża obecnego terminu dostępu.',
            ]);
        }

        if ($newExpiresAt !== null && $newExpiresAt->lte($now)) {
            return array_merge($base, [
                'new_expires_at' => $newExpiresAt,
                'reason' => 'Wyliczona data dostępu jest już w przeszłości.',
            ]);
        }

        return array_merge($base, [
            'eligible' => true,
            'reason' => 'Możliwy świadomy ponowny zakup/przedłużenie dostępu.',
            'new_expires_at' => $newExpiresAt,
        ]);
    }

    private function resolveCourse(FormOrder $order): ?Course
    {
        $courseId = $order->resolveDuplicateGroupingCourseKey();

        return $courseId > 0 ? Course::query()->find($courseId) : null;
    }

    private function findParticipantForOrder(FormOrder $order, Course $course, bool $lock = false): ?Participant
    {
        $email = strtolower(trim((string) $order->display_participant_email));
        if ($email === '') {
            return null;
        }

        $query = Participant::query()
            ->where('course_id', $course->id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->orderByDesc('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function isPaymentAccepted(FormOrder $order): bool
    {
        if ($order->has_invoice) {
            return true;
        }

        return $order->payment_mode === FormOrder::PAYMENT_MODE_ONLINE_GATEWAY
            && $order->payment_status === FormOrder::PAYMENT_STATUS_PAID;
    }

    private function variantAllowsExtension(?CoursePriceVariant $variant, ?Course $course): bool
    {
        if ($variant === null || $course === null) {
            return false;
        }

        $availability = $variant->availability_after_course_end ?? CoursePriceVariant::AVAILABILITY_ALWAYS;
        if (! in_array($availability, [
            CoursePriceVariant::AVAILABILITY_ALWAYS,
            CoursePriceVariant::AVAILABILITY_SHOW_AFTER_END,
        ], true)) {
            return false;
        }

        $courseEnded = $course->end_date !== null && $course->end_date->isPast();

        return $variant->isAvailableForCourseEndState($courseEnded);
    }

    private function variantLabel(?CoursePriceVariant $variant): ?string
    {
        if ($variant === null) {
            return null;
        }

        return filled($variant->name) ? (string) $variant->name : 'Wariant #'.$variant->id;
    }

    private function appendExtensionNote(
        string $notes,
        Participant $participant,
        ?Carbon $previousExpiresAt,
        ?Carbon $newExpiresAt
    ): string
    {
        $line = sprintf(
            '[%s] PNEDU: przedłużono dostęp uczestnika #%d z %s do %s.',
            now()->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            $participant->id,
            $this->formatDate($previousExpiresAt) ?? 'bezterminowo',
            $this->formatDate($newExpiresAt) ?? 'bezterminowo'
        );

        return trim($notes) === '' ? $line : trim($notes)."\n".$line;
    }

    private function formatDate(?Carbon $date): ?string
    {
        return $date?->copy()->timezone(config('app.timezone'))->format('d.m.Y H:i');
    }
}
