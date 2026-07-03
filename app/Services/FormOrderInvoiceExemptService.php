<?php

namespace App\Services;

use App\Models\FormOrder;
use Throwable;

class FormOrderInvoiceExemptService
{
    /**
     * Oznacza zamówienie jako zamknięte rozliczeniowo bez FV (bezpłatny dostęp).
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function markExempt(FormOrder $order, ?string $reason, ?int $userId): array
    {
        if ($order->cancelled_at !== null) {
            return ['success' => false, 'error' => 'Nie można oznaczyć anulowanego zamówienia.'];
        }

        if ($order->has_invoice) {
            return ['success' => false, 'error' => 'Zamówienie ma już wystawioną fakturę — zwolnienie z FV nie jest potrzebne.'];
        }

        if ($order->invoice_exempt_at !== null) {
            return ['success' => false, 'error' => 'Zamówienie jest już oznaczone jako bez faktury.'];
        }

        try {
            $order->invoice_exempt_at = now();
            $order->invoice_exempt_reason = $reason ?: 'Bezpłatny dostęp — bez faktury';
            $order->invoice_exempt_by = $userId;
            $order->save();
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Zamówienie oznaczone jako bez faktury (bezpłatny dostęp).',
        ];
    }

    /**
     * @return array{success: bool, message?: string, error?: string}
     */
    public function clearExempt(FormOrder $order): array
    {
        if ($order->invoice_exempt_at === null) {
            return ['success' => false, 'error' => 'Zamówienie nie ma oznaczenia „bez faktury”.'];
        }

        try {
            $order->invoice_exempt_at = null;
            $order->invoice_exempt_reason = null;
            $order->invoice_exempt_by = null;
            $order->save();
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Cofnięto oznaczenie „bez faktury”.',
        ];
    }
}
