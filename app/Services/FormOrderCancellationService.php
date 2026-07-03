<?php

namespace App\Services;

use App\Models\FormOrder;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;
use Throwable;

class FormOrderCancellationService
{
    public function __construct(
        private readonly FormOrderOperationalStatusService $operationalStatus
    ) {}

    /**
     * @return array{success: bool, message?: string, error?: string, warnings?: array<int, string>}
     */
    public function cancel(FormOrder $order, ?string $reason, ?int $cancelledByUserId): array
    {
        if ($order->cancelled_at !== null) {
            return ['success' => false, 'error' => 'Zamówienie jest już anulowane.'];
        }

        $warnings = [];

        try {
            DB::connection('mysql')->transaction(function () use ($order, $reason, $cancelledByUserId, &$warnings) {
                $locked = FormOrder::query()->lockForUpdate()->find($order->id);
                if (! $locked) {
                    throw new \RuntimeException('Zamówienie nie zostało znalezione.');
                }

                $courseId = $this->operationalStatus->resolveCourseId($locked);
                $participants = $this->operationalStatus->activeOrderParticipants($locked);

                foreach ($participants as $fop) {
                    if ($fop->participant_id !== null) {
                        $participant = Participant::query()->find($fop->participant_id);
                        if ($participant && ! $participant->trashed()) {
                            $participant->delete();
                        }

                        continue;
                    }

                    if ($courseId !== null) {
                        $email = strtolower(trim((string) ($fop->participant_email ?? '')));
                        if ($email !== '') {
                            $warnings[] = 'Uczestnik '.$fop->participant_email.' nie ma powiązania participant_id — nie wypisano automatycznie. Sprawdź ręcznie.';
                        }
                    }
                }

                $locked->cancelled_at = now();
                $locked->cancelled_reason = $reason ?: 'Anulowanie administracyjne';
                $locked->cancelled_by = $cancelledByUserId;
                // Legacy: zachowaj zgodność ze starym checkboxem „Zakończone” (void bez FV).
                if (! $locked->has_invoice) {
                    $locked->status_completed = 1;
                }
                $locked->save();
            });
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Zamówienie zostało anulowane.',
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{success: bool, message?: string, error?: string}
     */
    public function restore(FormOrder $order): array
    {
        if ($order->cancelled_at === null) {
            return ['success' => false, 'error' => 'Zamówienie nie jest anulowane.'];
        }

        $order->cancelled_at = null;
        $order->cancelled_reason = null;
        $order->cancelled_by = null;
        if (! $order->has_invoice) {
            $order->status_completed = 0;
        }
        $order->save();

        return [
            'success' => true,
            'message' => 'Anulowanie zostało cofnięte. Uczestnicy nie zostali automatycznie przywróceni — dodaj dostęp ręcznie, jeśli potrzeba.',
        ];
    }
}
