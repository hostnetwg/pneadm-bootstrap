<?php

namespace App\Services;

use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Rejestracja wpłaty w iFirma (zapis) + odświeżenie cache statusu na sprawie.
 */
class IfirmaInvoicePaymentRegistrationService
{
    private const AMOUNT_EPSILON = 0.01;

    public function __construct(
        private IfirmaApiService $api,
        private IfirmaInvoicePaymentStatusService $statusService,
    ) {}

    /**
     * Po akceptacji dopasowania z wyciągu: wpłata w iFirma + sync statusu.
     *
     * @return array{success: bool, message: string, synced?: bool, status?: string}
     */
    public function registerFromAcceptedBankMatch(BankTransactionMatch $match, ?User $user = null): array
    {
        $match = $match->fresh(['transaction', 'formOrder', 'debtCase.formOrder']) ?? $match;
        $match->loadMissing(['transaction', 'formOrder', 'debtCase.formOrder']);

        if ($match->status !== BankTransactionMatch::STATUS_ACCEPTED) {
            return [
                'success' => false,
                'message' => 'Dopasowanie nie jest zaakceptowane — najpierw akceptacja lokalna.',
            ];
        }

        $transaction = $match->transaction;
        $debtCase = $match->debtCase;
        $order = $match->formOrder ?: $debtCase?->formOrder;

        if (! $transaction || ! $debtCase || ! $order) {
            return [
                'success' => false,
                'message' => 'Brak transakcji, sprawy lub zamówienia do rejestracji wpłaty.',
            ];
        }

        $amount = round((float) $transaction->amount, 2);
        $expected = round((float) ($order->product_price ?? $debtCase->amount_gross ?? 0), 2);

        if ($expected <= 0 || abs($amount - $expected) > self::AMOUNT_EPSILON) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Rejestracja w iFirma tylko przy zgodnej kwocie (przelew %s ≠ FV/zamówienie %s).',
                    number_format($amount, 2, ',', ' '),
                    number_format($expected, 2, ',', ' ')
                ),
            ];
        }

        $paymentDate = $transaction->operation_date?->format('Y-m-d');

        return $this->registerPaymentForDebtCase($debtCase, $amount, $paymentDate, $user);
    }

    /**
     * @return array{success: bool, message: string, synced?: bool, status?: string}
     */
    public function registerPaymentForDebtCase(
        DebtCase $case,
        float $amount,
        ?string $paymentDate = null,
        ?User $user = null
    ): array {
        $case = $case->fresh(['formOrder']) ?? $case;
        $case->loadMissing('formOrder');
        $order = $case->formOrder;
        if ($order === null) {
            return [
                'success' => false,
                'message' => 'Sprawa nie ma powiązanego zamówienia.',
            ];
        }

        $this->refreshCaseInvoiceDataFromOrder($case, $order);

        $snapshot = $this->statusService->fetchPaymentSnapshotForOrder(
            $order,
            $case->invoice_number ?: null,
            $case->invoice_date
        );

        if (! ($snapshot['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $snapshot['message'] ?? 'Nie udało się odnaleźć faktury w iFirma przed rejestracją wpłaty.',
            ];
        }

        $invoiceId = trim((string) ($snapshot['invoice_id'] ?? ''));
        $invoiceNumber = trim((string) ($snapshot['invoice_number'] ?? $order->invoice_number ?? ''));
        $invoiceRef = $invoiceId !== '' ? $invoiceId : $this->invoiceNumberToApiPath($invoiceNumber);

        if ($invoiceRef === '') {
            return [
                'success' => false,
                'message' => 'Brak identyfikatora i numeru faktury w iFirma.',
            ];
        }

        if ($invoiceId !== '' && (string) ($order->ifirma_invoice_id ?? '') !== $invoiceId) {
            $order->ifirma_invoice_id = $invoiceId;
            $order->save();
        }

        $paid = (float) ($snapshot['paid_amount'] ?? 0);
        $gross = $snapshot['gross_amount'] !== null ? (float) $snapshot['gross_amount'] : null;
        if ($gross !== null && $paid + self::AMOUNT_EPSILON >= $gross) {
            $sync = $this->statusService->syncDebtCase($case->fresh(['formOrder']), $user);

            return [
                'success' => true,
                'message' => 'Faktura w iFirma jest już opłacona — wpłaty nie dodano. Odświeżono status lokalnie.',
                'synced' => (bool) ($sync['success'] ?? false),
                'status' => $sync['status'] ?? $snapshot['status'] ?? null,
            ];
        }

        if ($gross !== null) {
            $remaining = round($gross - $paid, 2);
            if ($amount - self::AMOUNT_EPSILON > $remaining) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Kwota wpłaty (%s) przekracza pozostałą do zapłaty w iFirma (%s).',
                        number_format($amount, 2, ',', ' '),
                        number_format($remaining, 2, ',', ' ')
                    ),
                ];
            }
        }

        $invoiceType = 'prz_faktura_kraj';
        $apiPaymentDate = $this->paymentDateAllowedForInvoice($paymentDate, $snapshot, $invoiceType);
        $apiResult = $this->api->registerInvoicePayment($invoiceRef, $amount, $apiPaymentDate, $invoiceType);

        if (($apiResult['status'] ?? null) !== 'success') {
            $message = $apiResult['message'] ?? 'Nie udało się zarejestrować wpłaty w iFirma.';
            Log::warning('iFirma register payment failed', [
                'debt_case_id' => $case->id,
                'form_order_id' => $order->id,
                'invoice_ref' => $invoiceRef,
                'amount' => $amount,
                'api_result' => $apiResult,
            ]);

            return [
                'success' => false,
                'message' => $message,
            ];
        }

        $case->actions()->create([
            'user_id' => $user?->id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_PAYMENT,
            'channel' => 'ifirma',
            'happened_at' => now(),
            'note' => sprintf(
                'Zarejestrowano wpłatę w iFirma: %s PLN%s (ref. %s).',
                number_format($amount, 2, ',', ' '),
                $paymentDate ? ' z dnia '.$paymentDate : '',
                $invoiceRef
            ),
        ]);

        $sync = $this->statusService->syncDebtCase($case->fresh(['formOrder']), $user);

        $statusLabel = isset($sync['status_label'])
            ? (string) $sync['status_label']
            : (string) ($sync['status'] ?? '—');

        if (! ($sync['success'] ?? false)) {
            return [
                'success' => true,
                'message' => 'Wpłata zarejestrowana w iFirma, ale nie udało się odświeżyć statusu lokalnie: '
                    .($sync['message'] ?? 'nieznany błąd'),
                'synced' => false,
            ];
        }

        return [
            'success' => true,
            'message' => 'Zarejestrowano wpłatę w iFirma. Status: '.$statusLabel.'.',
            'synced' => true,
            'status' => $sync['status'] ?? null,
        ];
    }

    /**
     * Best-effort usunięcie wpłaty w iFirma (API oficjalnie dokumentuje tylko rejestrację).
     *
     * @return array{success: bool, message: string, attempted: bool, synced?: bool, status?: string|null}
     */
    public function attemptRemovePaymentForDebtCase(
        DebtCase $case,
        float $amount,
        ?string $paymentDate = null,
        ?User $user = null
    ): array {
        $case = $case->fresh(['formOrder']) ?? $case;
        $case->loadMissing('formOrder');
        $order = $case->formOrder;
        if ($order === null) {
            return [
                'success' => false,
                'attempted' => false,
                'message' => 'Brak zamówienia — pominięto próbę usunięcia wpłaty w iFirma.',
            ];
        }

        $this->refreshCaseInvoiceDataFromOrder($case, $order);

        $snapshot = $this->statusService->fetchPaymentSnapshotForOrder(
            $order,
            $case->invoice_number ?: null,
            $case->invoice_date
        );

        if (! ($snapshot['success'] ?? false)) {
            return [
                'success' => false,
                'attempted' => true,
                'message' => $snapshot['message'] ?? 'Nie udało się odnaleźć faktury w iFirma przed usunięciem wpłaty.',
            ];
        }

        $invoiceId = trim((string) ($snapshot['invoice_id'] ?? ''));
        $invoiceNumber = trim((string) ($snapshot['invoice_number'] ?? $order->invoice_number ?? ''));
        $invoiceRef = $invoiceId !== '' ? $invoiceId : $this->invoiceNumberToApiPath($invoiceNumber);

        if ($invoiceRef === '') {
            return [
                'success' => false,
                'attempted' => false,
                'message' => 'Brak identyfikatora faktury w iFirma — nie wykonano usunięcia wpłaty.',
            ];
        }

        $paid = (float) ($snapshot['paid_amount'] ?? 0);
        if ($paid <= self::AMOUNT_EPSILON) {
            $sync = $this->statusService->syncDebtCase($case->fresh(['formOrder']), $user);

            return [
                'success' => true,
                'attempted' => false,
                'message' => 'W iFirma nie widać wpłaty do usunięcia (Zaplacono ≈ 0). Odświeżono status lokalnie.',
                'synced' => (bool) ($sync['success'] ?? false),
                'status' => $sync['status'] ?? $snapshot['status'] ?? null,
            ];
        }

        $apiPaymentDate = $this->paymentDateAllowedForInvoice($paymentDate, $snapshot, 'prz_faktura_kraj');
        $apiResult = $this->api->deleteInvoicePayment($invoiceRef, $amount, $apiPaymentDate, 'prz_faktura_kraj');

        if (($apiResult['status'] ?? null) !== 'success') {
            Log::warning('iFirma delete payment failed or unsupported', [
                'debt_case_id' => $case->id,
                'form_order_id' => $order->id,
                'invoice_ref' => $invoiceRef,
                'amount' => $amount,
                'api_result' => $apiResult,
            ]);

            $sync = $this->statusService->syncDebtCase($case->fresh(['formOrder']), $user);

            return [
                'success' => false,
                'attempted' => true,
                'message' => ($apiResult['message'] ?? 'iFirma nie przyjęła usunięcia wpłaty.')
                    .' Oficjalne API dokumentuje tylko dodawanie wpłat — usuń wpłatę ręcznie w panelu iFirma (FV '
                    .($invoiceNumber !== '' ? $invoiceNumber : $invoiceRef).').',
                'synced' => (bool) ($sync['success'] ?? false),
                'status' => $sync['status'] ?? null,
            ];
        }

        $case->actions()->create([
            'user_id' => $user?->id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_PAYMENT,
            'channel' => 'ifirma',
            'happened_at' => now(),
            'note' => sprintf(
                'Usunięto wpłatę w iFirma: %s PLN%s (ref. %s).',
                number_format($amount, 2, ',', ' '),
                $paymentDate ? ' z dnia '.$paymentDate : '',
                $invoiceRef
            ),
        ]);

        $sync = $this->statusService->syncDebtCase($case->fresh(['formOrder']), $user);

        return [
            'success' => true,
            'attempted' => true,
            'message' => 'Usunięto wpłatę w iFirma. Status lokalny: '
                .(isset($sync['status_label']) ? (string) $sync['status_label'] : (string) ($sync['status'] ?? '—')).'.',
            'synced' => (bool) ($sync['success'] ?? false),
            'status' => $sync['status'] ?? null,
        ];
    }

    public function invoiceNumberToApiPath(string $invoiceNumber): string
    {
        $number = trim($invoiceNumber);
        if ($number === '' || $number === '0') {
            return '';
        }

        return str_replace('/', '_', $number);
    }

    public function amountsMatch(float $a, float $b): bool
    {
        return abs($a - $b) <= self::AMOUNT_EPSILON;
    }

    private function refreshCaseInvoiceDataFromOrder(DebtCase $case, FormOrder $order): void
    {
        $dirty = false;

        foreach (['invoice_number', 'ksef_number'] as $field) {
            $value = trim((string) ($order->{$field} ?? ''));
            if ($value !== '' && (string) ($case->{$field} ?? '') !== $value) {
                $case->{$field} = $value;
                $dirty = true;
            }
        }

        if ($order->product_price !== null) {
            $amount = round((float) $order->product_price, 2);
            $caseAmount = $case->amount_gross !== null ? round((float) $case->amount_gross, 2) : null;
            if ($caseAmount === null || abs($caseAmount - $amount) > self::AMOUNT_EPSILON) {
                $case->amount_gross = $amount;
                $dirty = true;
            }
        }

        if ($dirty) {
            $case->save();
        }
    }

    /**
     * iFirma dla zwykłej faktury krajowej przyjmuje wpłatę z samą kwotą.
     * Gdy przelew był przed wystawieniem FV, wysłanie tej daty może zostać odrzucone.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function paymentDateAllowedForInvoice(?string $paymentDate, array $snapshot, string $invoiceType): ?string
    {
        if ($paymentDate === null || trim($paymentDate) === '') {
            return null;
        }

        $issueDate = trim((string) ($snapshot['issue_date'] ?? ''));
        if ($invoiceType === 'prz_faktura_kraj' && $issueDate !== '' && $paymentDate < $issueDate) {
            return null;
        }

        return $paymentDate;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertCanRegisterAmount(float $transferAmount, float $invoiceAmount): void
    {
        if (! $this->amountsMatch($transferAmount, $invoiceAmount)) {
            throw new InvalidArgumentException(
                'Rejestracja wpłaty w iFirma wymaga zgodności kwoty przelewu z kwotą faktury/zamówienia.'
            );
        }
    }
}
