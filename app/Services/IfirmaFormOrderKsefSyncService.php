<?php

namespace App\Services;

use App\Models\FormOrder;
use Illuminate\Support\Facades\Log;

/**
 * Pobiera z iFirma aktualny NumerKSeF (i potwierdza ifirma_invoice_id) dla zamówienia
 * po wewnętrznym Identyfikatorze dokumentu — np. po ręcznej wysyłce do KSeF z panelu iFirma.
 */
class IfirmaFormOrderKsefSyncService
{
    public function __construct(
        private IfirmaApiService $api
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     ksef_number?: string|null,
     *     ifirma_invoice_id?: string|null,
     *     invoice_number?: string|null,
     *     ksef_status?: string|null,
     *     changed?: bool
     * }
     */
    public function syncFromIfirmaInvoiceId(FormOrder $order): array
    {
        $invoiceId = trim((string) ($order->ifirma_invoice_id ?? ''));
        if ($invoiceId === '') {
            return [
                'success' => false,
                'message' => 'Brak ID iFirma w zamówieniu. Wystaw fakturę przyciskiem iFirma (fioletowy lub czerwony), '
                    .'aby zapisać identyfikator dokumentu — synchronizacja KSeF działa po tym ID, nie po numerze FV.',
            ];
        }

        $result = $this->api->getInvoice($invoiceId);
        if (($result['status'] ?? null) !== 'success') {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Nie udało się pobrać faktury z iFirma po ID '.$invoiceId.'.',
            ];
        }

        $payload = $this->api->unwrapInvoicePayload($result['data'] ?? null);
        if ($payload === []) {
            return [
                'success' => false,
                'message' => 'iFirma zwróciło pustą odpowiedź dla dokumentu ID '.$invoiceId.'.',
            ];
        }

        $resolvedId = $this->resolveIfirmaInvoiceId($payload, $invoiceId);
        $ksefNumber = $this->api->extractNumerKSeFFromInvoicePayload($payload);
        $pelnyNumer = isset($payload['PelnyNumer']) ? trim((string) $payload['PelnyNumer']) : null;
        if ($pelnyNumer === '') {
            $pelnyNumer = null;
        }

        $previousKsef = trim((string) ($order->ksef_number ?? ''));
        $previousId = trim((string) ($order->ifirma_invoice_id ?? ''));
        $changed = false;

        if ($resolvedId !== '' && $previousId !== $resolvedId) {
            $order->ifirma_invoice_id = $resolvedId;
            $changed = true;
        }

        if ($ksefNumber !== null && $ksefNumber !== '') {
            if ($previousKsef !== $ksefNumber) {
                $order->ksef_number = $ksefNumber;
                $changed = true;
            }
            if ($order->ksef_status !== 'sent') {
                $order->ksef_status = 'sent';
                $changed = true;
            }
            if ($order->ksef_sent_at === null) {
                $order->ksef_sent_at = now();
                $changed = true;
            }
            $order->ksef_error = null;
        } else {
            Log::info('iFirma KSeF sync: brak NumerKSeF w dokumencie', [
                'form_order_id' => $order->id,
                'ifirma_invoice_id' => $invoiceId,
                'pelny_numer' => $pelnyNumer,
            ]);

            return [
                'success' => false,
                'message' => 'Dokument iFirma (ID '.$invoiceId.') nie ma jeszcze nadanego numeru KSeF. '
                    .'Wyślij fakturę do KSeF w panelu iFirma i spróbuj ponownie za chwilę.',
                'ifirma_invoice_id' => $resolvedId !== '' ? $resolvedId : $invoiceId,
                'invoice_number' => $pelnyNumer,
                'ksef_number' => $previousKsef !== '' ? $previousKsef : null,
            ];
        }

        if ($changed) {
            $order->save();
        }

        $message = $changed
            ? 'Zaktualizowano dane KSeF z iFirma.'
            : 'Dane KSeF w zamówieniu są już zgodne z iFirma.';

        return [
            'success' => true,
            'message' => $message,
            'ksef_number' => $order->ksef_number,
            'ifirma_invoice_id' => $order->ifirma_invoice_id,
            'invoice_number' => $pelnyNumer,
            'ksef_status' => $order->ksef_status,
            'changed' => $changed,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveIfirmaInvoiceId(array $payload, string $requestedId): string
    {
        $fromPayload = $payload['Identyfikator'] ?? $payload['FakturaId'] ?? null;
        if ($fromPayload !== null && trim((string) $fromPayload) !== '') {
            return trim((string) $fromPayload);
        }

        return $requestedId;
    }
}
