<?php

namespace App\Observers;

use App\Models\FormOrder;
use App\Services\Analytics\InvoiceAnalyticsTracker;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Uczestnik zamówienia jest zapisywany wyłącznie w form_order_participants
 * (FormOrdersController, zewnętrzne formularze). Kolumn participant_* w form_orders nie ma.
 *
 * Analityka (Etap 2C-1): emituje event invoice_created przy PIERWSZYM ustawieniu
 * poprawnego invoice_number (przejście empty -> present). Patrz ADR-005.
 */
class FormOrderObserver
{
    public function created(FormOrder $formOrder): void
    {
        $this->trackInvoiceCreatedOnFirstInvoiceNumber($formOrder, null, $formOrder->invoice_number);
    }

    public function updated(FormOrder $formOrder): void
    {
        if (! $formOrder->wasChanged('invoice_number')) {
            return;
        }

        $this->trackInvoiceCreatedOnFirstInvoiceNumber(
            $formOrder,
            $formOrder->getOriginal('invoice_number'),
            $formOrder->invoice_number,
        );
    }

    public function deleted(FormOrder $formOrder): void
    {
        Log::info("FormOrderObserver: Usunięto zamówienie #{$formOrder->id} - uczestnicy zostaną usunięci przez CASCADE");
    }

    /**
     * Emituje invoice_created tylko przy przejściu invoice_number: empty -> present.
     * Nie emituje dla: empty->empty, present->present, present->changed, present->empty.
     */
    private function trackInvoiceCreatedOnFirstInvoiceNumber(FormOrder $formOrder, mixed $old, mixed $new): void
    {
        try {
            if ($this->isInvoiceNumberPresent($old) || ! $this->isInvoiceNumberPresent($new)) {
                return;
            }

            $pathType = InvoiceAnalyticsTracker::consumeSourceHint();

            app(InvoiceAnalyticsTracker::class)->trackInvoiceCreated($formOrder, $pathType);
        } catch (Throwable) {
            // Fail-silent: analytics must never break saving invoice_number.
        }
    }

    /**
     * present = wartość po trim nie jest pusta i nie jest '0'.
     * empty = null | '' | '0'.
     */
    private function isInvoiceNumberPresent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' && $trimmed !== '0';
    }
}
