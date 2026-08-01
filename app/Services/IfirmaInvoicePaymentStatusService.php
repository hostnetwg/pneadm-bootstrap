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
     *     issue_date?: string|null,
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

        $snapshot = $this->fetchPaymentSnapshotForOrder(
            $order,
            $case->invoice_number ?: null,
            $case->invoice_date
        );
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
            'issue_date' => $snapshot['issue_date'] ?? null,
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
     *     issue_date?: string|null,
     *     due_date?: string|null,
     *     source?: string
     * }
     */
    public function fetchPaymentSnapshotForOrder(
        FormOrder $order,
        ?string $invoiceNumberOverride = null,
        mixed $invoiceDateHint = null
    ): array {
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

        $invoiceNumber = trim((string) ($invoiceNumberOverride ?: $order->invoice_number ?? ''));
        if ($invoiceNumber === '' || $invoiceNumber === '0') {
            return [
                'success' => false,
                'message' => 'Brak ifirma_invoice_id i numeru faktury — nie można odpytać iFirma.',
            ];
        }

        [$dataOd, $dataDo] = $this->resolveSearchDateRange($order, $invoiceNumber, $invoiceDateHint);

        $found = $this->api->findSalesInvoiceByPelnyNumer($invoiceNumber, $dataOd, $dataDo);
        if (($found['status'] ?? null) === 'not_found') {
            // Ponów bez filtra typu — na liście bywają inne rodzaje dokumentów sprzedaży.
            $found = $this->api->findSalesInvoiceByPelnyNumer($invoiceNumber, $dataOd, $dataDo, null);
        }

        if (($found['status'] ?? null) !== 'success' || empty($found['invoice']) || ! is_array($found['invoice'])) {
            $rangeHint = "{$dataOd} – {$dataDo}";

            return [
                'success' => false,
                'message' => ($found['message'] ?? 'Nie znaleziono faktury w iFirma po numerze.')
                    ." Zakres wyszukiwania: {$rangeHint}.",
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
     *     issue_date: string|null,
     *     due_date: string|null,
     *     source: string
     * }
     */
    public function snapshotFromInvoiceRow(array $row, string $source, ?string $invoiceId = null): array
    {
        $paid = $this->toFloatOrNull(
            $row['Zaplacono']
                ?? $row['ZaplaconoNaDokumencie']
                ?? null
        );
        $gross = $this->toFloatOrNull(
            $row['Brutto']
                ?? $row['WartoscBrutto']
                ?? $row['Razem']
                ?? null
        );
        if ($gross === null) {
            $gross = $this->sumPozycjeBrutto($row['Pozycje'] ?? null);
        }

        $dueRaw = $row['TerminPlatnosci'] ?? null;
        $dueDate = null;
        if (is_string($dueRaw) && trim($dueRaw) !== '') {
            try {
                $dueDate = Carbon::parse($dueRaw)->toDateString();
            } catch (\Throwable) {
                $dueDate = null;
            }
        }

        $issueRaw = $row['DataWystawienia'] ?? $row['DataWystawieniaFaktury'] ?? null;
        $issueDate = null;
        if (is_string($issueRaw) && trim($issueRaw) !== '') {
            try {
                $issueDate = Carbon::parse($issueRaw)->toDateString();
            } catch (\Throwable) {
                $issueDate = null;
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
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'source' => $source,
        ];
    }

    /**
     * Suma brutto z pozycji faktury (GET fakturakraj/{id} nie zawsze ma Brutto, ma WartoscBrutto / Pozycje).
     */
    public function sumPozycjeBrutto(mixed $pozycje): ?float
    {
        if (! is_array($pozycje) || $pozycje === []) {
            return null;
        }

        $sum = 0.0;
        $any = false;
        foreach ($pozycje as $pozycja) {
            if (! is_array($pozycja)) {
                continue;
            }
            $value = $this->toFloatOrNull(
                $pozycja['WartoscBrutto']
                    ?? $pozycja['Brutto']
                    ?? $pozycja['CenaBrutto']
                    ?? null
            );
            if ($value === null) {
                continue;
            }
            $sum += $value;
            $any = true;
        }

        return $any ? round($sum, 2) : null;
    }

    public function deriveStatus(?float $paid, ?float $gross, ?string $dueDate): string
    {
        $paid = $paid ?? 0.0;

        if ($gross !== null && $gross > self::AMOUNT_EPSILON && $paid + self::AMOUNT_EPSILON >= $gross) {
            return self::STATUS_PAID;
        }

        if ($paid > self::AMOUNT_EPSILON) {
            // Częściowa wpłata — nawet bez Brutto (np. tylko Zaplacono > 0).
            if ($gross === null || $paid + self::AMOUNT_EPSILON < $gross) {
                return self::STATUS_PARTIAL;
            }
        }

        // Brak Brutto na szczegółach faktury nie powinien dawać „Nieznany”,
        // gdy znamy TerminPlatnosci / brak wpłaty — to typowy payload GET fakturakraj/{id}.
        if ($gross === null && $paid <= self::AMOUNT_EPSILON && $dueDate === null) {
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
     * Zakres dat do GET faktury.json.
     * Preferuje miesiąc z numeru FV (np. 239/6/2026 → czerwiec), potem datę z sprawy / zamówienia.
     * Dzięki temu sync działa także gdy order_date jest w innym miesiącu niż wystawienie FV.
     *
     * @return array{0: string, 1: string}
     */
    public function resolveSearchDateRange(
        FormOrder $order,
        ?string $invoiceNumber = null,
        mixed $invoiceDateHint = null
    ): array {
        $points = [];

        $fromNumber = $this->parseIssueMonthFromInvoiceNumber(
            $invoiceNumber ?: (string) ($order->invoice_number ?? '')
        );
        if ($fromNumber !== null) {
            $points[] = $fromNumber->copy()->startOfMonth();
            $points[] = $fromNumber->copy()->endOfMonth();
        }

        $hint = $this->toCarbonDate($invoiceDateHint);
        if ($hint !== null) {
            $points[] = $hint->copy()->startOfMonth();
            $points[] = $hint->copy()->endOfMonth();
        }

        if ($order->order_date) {
            $points[] = $order->order_date->copy()->startOfDay();
        }
        if ($order->created_at) {
            $points[] = $order->created_at->copy()->startOfDay();
        }

        if ($points === []) {
            $points[] = now()->startOfDay();
        }

        /** @var Carbon $min */
        $min = $points[0];
        /** @var Carbon $max */
        $max = $points[0];
        foreach ($points as $point) {
            if ($point->lt($min)) {
                $min = $point;
            }
            if ($point->gt($max)) {
                $max = $point;
            }
        }

        return [
            $min->copy()->subDays(14)->toDateString(),
            $max->copy()->addDays(45)->toDateString(),
        ];
    }

    /**
     * Parsuje miesiąc wystawienia z numeru iFirma: {nr}/{miesiąc}/{rok}.
     * Przykłady: 239/6/2026, 12/06/2025.
     */
    public function parseIssueMonthFromInvoiceNumber(?string $invoiceNumber): ?Carbon
    {
        if ($invoiceNumber === null) {
            return null;
        }

        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '' || ! preg_match('/^(\d+)\s*\/\s*(\d{1,2})\s*\/\s*(\d{4})\b/u', $invoiceNumber, $matches)) {
            return null;
        }

        $month = (int) $matches[2];
        $year = (int) $matches[3];
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return null;
        }

        return Carbon::create($year, $month, 1)->startOfDay();
    }

    private function toCarbonDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->copy();
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value)->startOfDay();
        }
        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
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
