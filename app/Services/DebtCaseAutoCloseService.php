<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\User;

/**
 * Auto-zamknięcie sprawy windykacyjnej po potwierdzeniu pełnej opłaty w iFirma
 * (ścieżka akceptacji przelewu z wyciągu).
 */
class DebtCaseAutoCloseService
{
    public const CLOSURE_REASON = 'Zamknięto automatycznie — FV opłacona w iFirma po akceptacji przelewu z wyciągu.';

    /**
     * Zamyka sprawę gdy status iFirma = oplacone i sprawa nie jest closed/disputed.
     *
     * @return bool true gdy sprawę zamknięto w tej operacji
     */
    public function closeIfFullyPaid(
        DebtCase $case,
        ?User $user = null,
        ?string $ifirmaStatus = null
    ): bool {
        $case->refresh();

        if (in_array($case->status, [DebtCase::STATUS_CLOSED, DebtCase::STATUS_DISPUTED], true)) {
            return false;
        }

        $status = $ifirmaStatus ?? $case->ifirma_payment_status;
        if ($status !== IfirmaInvoicePaymentStatusService::STATUS_PAID) {
            return false;
        }

        $happenedAt = now();

        $case->actions()->create([
            'user_id' => $user?->id,
            'action_type' => DebtCaseAction::TYPE_CLOSE,
            'channel' => 'system',
            'outcome' => IfirmaInvoicePaymentStatusService::STATUS_PAID,
            'happened_at' => $happenedAt,
            'note' => self::CLOSURE_REASON,
        ]);

        $case->forceFill([
            'status' => DebtCase::STATUS_CLOSED,
            'closed_at' => $happenedAt,
            'closure_reason' => self::CLOSURE_REASON,
            'last_action_at' => $happenedAt,
            'ifirma_payment_status' => IfirmaInvoicePaymentStatusService::STATUS_PAID,
            'assigned_to_id' => $user?->id ?: $case->assigned_to_id,
        ])->save();

        return true;
    }
}
