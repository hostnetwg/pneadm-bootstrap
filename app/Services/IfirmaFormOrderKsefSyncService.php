<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\FormOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Pobiera z iFirma aktualny NumerKSeF oraz daty FV dla zamówienia.
 * Preferuje ifirma_invoice_id; gdy brak — wyszukuje dokument po invoice_number (lista iFirma).
 */
class IfirmaFormOrderKsefSyncService
{
    public function __construct(
        private IfirmaApiService $api,
        private IfirmaInvoicePaymentStatusService $paymentStatus,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     ksef_number?: string|null,
     *     ifirma_invoice_id?: string|null,
     *     invoice_number?: string|null,
     *     invoice_issue_date?: string|null,
     *     invoice_due_date?: string|null,
     *     ksef_status?: string|null,
     *     changed?: bool
     * }
     */
    public function syncFromIfirmaInvoiceId(FormOrder $order): array
    {
        $idBeforeSync = trim((string) ($order->ifirma_invoice_id ?? ''));
        $invoiceId = $idBeforeSync;

        if ($invoiceId === '') {
            $resolved = $this->resolveInvoiceIdFromNumber($order);
            if (! ($resolved['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $resolved['message'] ?? 'Nie udało się ustalić ID faktury w iFirma.',
                ];
            }
            $invoiceId = (string) $resolved['invoice_id'];
            $order->ifirma_invoice_id = $invoiceId;
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

        $issueDate = $this->parseDateString(
            $payload['DataWystawienia'] ?? $payload['DataWystawieniaFaktury'] ?? null
        );
        $dueDate = $this->parseDateString($payload['TerminPlatnosci'] ?? null);

        $previousKsef = trim((string) ($order->ksef_number ?? ''));
        $previousIssue = $order->invoice_issue_date?->toDateString();
        $previousDue = $order->invoice_due_date?->toDateString();
        $previousInvoiceNumber = trim((string) ($order->invoice_number ?? ''));
        $changed = false;

        if ($resolvedId !== '' && $idBeforeSync !== $resolvedId) {
            $order->ifirma_invoice_id = $resolvedId;
            $changed = true;
        }

        if ($issueDate !== null && $previousIssue !== $issueDate) {
            $order->invoice_issue_date = $issueDate;
            $changed = true;
        }
        if ($dueDate !== null && $previousDue !== $dueDate) {
            $order->invoice_due_date = $dueDate;
            $changed = true;
        }

        if ($previousInvoiceNumber === '' && $pelnyNumer !== null) {
            $order->invoice_number = $pelnyNumer;
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
            Log::info('iFirma KSeF sync: brak NumerKSeF w dokumencie — czyszczenie pola w zamówieniu', [
                'form_order_id' => $order->id,
                'ifirma_invoice_id' => $invoiceId,
                'pelny_numer' => $pelnyNumer,
                'previous_ksef_number' => $previousKsef !== '' ? $previousKsef : null,
            ]);

            if ($previousKsef !== '') {
                $order->ksef_number = null;
                $changed = true;
            }
            if ($order->ksef_status !== null) {
                $order->ksef_status = null;
                $changed = true;
            }
            if ($order->ksef_sent_at !== null) {
                $order->ksef_sent_at = null;
                $changed = true;
            }
            if ($order->ksef_error !== null) {
                $order->ksef_error = null;
                $changed = true;
            }
        }

        if ($changed) {
            $order->save();
        }

        $this->syncDebtCaseInvoiceDates($order, $issueDate, $dueDate);

        $datesPayload = [
            'invoice_issue_date' => $order->invoice_issue_date?->toDateString(),
            'invoice_due_date' => $order->invoice_due_date?->toDateString(),
        ];

        $invoiceNumberForResponse = $previousInvoiceNumber !== ''
            ? $previousInvoiceNumber
            : ($order->invoice_number ?: $pelnyNumer);

        if ($ksefNumber === null || $ksefNumber === '') {
            $message = $changed
                ? 'W iFirma brak numeru KSeF dla tego dokumentu — zaktualizowano ID/daty FV; wyczyszczono zapisany numer KSeF w zamówieniu (jeśli był).'
                : 'W iFirma brak numeru KSeF dla tego dokumentu. Zamówienie nie miało zapisanego numeru KSeF.';

            return array_merge([
                'success' => true,
                'message' => $message,
                'ksef_number' => null,
                'ifirma_invoice_id' => $order->ifirma_invoice_id,
                'invoice_number' => $invoiceNumberForResponse,
                'ksef_status' => null,
                'changed' => $changed,
                'ksef_cleared' => $changed && $previousKsef !== '',
            ], $datesPayload);
        }

        $message = $changed
            ? 'Zaktualizowano dane KSeF, ID iFirma i daty FV z iFirma.'
            : 'Dane KSeF i daty FV w zamówieniu są już zgodne z iFirma.';

        return array_merge([
            'success' => true,
            'message' => $message,
            'ksef_number' => $order->ksef_number,
            'ifirma_invoice_id' => $order->ifirma_invoice_id,
            'invoice_number' => $invoiceNumberForResponse,
            'ksef_status' => $order->ksef_status,
            'changed' => $changed,
        ], $datesPayload);
    }

    /**
     * @return array{success: bool, message?: string, invoice_id?: string}
     */
    private function resolveInvoiceIdFromNumber(FormOrder $order): array
    {
        $invoiceNumber = trim((string) ($order->invoice_number ?? ''));
        if ($invoiceNumber === '' || $invoiceNumber === '0') {
            return [
                'success' => false,
                'message' => 'Brak ID iFirma i numeru FV w zamówieniu. Wpisz numer faktury albo wystaw FV przyciskiem iFirma, potem odśwież KSeF.',
            ];
        }

        $snapshot = $this->paymentStatus->fetchPaymentSnapshotForOrder(
            $order,
            $invoiceNumber,
            $order->invoice_issue_date
        );

        if (! ($snapshot['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $snapshot['message'] ?? 'Nie znaleziono faktury w iFirma po numerze FV.',
            ];
        }

        $id = trim((string) ($snapshot['invoice_id'] ?? ''));
        if ($id === '') {
            return [
                'success' => false,
                'message' => 'Znaleziono fakturę po numerze, ale iFirma nie zwróciło ID dokumentu. Uzupełnij „ID iFirma” ręcznie.',
            ];
        }

        Log::info('iFirma KSeF sync: resolved ifirma_invoice_id from invoice_number', [
            'form_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'ifirma_invoice_id' => $id,
            'source' => $snapshot['source'] ?? null,
        ]);

        return [
            'success' => true,
            'invoice_id' => $id,
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

    private function parseDateString(mixed $raw): ?string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function syncDebtCaseInvoiceDates(FormOrder $order, ?string $issueDate, ?string $dueDate): void
    {
        if ($issueDate === null && $dueDate === null) {
            return;
        }

        if (! $order->exists || $order->id === null) {
            return;
        }

        $cases = DebtCase::query()
            ->where('form_order_id', $order->id)
            ->get();

        foreach ($cases as $case) {
            $caseDirty = false;
            if ($issueDate !== null && $case->invoice_date?->toDateString() !== $issueDate) {
                $case->invoice_date = $issueDate;
                $caseDirty = true;
            }
            if ($dueDate !== null && $case->due_date?->toDateString() !== $dueDate) {
                $case->due_date = $dueDate;
                $caseDirty = true;
            }
            if ($caseDirty) {
                $case->save();
            }
        }
    }
}
