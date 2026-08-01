<?php

namespace App\Services;

use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
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
        $case->loadMissing('formOrder');
        $order = $case->formOrder;
        if ($order === null) {
            return [
                'success' => false,
                'message' => 'Sprawa nie ma powiązanego zamówienia.',
            ];
        }

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
        $apiResult = $this->api->registerInvoicePayment($invoiceRef, $amount, $paymentDate, $invoiceType);

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
