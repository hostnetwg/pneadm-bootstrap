<?php

namespace App\Observers;

use App\Models\FormOrder;
use Illuminate\Support\Facades\Log;

/**
 * Uczestnik zamówienia jest zapisywany wyłącznie w form_order_participants
 * (FormOrdersController, zewnętrzne formularze). Kolumn participant_* w form_orders nie ma.
 */
class FormOrderObserver
{
    public function created(FormOrder $formOrder): void
    {
        // intentionally empty
    }

    public function updated(FormOrder $formOrder): void
    {
        // intentionally empty
    }

    public function deleted(FormOrder $formOrder): void
    {
        Log::info("FormOrderObserver: Usunięto zamówienie #{$formOrder->id} - uczestnicy zostaną usunięci przez CASCADE");
    }
}
