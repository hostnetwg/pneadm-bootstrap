<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Odczyt statusu płatności faktury z iFirma (źródło prawdy) → cache na debt_cases.
 * Nie rejestruje wpłat i nie zamyka spraw automatycznie.
 */
class IfirmaInvoicePaymentStatusService
{
    public const STATUS_PAID = 'oplacone';

    public const STATUS_PARTIAL = 'oplaconeCzesciowo';

    public const STATUS_UNPAID = 'nieoplacone';

    public const STATUS_OVERDUE = 'przeterminowane';

    public const STATUS_UNKNOWN = 'unknown';

    private const AMOUNT_EPSILON = 0.009;

    public function __construct(
        private IfirmaApiService $api
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     status?: string,
     *     status_label?: string,
     *     paid_amount?: float|null,
     *     gross_amount?: float|null,
     *     invoice_id?: string|null,
     *     invoice_number?: string|null,
     *     due_date?: string|null,
     *     source?: string,
     *     changed?: bool
     * }
     */
    public function syncDebtCase(DebtCase $case, ?User $user = null): array
    {
        $case->loadMissing('formOrder');
        $order = $case->formOrder;
        if ($order === null) {
            return [
                'success' => false,
                'message' => 'Sprawa nie ma powiązanego zamówienia.',
            ];
        }

        $snapshot = $this->fetchPaymentSnapshotForOrder($order);
        if (! ($snapshot['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $snapshot['message'] ?? 'Nie udało się pobrać statusu z iFirma.',
            ];
        }

        $previousStatus = $case->ifirma_payment_status;
        $status = (string) $snapshot['status'];

        $case->ifirma_payment_status = $status;
        $case->ifirma_synced_at = now();

        if (! empty($snapshot['invoice_number']) && empty($case->invoice_number)) {
            $case->invoice_number = (string) $snapshot['invoice_number'];
        }
        if (! empty($snapshot['due_date']) && $case->due_date === null) {
            $case->due_date = $snapshot['due_date'];
        }
        if ($snapshot['gross_amount'] !== null && $case->amount_gross === null) {
            $case->amount_gross = $snapshot['gross_amount'];
        }

        $case->last_action_at = now();
        if ($user !== null) {
            $case->assigned_to_id = $user->id;
        }
        $case->save();

        if (! empty($snapshot['invoice_id'])) {
            $invoiceId = (string) $snapshot['invoice_id'];
            if (empty($order->ifirma_invoice_id) || (string) $order->ifirma_invoice_id !== $invoiceId) {
                $order->ifirma_invoice_id = $invoiceId;
                $order->save();
            }
        }

        $paid = $snapshot['paid_amount'];
        $gross = $snapshot['gross_amount'];
        $note = sprintf(
            'Synchronizacja iFirma: %s. Zapłacono %s / brutto %s. Źródło: %s.',
            $this->statusLabel($status),
            $this->formatAmount($paid),
            $this->formatAmount($gross),
            (string) ($snapshot['source'] ?? 'iFirma')
        );

        $case->actions()->create([
            'user_id' => $user?->id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_SYNC,
            'channel' => 'ifirma',
            'outcome' => $status,
            'happened_at' => now(),
            'note' => $note,
        ]);

        $changed = $previousStatus !== $status;

        return [
            'success' => true,
            'message' => $changed
                ? 'Zaktualizowano status płatności z iFirma: '.$this->statusLabel($status).'.'
                : 'Status w iFirma bez zmian: '.$this->statusLabel($status).'.',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'paid_amount' => $paid,
            'gross_amount' => $gross,
            'invoice_id' => $snapshot['invoice_id'] ?? null,
            'invoice_number' => $snapshot['invoice_number'] ?? null,
            'due_date' => $snapshot['due_date'] ?? null,
            'source' => $snapshot['source'] ?? null,
            'changed' => $changed,
        ];
    }

    /**
     * @return array{
     *     success: bool,
     *     message?: string,
     *     status?: string,
     *     paid_amount?: float|null,
     *     gross_amount?: float|null,
     *     invoice_id?: string|null,
     *     invoice_number?: string|null,
     *     due_date?: string|null,
     *     source?: string
     * }
     */
    public function fetchPaymentSnapshotForOrder(FormOrder $order): array
    {
        $invoiceId = trim((string) ($order->ifirma_invoice_id ?? ''));
        if ($invoiceId !== '') {
            $result = $this->api->getInvoice($invoiceId);
            if (($result['status'] ?? null) === 'success') {
                $row = $this->api->unwrapInvoicePayload($result['data'] ?? null);

                return $this->snapshotFromInvoiceRow($row, 'fakturakraj/'.$invoiceId, $invoiceId);
            }

            Log::warning('iFirma payment sync: getInvoice failed, falling back to list', [
                'form_order_id' => $order->id,
                'ifirma_invoice_id' => $invoiceId,
                'result_status' => $result['status'] ?? null,
                'message' => $result['message'] ?? null,
            ]);
        }

        $invoiceNumber = trim((string) ($order->invoice_number ?? ''));
        if ($invoiceNumber === '' || $invoiceNumber === '0') {
            return [
                'success' => false,
                'message' => 'Brak ifirma_invoice_id i numeru faktury — nie można odpytać iFirma.',
            ];
        }

        [$dataOd, $dataDo] = $this->resolveSearchDateRange($order);
        $found = $this->api->findSalesInvoiceByPelnyNumer($invoiceNumber, $dataOd, $dataDo);
        if (($found['status'] ?? null) !== 'success' || empty($found['invoice']) || ! is_array($found['invoice'])) {
            return [
                'success' => false,
                'message' => $found['message'] ?? 'Nie znaleziono faktury w iFirma po numerze.',
            ];
        }

        $row = $found['invoice'];
        $foundId = $row['FakturaId'] ?? $row['Identyfikator'] ?? null;

        return $this->snapshotFromInvoiceRow(
            $row,
            'faktury.json (PelnyNumer)',
            $foundId !== null ? (string) $foundId : null
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     success: bool,
     *     status: string,
     *     paid_amount: float|null,
     *     gross_amount: float|null,
     *     invoice_id: string|null,
     *     invoice_number: string|null,
     *     due_date: string|null,
     *     source: string
     * }
     */
    public function snapshotFromInvoiceRow(array $row, string $source, ?string $invoiceId = null): array
    {
        $paid = $this->toFloatOrNull($row['Zaplacono'] ?? null);
        $gross = $this->toFloatOrNull($row['Brutto'] ?? $row['Razem'] ?? null);
        $dueRaw = $row['TerminPlatnosci'] ?? null;
        $dueDate = null;
        if (is_string($dueRaw) && trim($dueRaw) !== '') {
            try {
                $dueDate = Carbon::parse($dueRaw)->toDateString();
            } catch (\Throwable) {
                $dueDate = null;
            }
        }

        $invoiceNumber = isset($row['PelnyNumer']) ? trim((string) $row['PelnyNumer']) : null;
        if ($invoiceNumber === '') {
            $invoiceNumber = null;
        }

        if ($invoiceId === null || $invoiceId === '') {
            $rawId = $row['FakturaId'] ?? $row['Identyfikator'] ?? null;
            $invoiceId = $rawId !== null && $rawId !== '' ? (string) $rawId : null;
        }

        $status = $this->deriveStatus($paid, $gross, $dueDate);

        return [
            'success' => true,
            'status' => $status,
            'paid_amount' => $paid,
            'gross_amount' => $gross,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'due_date' => $dueDate,
            'source' => $source,
        ];
    }

    public function deriveStatus(?float $paid, ?float $gross, ?string $dueDate): string
    {
        $paid = $paid ?? 0.0;

        if ($gross !== null && $gross > self::AMOUNT_EPSILON && $paid + self::AMOUNT_EPSILON >= $gross) {
            return self::STATUS_PAID;
        }

        if ($paid > self::AMOUNT_EPSILON) {
            return self::STATUS_PARTIAL;
        }

        if ($gross === null && $paid <= self::AMOUNT_EPSILON) {
            // Lista iFirma czasem bez Brutto — bez kwoty nie da się odróżnić pełnej płatności
            return self::STATUS_UNKNOWN;
        }

        if ($dueDate !== null) {
            try {
                if (Carbon::parse($dueDate)->startOfDay()->lt(now()->startOfDay())) {
                    return self::STATUS_OVERDUE;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return self::STATUS_UNPAID;
    }

    public function statusLabel(?string $status): string
    {
        return self::statusLabels()[$status] ?? (string) ($status ?: '—');
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PAID => 'Opłacona',
            self::STATUS_PARTIAL => 'Częściowo opłacona',
            self::STATUS_UNPAID => 'Nieopłacona',
            self::STATUS_OVERDUE => 'Przeterminowana',
            self::STATUS_UNKNOWN => 'Nieznany',
        ];
    }

    public static function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => 'text-bg-success',
            self::STATUS_PARTIAL => 'text-bg-info',
            self::STATUS_OVERDUE => 'text-bg-danger',
            self::STATUS_UNPAID => 'text-bg-warning',
            default => 'text-bg-secondary',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSearchDateRange(FormOrder $order): array
    {
        $anchor = $order->order_date?->copy()
            ?? $order->created_at?->copy()
            ?? now();

        $dataOd = $anchor->copy()->subDays(7)->toDateString();
        $dataDo = $anchor->copy()->addDays(90)->toDateString();

        return [$dataOd, $dataDo];
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function formatAmount(?float $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        return number_format($amount, 2, ',', ' ').' zł';
    }
}
