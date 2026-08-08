<?php

namespace App\Observers;

use App\Models\DebtCase;
use App\Services\DebtCaseInvoicePdfService;

/**
 * Po zamknięciu sprawy usuwa lokalny PDF faktury (oszczędność dysku).
 */
class DebtCaseObserver
{
    public function __construct(
        private readonly DebtCaseInvoicePdfService $invoicePdfService,
    ) {}

    public function updated(DebtCase $debtCase): void
    {
        if (! $debtCase->wasChanged('status')) {
            return;
        }

        if ($debtCase->status !== DebtCase::STATUS_CLOSED) {
            return;
        }

        $this->invoicePdfService->delete($debtCase);
    }
}
