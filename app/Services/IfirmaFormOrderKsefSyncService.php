<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\FormOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Pobiera z iFirma aktualny NumerKSeF oraz daty FV dla zamówienia.
 * Preferuje ifirma_invoice_id; przy braku / preferencji numeru / starym ID —
 * wyszukuje dokument po invoice_number (lista iFirma).
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
     *     changed?: bool,
     *     ksef_cleared?: bool,
     *     email_sent?: bool,
     *     emails_sent?: list<string>,
     *     email_errors?: list<array{email: string, error: string}>,
     *     ksef_email_pending?: bool
     * }
     */
    public function syncFromIfirmaInvoiceId(
        FormOrder $order,
        ?string $invoiceNumberOverride = null,
        bool $preferNumberLookup = false
    ): array {
        if ($preferNumberLookup && ($invoiceNumberOverride === null || trim($invoiceNumberOverride) === '')) {
            return $this->clearInvoiceMetadataWhenNumberEmpty($order);
        }

        $numberAtEntry = trim((string) ($order->invoice_number ?? ''));
        $numberChangedFromOverride = false;
        if ($invoiceNumberOverride !== null && $invoiceNumberOverride !== '') {
            $trimmedOverride = trim($invoiceNumberOverride);
            if ($trimmedOverride !== '' && $trimmedOverride !== $numberAtEntry) {
                $order->invoice_number = $trimmedOverride;
                $numberChangedFromOverride = true;
            } elseif ($trimmedOverride !== '' && $numberAtEntry === '') {
                $order->invoice_number = $trimmedOverride;
                $numberChangedFromOverride = true;
            }
        }

        $idBeforeSync = trim((string) ($order->ifirma_invoice_id ?? ''));
        $expectedNumber = trim((string) ($order->invoice_number ?? ''));
        $invoiceId = $idBeforeSync;
        $lookupByNumber = $preferNumberLookup || $invoiceId === '';

        if ($lookupByNumber) {
            $resolved = $this->resolveInvoiceIdFromNumber($order, $expectedNumber !== '' ? $expectedNumber : null);
            if (! ($resolved['success'] ?? false)) {
                if ($invoiceId === '') {
                    return [
                        'success' => false,
                        'message' => $resolved['message'] ?? 'Nie udało się ustalić ID faktury w iFirma.',
                    ];
                }
                Log::info('iFirma KSeF sync: number lookup failed, falling back to stored ID', [
                    'form_order_id' => $order->id,
                    'ifirma_invoice_id' => $invoiceId,
                    'message' => $resolved['message'] ?? null,
                ]);
            } else {
                $invoiceId = (string) $resolved['invoice_id'];
                $order->ifirma_invoice_id = $invoiceId;
            }
        }

        $result = $this->api->getInvoice($invoiceId);
        if (($result['status'] ?? null) !== 'success') {
            if (! $lookupByNumber || $idBeforeSync === $invoiceId) {
                $fallback = $this->resolveInvoiceIdFromNumber($order, $expectedNumber !== '' ? $expectedNumber : null);
                if (($fallback['success'] ?? false)) {
                    $invoiceId = (string) $fallback['invoice_id'];
                    $order->ifirma_invoice_id = $invoiceId;
                    $result = $this->api->getInvoice($invoiceId);
                }
            }
        }

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

        $pelnyNumer = isset($payload['PelnyNumer']) ? trim((string) $payload['PelnyNumer']) : null;
        if ($pelnyNumer === '') {
            $pelnyNumer = null;
        }

        // Stare ID ≠ wpisany numer FV (format N/M/YYYY) — wyszukaj ponownie po numerze.
        if (
            ! $lookupByNumber
            && $expectedNumber !== ''
            && $pelnyNumer !== null
            && ! $this->invoiceNumbersMatch($expectedNumber, $pelnyNumer)
            && preg_match('#^\d+/\d+/\d+#', $expectedNumber) === 1
        ) {
            $resolved = $this->resolveInvoiceIdFromNumber($order, $expectedNumber);
            if (($resolved['success'] ?? false)) {
                $invoiceId = (string) $resolved['invoice_id'];
                $order->ifirma_invoice_id = $invoiceId;
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
                $pelnyNumer = isset($payload['PelnyNumer']) ? trim((string) $payload['PelnyNumer']) : null;
                if ($pelnyNumer === '') {
                    $pelnyNumer = null;
                }
            }
        }

        $resolvedId = $this->resolveIfirmaInvoiceId($payload, $invoiceId);
        $ksefNumber = $this->api->extractNumerKSeFFromInvoicePayload($payload);

        $issueDate = $this->parseDateString(
            $payload['DataWystawienia'] ?? $payload['DataWystawieniaFaktury'] ?? null
        );
        $dueDate = $this->parseDateString($payload['TerminPlatnosci'] ?? null);

        $previousKsef = trim((string) ($order->ksef_number ?? ''));
        $previousIssue = $order->invoice_issue_date?->toDateString();
        $previousDue = $order->invoice_due_date?->toDateString();
        $changed = $numberChangedFromOverride;

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

        if ($numberAtEntry === '' && trim((string) ($order->invoice_number ?? '')) === '' && $pelnyNumer !== null) {
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
            if ($order->ksef_error !== null) {
                $order->ksef_error = null;
                $changed = true;
            }
        } else {
            Log::info('iFirma KSeF sync: brak NumerKSeF w dokumencie — pozostawiono lokalny numer (bez czyszczenia)', [
                'form_order_id' => $order->id,
                'ifirma_invoice_id' => $invoiceId,
                'pelny_numer' => $pelnyNumer,
                'previous_ksef_number' => $previousKsef !== '' ? $previousKsef : null,
            ]);
        }

        if ($changed) {
            $order->save();
        }

        $this->syncDebtCaseInvoiceDates($order, $issueDate, $dueDate);

        $datesPayload = [
            'invoice_issue_date' => $order->invoice_issue_date?->toDateString(),
            'invoice_due_date' => $order->invoice_due_date?->toDateString(),
        ];

        $invoiceNumberForResponse = trim((string) ($order->invoice_number ?? '')) !== ''
            ? (string) $order->invoice_number
            : $pelnyNumer;

        if ($ksefNumber === null || $ksefNumber === '') {
            $message = $changed
                ? 'Zaktualizowano ID iFirma i daty FV. W iFirma brak jeszcze numeru KSeF — lokalny numer KSeF nie został usunięty.'
                : 'W iFirma brak numeru KSeF dla tego dokumentu. ID/daty bez zmian.';

            return array_merge([
                'success' => true,
                'message' => $message,
                'ksef_number' => $order->ksef_number,
                'ifirma_invoice_id' => $order->ifirma_invoice_id,
                'invoice_number' => $invoiceNumberForResponse,
                'ksef_status' => $order->ksef_status,
                'changed' => $changed,
                'ksef_cleared' => false,
                'ksef_email_pending' => (bool) $order->ksef_email_pending,
            ], $datesPayload);
        }

        $emailResult = $this->sendPendingInvoiceEmailsAfterKsef($order, (string) $order->ifirma_invoice_id, $invoiceNumberForResponse);
        if ($emailResult['changed']) {
            $changed = true;
        }

        $message = $changed
            ? 'Zaktualizowano dane KSeF, ID iFirma i daty FV z iFirma.'
            : 'Dane KSeF i daty FV w zamówieniu są już zgodne z iFirma.';
        if ($emailResult['emails_sent'] !== []) {
            $message .= ' E-mail FV wysłany na: '.implode(', ', $emailResult['emails_sent']).'.';
        }
        if ($emailResult['email_errors'] !== []) {
            $message .= ' Błędy wysyłki e-mail: '.count($emailResult['email_errors']).' (intencja pozostaje — można ponowić Odśwież KSeF).';
        }

        return array_merge([
            'success' => true,
            'message' => $message,
            'ksef_number' => $order->ksef_number,
            'ifirma_invoice_id' => $order->ifirma_invoice_id,
            'invoice_number' => $invoiceNumberForResponse,
            'ksef_status' => $order->ksef_status,
            'changed' => $changed,
            'ksef_cleared' => false,
            'email_sent' => $emailResult['emails_sent'] !== [],
            'emails_sent' => $emailResult['emails_sent'],
            'email_errors' => $emailResult['email_errors'],
            'ksef_email_pending' => (bool) $order->ksef_email_pending,
        ], $datesPayload);
    }

    /**
     * Odświeżenie przy pustym polu „Numer faktury” — usuwa lokalne powiązanie z dokumentem iFirma/KSeF
     * (np. po przeniesieniu FV do innego zamówienia).
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     ksef_number: null,
     *     ifirma_invoice_id: null,
     *     invoice_number: null,
     *     invoice_issue_date?: string|null,
     *     invoice_due_date?: string|null,
     *     changed: bool,
     *     ksef_cleared: bool,
     *     metadata_cleared: bool,
     *     ksef_email_pending?: bool
     * }
     */
    private function clearInvoiceMetadataWhenNumberEmpty(FormOrder $order): array
    {
        $hadInvoiceNumber = trim((string) ($order->invoice_number ?? '')) !== '';
        $hadIfirmaId = $order->hasIfirmaInvoiceId();
        $hadKsef = $order->hasConfirmedKsef() || trim((string) ($order->ksef_number ?? '')) !== '';
        $hadIssueDate = $order->invoice_issue_date !== null;
        $hadDueDate = $order->invoice_due_date !== null;
        $hadData = $hadInvoiceNumber || $hadIfirmaId || $hadKsef || $hadIssueDate || $hadDueDate;

        if ($hadData) {
            $order->invoice_number = null;
            $order->ifirma_invoice_id = null;
            $order->ksef_number = null;
            $order->ksef_status = null;
            $order->ksef_sent_at = null;
            $order->ksef_error = null;
            $order->invoice_issue_date = null;
            $order->invoice_due_date = null;
            $order->save();

            Log::info('iFirma KSeF sync: cleared local invoice metadata (empty invoice number refresh)', [
                'form_order_id' => $order->id,
                'had_invoice_number' => $hadInvoiceNumber,
                'had_ifirma_invoice_id' => $hadIfirmaId,
                'had_ksef' => $hadKsef,
                'had_invoice_issue_date' => $hadIssueDate,
                'had_invoice_due_date' => $hadDueDate,
            ]);
        }

        return [
            'success' => true,
            'message' => $hadData
                ? 'Wyczyszczono numer FV, daty FV, ID iFirma i numer KSeF w tym zamówieniu.'
                : 'Brak danych FV / ID iFirma / KSeF do wyczyszczenia.',
            'ksef_number' => null,
            'ifirma_invoice_id' => null,
            'invoice_number' => null,
            'invoice_issue_date' => null,
            'invoice_due_date' => null,
            'changed' => $hadData,
            'ksef_cleared' => $hadData,
            'metadata_cleared' => $hadData,
            'ksef_email_pending' => (bool) $order->ksef_email_pending,
        ];
    }

    /**
     * Po uzyskaniu NumerKSeF — wyślij FV mailem, jeśli czerwony przycisk zapisał intencję.
     *
     * @return array{
     *     changed: bool,
     *     emails_sent: list<string>,
     *     email_errors: list<array{email: string, error: string}>
     * }
     */
    private function sendPendingInvoiceEmailsAfterKsef(
        FormOrder $order,
        string $invoiceId,
        ?string $invoiceNumber
    ): array {
        $empty = ['changed' => false, 'emails_sent' => [], 'email_errors' => []];

        if (! $order->ksef_email_pending) {
            return $empty;
        }

        $invoiceId = trim($invoiceId);
        if ($invoiceId === '') {
            return $empty;
        }

        $emails = [];
        if (! empty($order->orderer_email)) {
            $emails[] = strtolower(trim($order->orderer_email));
        }
        if (! empty(trim($order->display_participant_email ?? ''))) {
            $participantEmail = strtolower(trim($order->display_participant_email));
            if (! in_array($participantEmail, $emails, true)) {
                $emails[] = $participantEmail;
            }
        }

        if ($emails === []) {
            $order->ksef_email_pending = false;
            $order->save();

            return ['changed' => true, 'emails_sent' => [], 'email_errors' => []];
        }

        $emailsSent = [];
        $emailErrors = [];
        $invoiceNumber = trim((string) ($invoiceNumber ?: $order->invoice_number ?: $invoiceId));

        foreach ($emails as $index => $email) {
            if ($index > 0) {
                usleep(400_000);
            }

            try {
                $sendResult = $this->api->sendInvoiceByEmail(
                    $invoiceId,
                    $email,
                    $invoiceNumber,
                    $order->id,
                    'invoice'
                );

                if (($sendResult['status'] ?? null) === 'success') {
                    $emailsSent[] = $email;
                } else {
                    $emailErrors[] = [
                        'email' => $email,
                        'error' => $sendResult['message'] ?? 'Nieznany błąd',
                    ];
                }
            } catch (\Throwable $e) {
                $emailErrors[] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ];
                Log::error('iFirma KSeF sync: exception podczas wysyłki FV po NumerKSeF', [
                    'form_order_id' => $order->id,
                    'invoice_id' => $invoiceId,
                    'email' => $email,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $changed = false;
        if ($emailErrors === []) {
            $order->ksef_email_pending = false;
            $order->save();
            $changed = true;
        }

        Log::info('iFirma KSeF sync: pending invoice email after NumerKSeF', [
            'form_order_id' => $order->id,
            'invoice_id' => $invoiceId,
            'emails_sent' => $emailsSent,
            'email_errors_count' => count($emailErrors),
            'ksef_email_pending' => (bool) $order->ksef_email_pending,
        ]);

        return [
            'changed' => $changed,
            'emails_sent' => $emailsSent,
            'email_errors' => $emailErrors,
        ];
    }

    /**
     * @return array{success: bool, message?: string, invoice_id?: string}
     */
    private function resolveInvoiceIdFromNumber(FormOrder $order, ?string $invoiceNumberOverride = null): array
    {
        $invoiceNumber = trim((string) ($invoiceNumberOverride ?: $order->invoice_number ?? ''));
        if ($invoiceNumber === '' || $invoiceNumber === '0') {
            return [
                'success' => false,
                'message' => 'Brak ID iFirma i numeru FV w zamówieniu. Wpisz numer faktury (np. 277/8/2026) i kliknij odświeżanie przy polu numeru FV.',
            ];
        }

        // Tymczasowo wyczyść ID, żeby fetchPaymentSnapshot szukał po numerze, nie po starym ID.
        $savedId = $order->ifirma_invoice_id;
        $order->ifirma_invoice_id = null;

        try {
            $snapshot = $this->paymentStatus->fetchPaymentSnapshotForOrder(
                $order,
                $invoiceNumber,
                $order->invoice_issue_date
            );
        } finally {
            $order->ifirma_invoice_id = $savedId;
        }

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

    private function invoiceNumbersMatch(string $a, string $b): bool
    {
        return $this->api->normalizeInvoiceNumber($a) === $this->api->normalizeInvoiceNumber($b);
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
