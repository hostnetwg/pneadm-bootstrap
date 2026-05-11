<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRevenueRecordRequest;
use App\Models\FormOrder;
use App\Models\OnlinePaymentOrder;
use App\Http\Requests\UpdateRevenueRecordRequest;
use App\Models\RevenueRecord;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    /**
     * Wyświetl stronę raportów księgowych
     */
    public function reportsIndex(Request $request)
    {
        // Pobierz parametry filtrowania
        $filterType = $request->get('filter_type', 'year'); // 'year' lub 'range'
        $selectedYear = $request->get('year', date('Y'));
        $selectedYear = (int) $selectedYear;

        // Parametry zakresu dat
        $startYear = (int) $request->get('start_year', date('Y'));
        $startMonth = (int) $request->get('start_month', 1);
        $endYear = (int) $request->get('end_year', date('Y'));
        $endMonth = (int) $request->get('end_month', 12);

        // Walidacja zakresu dat
        if ($filterType === 'range') {
            // Sprawdź czy zakres jest poprawny
            if ($startYear > $endYear || ($startYear == $endYear && $startMonth > $endMonth)) {
                return redirect()
                    ->route('accounting.reports.index')
                    ->with('error', 'Nieprawidłowy zakres dat. Data "od" musi być wcześniejsza niż data "do".');
            }
        }

        // Pobierz dane w zależności od typu filtra
        if ($filterType === 'range') {
            $monthlyData = RevenueRecord::getDataForDateRange($startYear, $startMonth, $endYear, $endMonth);
            $totalForPeriod = RevenueRecord::getTotalForDateRange($startYear, $startMonth, $endYear, $endMonth);
            $monthsCount = count($monthlyData);
            $averageMonthly = $monthsCount > 0 ? $totalForPeriod / $monthsCount : 0;
        } else {
            // Tryb roku (zachowana kompatybilność wsteczna)
            $monthlyData = RevenueRecord::getMonthlyData($selectedYear);
            $totalForPeriod = RevenueRecord::getTotalForYear($selectedYear);
            $monthsCount = 12;
            $averageMonthly = $totalForPeriod / 12;
        }

        // Pobierz dostępne lata (lata, w których są rekordy)
        $availableYears = RevenueRecord::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        // Jeśli brak rekordów, dodaj bieżący rok do listy
        if (empty($availableYears)) {
            $availableYears[] = (int) date('Y');
        }

        // Najlepszy i najsłabszy miesiąc
        $bestMonth = null;
        $worstMonth = null;
        $bestAmount = 0;
        $worstAmount = PHP_FLOAT_MAX;

        foreach ($monthlyData as $data) {
            if ($data['amount'] > $bestAmount) {
                $bestAmount = $data['amount'];
                $bestMonth = $data;
            }
            if ($data['amount'] < $worstAmount && $data['amount'] > 0) {
                $worstAmount = $data['amount'];
                $worstMonth = $data;
            }
        }

        // Trend - porównanie z poprzednim okresem o tej samej długości
        $trend = 0;
        $totalPreviousPeriod = 0;

        if ($filterType === 'range') {
            // Oblicz poprzedni zakres (o tyle samo miesięcy wstecz)
            $rangeMonths = $monthsCount;
            $prevEndYear = $startYear;
            $prevEndMonth = $startMonth - 1;
            if ($prevEndMonth < 1) {
                $prevEndMonth = 12;
                $prevEndYear--;
            }

            $prevStartYear = $prevEndYear;
            $prevStartMonth = $prevEndMonth;
            for ($i = 1; $i < $rangeMonths; $i++) {
                $prevStartMonth--;
                if ($prevStartMonth < 1) {
                    $prevStartMonth = 12;
                    $prevStartYear--;
                }
            }

            $totalPreviousPeriod = RevenueRecord::getTotalForDateRange($prevStartYear, $prevStartMonth, $prevEndYear, $prevEndMonth);
        } else {
            // Tryb roku - porównanie z poprzednim rokiem
            $previousYear = $selectedYear - 1;
            $totalPreviousPeriod = RevenueRecord::getTotalForYear($previousYear);
        }

        $trend = $totalPreviousPeriod > 0
            ? (($totalForPeriod - $totalPreviousPeriod) / $totalPreviousPeriod) * 100
            : 0;

        // Dane do porównania miesiąc do miesiąca dla wszystkich lat
        $monthToMonthComparison = RevenueRecord::getMonthToMonthComparison(2020);

        return view('accounting.reports.index', [
            'monthlyData' => $monthlyData,
            'filterType' => $filterType,
            'selectedYear' => $selectedYear,
            'startYear' => $startYear,
            'startMonth' => $startMonth,
            'endYear' => $endYear,
            'endMonth' => $endMonth,
            'availableYears' => $availableYears,
            'totalForPeriod' => $totalForPeriod,
            'averageMonthly' => $averageMonthly,
            'bestMonth' => $bestMonth,
            'worstMonth' => $worstMonth,
            'trend' => $trend,
            'totalPreviousPeriod' => $totalPreviousPeriod,
            'monthsCount' => $monthsCount,
            'monthToMonthComparison' => $monthToMonthComparison,
        ]);
    }

    /**
     * Wyświetl stronę wprowadzania danych księgowych
     */
    public function dataEntryIndex(Request $request)
    {
        // Filtrowanie po roku (domyślnie wszystkie)
        $selectedYear = $request->get('year');

        $query = RevenueRecord::with('user')->latestFirst();

        if ($selectedYear) {
            $query->forYear((int) $selectedYear);
        }

        $revenueRecords = $query->paginate(20);

        // Pobierz dostępne lata dla filtra
        $availableYears = RevenueRecord::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return view('accounting.data-entry.index', [
            'revenueRecords' => $revenueRecords,
            'selectedYear' => $selectedYear,
            'availableYears' => $availableYears,
        ]);
    }

    /**
     * Zapisz nowy rekord przychodu
     */
    public function dataEntryStore(StoreRevenueRecordRequest $request)
    {
        try {
            $revenueRecord = RevenueRecord::create([
                'year' => $request->year,
                'month' => $request->month,
                'amount' => $request->amount,
                'notes' => $request->notes,
                'source' => $request->source ?? 'manual',
                'user_id' => Auth::id(),
            ]);

            return redirect()
                ->route('accounting.data-entry.index')
                ->with('success', 'Rekord przychodu został zapisany pomyślnie.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas zapisywania rekordu: '.$e->getMessage());
        }
    }

    /**
     * Aktualizuj istniejący rekord przychodu
     */
    public function dataEntryUpdate(UpdateRevenueRecordRequest $request, $id)
    {
        try {
            $revenueRecord = RevenueRecord::findOrFail($id);

            $revenueRecord->update([
                'year' => $request->year,
                'month' => $request->month,
                'amount' => $request->amount,
                'notes' => $request->notes,
                'source' => $request->source ?? 'manual',
            ]);

            return redirect()
                ->route('accounting.data-entry.index')
                ->with('success', 'Rekord przychodu został zaktualizowany pomyślnie.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas aktualizacji rekordu: '.$e->getMessage());
        }
    }

    /**
     * Usuń rekord przychodu (soft delete)
     */
    public function dataEntryDestroy($id)
    {
        try {
            $revenueRecord = RevenueRecord::findOrFail($id);
            $revenueRecord->delete();

            return redirect()
                ->route('accounting.data-entry.index')
                ->with('success', 'Rekord przychodu został usunięty pomyślnie.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Wystąpił błąd podczas usuwania rekordu: '.$e->getMessage());
        }
    }

    /**
     * Lista dłużników (w przygotowaniu).
     */
    public function debtorsIndex()
    {
        return view('accounting.debtors.index');
    }

    /**
     * Live lookup danych pod ponaglenie po numerze faktury.
     * Uwaga: dla faktur odroczonych status opłacenia jest weryfikowany w iFirma (poza systemem).
     */
    public function debtorsLookup(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'match_mode' => ['nullable', 'string', 'in:exact,partial'],
        ]);

        $query = trim((string) $validated['q']);
        $matchMode = (string) ($validated['match_mode'] ?? 'partial');

        $matchesQuery = FormOrder::query()
            ->with(['primaryParticipant', 'onlinePaymentOrders'])
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->where('invoice_number', '!=', '0');

        if ($matchMode === 'exact') {
            $matchesQuery->where('invoice_number', $query);
        } else {
            $matchesQuery
                ->where('invoice_number', 'LIKE', '%'.$query.'%')
                ->orderByRaw('CASE WHEN invoice_number = ? THEN 0 ELSE 1 END', [$query]);
        }

        $matches = $matchesQuery
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        if ($matches->isEmpty()) {
            return response()->json([
                'matches' => [],
                'selected' => null,
                'history' => [
                    'orders' => [],
                    'stats' => [
                        'total_orders' => 0,
                        'total_value' => 0,
                        'deferred_invoice_orders' => 0,
                        'online_gateway_orders' => 0,
                        'online_paid_orders' => 0,
                        'online_pending_orders' => 0,
                        'online_failed_or_cancelled_orders' => 0,
                    ],
                    'identity' => [
                        'recipient_nip' => null,
                        'buyer_nip' => null,
                        'emails' => [],
                    ],
                    'sources' => [],
                ],
            ]);
        }

        $selected = $matches->first();
        $historyPayload = $this->buildDebtorHistoryPayload($selected);

        return response()->json([
            'matches' => $matches->map(fn (FormOrder $order) => [
                'id' => $order->id,
                'invoice_number' => $order->invoice_number,
                'order_date' => $this->formatDateTime($order->order_date),
                'product_name' => $order->product_name,
                'buyer_name' => $order->buyer_name,
                'recipient_name' => $order->recipient_name,
            ])->values(),
            'selected' => [
                'id' => $selected->id,
                'invoice_number' => $selected->invoice_number,
                'order_date' => $this->formatDateTime($selected->order_date),
                'invoice_date' => $this->formatDate($selected->order_date),
                'product_name' => $selected->product_name,
                'product_price' => (float) ($selected->product_price ?? 0),
                'invoice_payment_delay' => $this->resolvedPaymentDelay($selected),
                'payment_due_date' => $this->paymentDueDate($selected)?->format('Y-m-d'),
                'overdue_days' => $this->overdueDays($selected),
                'payment_mode' => $selected->payment_mode,
                'payment_mode_label' => $selected->paymentModeLabelWithGateway(),
                'payment_status_label' => FormOrder::paymentStatusLabel($selected->payment_status),
                'payment_status_hint' => $this->buildPaymentStatusHint($selected),
                'orderer' => [
                    'name' => $selected->orderer_name,
                    'email' => $selected->orderer_email,
                    'phone' => $selected->orderer_phone,
                    'address' => $selected->orderer_address,
                    'postal_code' => $selected->orderer_postal_code,
                    'city' => $selected->orderer_city,
                ],
                'participant' => [
                    'name' => $selected->display_participant_name,
                    'email' => $selected->display_participant_email,
                ],
                'buyer' => [
                    'name' => $selected->buyer_name,
                    'nip' => $selected->formatted_nip,
                    'address' => $selected->buyer_address,
                    'postal_code' => $selected->buyer_postal_code,
                    'city' => $selected->buyer_city,
                ],
                'recipient' => [
                    'name' => $selected->recipient_name,
                    'nip' => $selected->recipient_formatted_nip,
                    'address' => $selected->recipient_address,
                    'postal_code' => $selected->recipient_postal_code,
                    'city' => $selected->recipient_city,
                ],
            ],
            'history' => $historyPayload,
        ]);
    }

    private function buildDebtorHistoryPayload(FormOrder $selected): array
    {
        $recipientNip = preg_replace('/\D+/', '', (string) ($selected->recipient_nip ?? '')) ?: null;
        $buyerNip = preg_replace('/\D+/', '', (string) ($selected->buyer_nip ?? '')) ?: null;
        $emails = collect([
            strtolower(trim((string) ($selected->orderer_email ?? ''))),
            strtolower(trim((string) ($selected->display_participant_email ?? ''))),
        ])->filter()->unique()->values();

        $ordersByRecipientNip = collect();
        $ordersByBuyerNip = collect();
        $ordersByEmails = collect();

        if ($recipientNip !== null) {
            $ordersByRecipientNip = FormOrder::query()
                ->with(['primaryParticipant', 'onlinePaymentOrders'])
                ->whereRaw($this->normalizedDigitsSql('recipient_nip').' = ?', [$recipientNip])
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        }

        if ($buyerNip !== null) {
            $ordersByBuyerNip = FormOrder::query()
                ->with(['primaryParticipant', 'onlinePaymentOrders'])
                ->whereRaw($this->normalizedDigitsSql('buyer_nip').' = ?', [$buyerNip])
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        }

        if ($emails->isNotEmpty()) {
            $emailValues = $emails->all();
            $ordersByEmails = FormOrder::query()
                ->with(['primaryParticipant', 'onlinePaymentOrders'])
                ->where(function ($query) use ($emailValues) {
                    $query->whereIn(DB::raw('LOWER(TRIM(orderer_email))'), $emailValues)
                        ->orWhereHas('primaryParticipant', function ($participantQuery) use ($emailValues) {
                            $participantQuery->whereIn(DB::raw('LOWER(TRIM(participant_email))'), $emailValues);
                        });
                })
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        }

        $allOrders = $ordersByRecipientNip
            ->concat($ordersByBuyerNip)
            ->concat($ordersByEmails)
            ->unique('id')
            ->sortByDesc('id')
            ->values();

        $stats = [
            'total_orders' => $allOrders->count(),
            'total_value' => (float) $allOrders->sum(fn (FormOrder $order) => (float) ($order->product_price ?? 0)),
            'deferred_invoice_orders' => $allOrders->where('payment_mode', FormOrder::PAYMENT_MODE_DEFERRED_INVOICE)->count(),
            'online_gateway_orders' => $allOrders->where('payment_mode', FormOrder::PAYMENT_MODE_ONLINE_GATEWAY)->count(),
            'online_paid_orders' => $allOrders->filter(fn (FormOrder $order) => $this->latestGatewayStatus($order) === OnlinePaymentOrder::STATUS_PAID)->count(),
            'online_pending_orders' => $allOrders->filter(fn (FormOrder $order) => in_array($this->latestGatewayStatus($order), [OnlinePaymentOrder::STATUS_PENDING, OnlinePaymentOrder::STATUS_CREATED], true))->count(),
            'online_failed_or_cancelled_orders' => $allOrders->filter(fn (FormOrder $order) => in_array($this->latestGatewayStatus($order), [OnlinePaymentOrder::STATUS_FAILED, OnlinePaymentOrder::STATUS_CANCELLED], true))->count(),
        ];

        return [
            'identity' => [
                'recipient_nip' => $recipientNip,
                'buyer_nip' => $buyerNip,
                'emails' => $emails->all(),
            ],
            'sources' => [
                'recipient_nip_matches' => $ordersByRecipientNip->count(),
                'buyer_nip_matches' => $ordersByBuyerNip->count(),
                'email_matches' => $ordersByEmails->count(),
            ],
            'stats' => $stats,
            'orders' => $allOrders->map(function (FormOrder $order) use ($recipientNip, $buyerNip, $emails) {
                $linkReasons = $this->resolveLinkReasons($order, $recipientNip, $buyerNip, $emails->all());

                return [
                    'id' => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'order_date' => $this->formatDateTime($order->order_date),
                    'product_name' => $order->product_name,
                    'product_price' => (float) ($order->product_price ?? 0),
                    'invoice_date' => $this->formatDate($order->order_date),
                    'invoice_payment_delay' => $this->resolvedPaymentDelay($order),
                    'payment_due_date' => $this->paymentDueDate($order)?->format('Y-m-d'),
                    'overdue_days' => $this->overdueDays($order),
                    'orderer_email' => $order->orderer_email,
                    'participant_email' => $order->display_participant_email,
                    'orderer_name' => $order->orderer_name,
                    'participant_name' => $order->display_participant_name,
                    'buyer_name' => $order->buyer_name,
                    'recipient_name' => $order->recipient_name,
                    'recipient_nip' => $order->recipient_formatted_nip,
                    'buyer_nip' => $order->formatted_nip,
                    'payment_mode' => $order->payment_mode,
                    'payment_mode_label' => $order->paymentModeLabelWithGateway(),
                    'payment_status_label' => FormOrder::paymentStatusLabel($order->payment_status),
                    'payment_status_hint' => $this->buildPaymentStatusHint($order),
                    'latest_gateway_status' => $this->latestGatewayStatus($order),
                    'link_reasons' => $linkReasons,
                    'link_reasons_label' => implode(', ', array_column($linkReasons, 'label')),
                ];
            })->values(),
        ];
    }

    private function normalizedDigitsSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '-', ''), ' ', ''), '.', ''), '/', ''), '_', '')";
    }

    private function latestGatewayStatus(FormOrder $order): ?string
    {
        if ($order->payment_mode !== FormOrder::PAYMENT_MODE_ONLINE_GATEWAY) {
            return null;
        }

        if ($order->relationLoaded('onlinePaymentOrders')) {
            return $order->onlinePaymentOrders->sortByDesc('id')->first()?->status;
        }

        return $order->onlinePaymentOrders()->orderByDesc('id')->value('status');
    }

    private function buildPaymentStatusHint(FormOrder $order): string
    {
        if ($order->payment_mode === FormOrder::PAYMENT_MODE_ONLINE_GATEWAY) {
            $status = $this->latestGatewayStatus($order);

            return $status
                ? 'Status z bramki płatniczej: '.$status
                : 'Płatność online: brak statusu bramki w rekordzie.';
        }

        return 'Faktura odroczona: status opłacenia weryfikujemy ręcznie w iFirma.';
    }

    private function resolvedPaymentDelay(FormOrder $order): int
    {
        $delay = (int) ($order->invoice_payment_delay ?? 0);

        return $delay > 0 ? $delay : 14;
    }

    private function paymentDueDate(FormOrder $order): ?\Carbon\Carbon
    {
        if (! $order->order_date instanceof CarbonInterface) {
            return null;
        }

        return $order->order_date->copy()->addDays($this->resolvedPaymentDelay($order));
    }

    private function overdueDays(FormOrder $order): int
    {
        if ($order->payment_mode === FormOrder::PAYMENT_MODE_ONLINE_GATEWAY) {
            return 0;
        }

        $dueDate = $this->paymentDueDate($order);
        if ($dueDate === null) {
            return 0;
        }

        $today = now()->startOfDay();
        if ($dueDate->startOfDay()->greaterThanOrEqualTo($today)) {
            return 0;
        }

        return $dueDate->diffInDays($today);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (! $value instanceof CarbonInterface) {
            return null;
        }

        return $value->format('Y-m-d H:i');
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value instanceof CarbonInterface) {
            return null;
        }

        return $value->format('Y-m-d');
    }

    private function resolveLinkReasons(FormOrder $order, ?string $selectedRecipientNip, ?string $selectedBuyerNip, array $selectedEmails): array
    {
        $reasons = [];

        $orderRecipientNip = preg_replace('/\D+/', '', (string) ($order->recipient_nip ?? '')) ?: null;
        $orderBuyerNip = preg_replace('/\D+/', '', (string) ($order->buyer_nip ?? '')) ?: null;
        $ordererEmail = strtolower(trim((string) ($order->orderer_email ?? ''))) ?: null;
        $participantEmail = strtolower(trim((string) ($order->display_participant_email ?? ''))) ?: null;

        if (! empty($orderRecipientNip) && $selectedRecipientNip !== null && $orderRecipientNip === $selectedRecipientNip) {
            $reasons[] = [
                'key' => 'recipient_nip',
                'label' => 'NIP odbiorcy',
                'value' => $orderRecipientNip,
                'strength' => 'high',
            ];
        }

        if (! empty($participantEmail) && in_array($participantEmail, $selectedEmails, true)) {
            $reasons[] = [
                'key' => 'participant_email',
                'label' => 'E-mail uczestnika',
                'value' => $participantEmail,
                'strength' => 'high',
            ];
        }

        if (! empty($ordererEmail) && in_array($ordererEmail, $selectedEmails, true)) {
            $reasons[] = [
                'key' => 'orderer_email',
                'label' => 'E-mail zamawiającego',
                'value' => $ordererEmail,
                'strength' => 'high',
            ];
        }

        if (! empty($orderBuyerNip) && $selectedBuyerNip !== null && $orderBuyerNip === $selectedBuyerNip) {
            $reasons[] = [
                'key' => 'buyer_nip',
                'label' => 'NIP nabywcy',
                'value' => $orderBuyerNip,
                'strength' => 'low',
            ];
        }

        return $reasons;
    }
}
