<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRevenueRecordRequest;
use App\Http\Requests\UpdateRevenueRecordRequest;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\DebtCaseContact;
use App\Models\FormOrder;
use App\Models\OnlinePaymentOrder;
use App\Models\RevenueRecord;
use App\Services\Bank\BankStatementImportService;
use App\Services\DebtCaseAutoCloseService;
use App\Services\DebtCustomerProfileService;
use App\Services\IfirmaInvoicePaymentRegistrationService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

    public function collectionsIndex(Request $request)
    {
        // Brak parametru status → domyślnie kolejka robocza (niezamknięte).
        // Jawne status= (puste) → wszystkie sprawy, w tym zamknięte.
        $status = $request->has('status')
            ? (string) $request->get('status', '')
            : 'active';
        $segment = (string) $request->get('segment', '');
        $search = trim((string) $request->get('search', ''));

        $casesQuery = DebtCase::query()
            ->with(['formOrder.primaryParticipant', 'assignedTo', 'createdBy'])
            ->latest('id');

        if ($status === 'active') {
            $casesQuery->active();
        } elseif ($status !== '' && array_key_exists($status, DebtCase::statusLabels())) {
            $casesQuery->where('status', $status);
        }

        if ($segment !== '' && array_key_exists($segment, DebtCase::segmentLabels())) {
            $casesQuery->where('customer_segment', $segment);
        }

        if ($search !== '') {
            $casesQuery->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('ksef_number', 'like', "%{$search}%")
                    ->orWhereHas('formOrder', function ($formOrderQuery) use ($search) {
                        $formOrderQuery->where('invoice_number', 'like', "%{$search}%")
                            ->orWhere('ksef_number', 'like', "%{$search}%")
                            ->orWhere('buyer_name', 'like', "%{$search}%")
                            ->orWhere('recipient_name', 'like', "%{$search}%")
                            ->orWhere('orderer_email', 'like', "%{$search}%")
                            ->orWhere('buyer_nip', 'like', "%{$search}%")
                            ->orWhere('recipient_nip', 'like', "%{$search}%");

                        if (ctype_digit($search)) {
                            $formOrderQuery->orWhere('id', (int) $search);
                        }
                    });

                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search)
                        ->orWhere('form_order_id', (int) $search);
                }
            });
        }

        $cases = $casesQuery->paginate(25)->withQueryString();

        $stats = [
            'active' => DebtCase::active()->count(),
            'vip' => DebtCase::active()
                ->whereIn('customer_segment', [DebtCase::SEGMENT_VIP, DebtCase::SEGMENT_VIP_OVERDUE])
                ->count(),
            'promised' => DebtCase::where('status', DebtCase::STATUS_PROMISED)->count(),
            'due_today' => DebtCase::active()
                ->whereNotNull('next_action_at')
                ->where('next_action_at', '<=', now()->endOfDay())
                ->count(),
        ];

        return view('accounting.collections.index', [
            'cases' => $cases,
            'stats' => $stats,
            'status' => $status,
            'segment' => $segment,
            'search' => $search,
            'statusLabels' => DebtCase::statusLabels(),
            'segmentLabels' => DebtCase::segmentLabels(),
        ]);
    }

    public function collectionsStore(Request $request, DebtCustomerProfileService $profileService)
    {
        $validated = $request->validate([
            'form_order_id' => ['required', 'integer', 'exists:form_orders,id'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'next_action_at' => ['nullable', 'date'],
        ]);

        $order = FormOrder::with(['primaryParticipant', 'onlinePaymentOrders'])->findOrFail($validated['form_order_id']);
        $existing = DebtCase::withTrashed()->where('form_order_id', $order->id)->first();
        if ($existing !== null && $existing->trashed()) {
            $existing->restore();
        }
        if ($existing !== null) {
            return redirect()
                ->route('accounting.collections.show', $existing)
                ->with('success', 'Sprawa windykacyjna dla tego zamówienia już istnieje.');
        }

        $profile = $profileService->profileForOrder($order);
        $delay = (int) ($order->invoice_payment_delay ?: 14);
        $invoiceDate = $order->order_date?->copy();
        $dueDate = $invoiceDate?->copy()->addDays($delay);

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'created_by' => Auth::id(),
            'assigned_to_id' => Auth::id(),
            'status' => DebtCase::STATUS_OPEN,
            'priority' => $profile['risk_score'] >= 50 ? DebtCase::PRIORITY_HIGH : DebtCase::PRIORITY_NORMAL,
            'customer_segment' => $profile['customer_segment'],
            'risk_score' => $profile['risk_score'],
            'relationship_score' => $profile['relationship_score'],
            'vip_reason' => $profile['vip_reason'],
            'invoice_number' => $order->invoice_number,
            'ksef_number' => $order->ksef_number,
            'amount_gross' => $order->product_price,
            'invoice_date' => $invoiceDate?->toDateString(),
            'due_date' => $dueDate?->toDateString(),
            'opened_at' => now(),
            'next_action_at' => $validated['next_action_at'] ?? null,
            'summary' => $validated['summary'] ?? null,
        ]);

        $case->actions()->create([
            'user_id' => Auth::id(),
            'action_type' => DebtCaseAction::TYPE_CASE_OPENED,
            'happened_at' => now(),
            'note' => ! empty($validated['summary'])
                ? $validated['summary']
                : 'Utworzono sprawę windykacyjną.',
        ]);

        return redirect()
            ->route('accounting.collections.show', $case)
            ->with('success', 'Utworzono sprawę windykacyjną.');
    }

    public function collectionsShow(Request $request, DebtCase $debtCase, DebtCustomerProfileService $profileService)
    {
        $debtCase->load([
            'formOrder.primaryParticipant',
            'formOrder.primaryParticipant.participant',
            'formOrder.onlinePaymentOrders',
            'formOrder.course.instructor',
            'actions.user',
            'contacts.createdBy',
            'assignedTo',
            'createdBy',
            'bankTransactionMatches' => function ($q) {
                $q->where('status', \App\Models\BankTransactionMatch::STATUS_ACCEPTED)
                    ->with(['transaction', 'acceptedBy'])
                    ->latest('accepted_at');
            },
        ]);

        $profile = $profileService->profileForOrder($debtCase->formOrder);
        $defaultBankAmount = (float) ($debtCase->amount_gross ?? $debtCase->formOrder?->product_price ?? 0);
        $bankTransferAmount = $defaultBankAmount > 0 ? round($defaultBankAmount, 2) : null;

        // Kolejność jak na liście (najnowsze pierwsze): poprzednia = nowsza (wyższe id), następna = starsza (niższe id).
        $previousCaseAll = DebtCase::query()
            ->where('id', '>', $debtCase->id)
            ->orderBy('id')
            ->first(['id']);
        $nextCaseAll = DebtCase::query()
            ->where('id', '<', $debtCase->id)
            ->orderByDesc('id')
            ->first(['id']);
        $previousCaseActive = DebtCase::query()
            ->active()
            ->where('id', '>', $debtCase->id)
            ->orderBy('id')
            ->first(['id']);
        $nextCaseActive = DebtCase::query()
            ->active()
            ->where('id', '<', $debtCase->id)
            ->orderByDesc('id')
            ->first(['id']);

        return view('accounting.collections.show', [
            'case' => $debtCase,
            'previousCase' => $previousCaseActive,
            'nextCase' => $nextCaseActive,
            'previousCaseAll' => $previousCaseAll,
            'nextCaseAll' => $nextCaseAll,
            'previousCaseActive' => $previousCaseActive,
            'nextCaseActive' => $nextCaseActive,
            'profile' => $profile,
            'relatedOrders' => $profileService->relatedOrders($profile['identity']),
            'statusLabels' => DebtCase::statusLabels(),
            'segmentLabels' => DebtCase::segmentLabels(),
            'actionTypeLabels' => collect(DebtCaseAction::typeLabels())
                ->except([
                    DebtCaseAction::TYPE_CASE_OPENED,
                    DebtCaseAction::TYPE_STATUS_UPDATE,
                    DebtCaseAction::TYPE_IFIRMA_SYNC,
                    DebtCaseAction::TYPE_BANK_MATCH,
                ])
                ->all(),
            'contactTypeLabels' => DebtCaseContact::typeLabels(),
            'ifirmaPaymentStatusLabels' => IfirmaInvoicePaymentStatusService::statusLabels(),
            'bankPayments' => $debtCase->bankTransactionMatches,
            'bankTransferSearch' => '',
            'bankTransferAmount' => $bankTransferAmount,
            'bankAfterOrderDate' => true,
        ]);
    }

    public function collectionsBankTransactionSearch(Request $request, DebtCase $debtCase)
    {
        $validated = $request->validate([
            'bank_search' => ['required', 'string', 'min:2', 'max:128'],
            'bank_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_after_order' => ['nullable', 'boolean'],
            'bank_unlinked_only' => ['nullable', 'boolean'],
            'bank_search_exact' => ['nullable', 'boolean'],
        ]);

        $search = trim($validated['bank_search']);
        $amount = null;
        if (array_key_exists('bank_amount', $validated) && $validated['bank_amount'] !== null && $validated['bank_amount'] !== '') {
            $amount = round((float) $validated['bank_amount'], 2);
            if ($amount <= 0) {
                $amount = null;
            }
        }

        $afterOrder = $request->boolean('bank_after_order', true);
        $unlinkedOnly = $request->boolean('bank_unlinked_only', true);
        $exactSearch = $request->boolean('bank_search_exact', false);
        $notBefore = $afterOrder
            ? $debtCase->formOrder?->order_date?->toDateString()
            : null;

        $caseAmount = round((float) ($debtCase->amount_gross ?? $debtCase->formOrder?->product_price ?? 0), 2);
        $candidates = $this->bankTransferCandidates($search, $amount, $notBefore, $unlinkedOnly, $exactSearch);

        return response()->json([
            'candidates' => $candidates->map(function (BankTransaction $candidate) use ($debtCase, $caseAmount) {
                $amountMatches = abs((float) $candidate->amount - $caseAmount) <= 0.01;
                $blockingMatch = $candidate->relationLoaded('matches')
                    ? $candidate->matches->first(fn (BankTransactionMatch $match) => in_array($match->status, [
                        BankTransactionMatch::STATUS_ACCEPTED,
                        BankTransactionMatch::STATUS_IGNORED,
                    ], true))
                    : $candidate->matches()
                        ->whereIn('status', [
                            BankTransactionMatch::STATUS_ACCEPTED,
                            BankTransactionMatch::STATUS_IGNORED,
                        ])
                        ->orderByDesc('id')
                        ->first();
                $isLinkable = $blockingMatch === null;
                $summary = sprintf(
                    '#%d · %s · %s %s',
                    $candidate->id,
                    $candidate->operation_date?->format('Y-m-d') ?? '—',
                    number_format((float) $candidate->amount, 2, ',', ' '),
                    $candidate->currency
                );

                return [
                    'id' => $candidate->id,
                    'operation_date' => $candidate->operation_date?->format('Y-m-d'),
                    'amount' => (float) $candidate->amount,
                    'amount_formatted' => number_format((float) $candidate->amount, 2, ',', ' '),
                    'currency' => $candidate->currency,
                    'amount_matches' => $amountMatches,
                    'account_label' => $candidate->account_label,
                    'description' => $candidate->description,
                    'description_short' => Str::limit((string) $candidate->description, 220),
                    'description_confirm' => Str::limit((string) $candidate->description, 180),
                    'import_id' => $candidate->bank_statement_import_id,
                    'import_url' => route('accounting.bank-imports.show', $candidate->bank_statement_import_id),
                    'import_filename' => $candidate->import?->original_filename,
                    'link_url' => route('accounting.collections.bank-transactions.link', [$debtCase, $candidate]),
                    'summary' => $summary,
                    'is_linkable' => $isLinkable,
                    'link_status' => $blockingMatch?->status,
                    'link_status_label' => $blockingMatch?->statusLabel(),
                ];
            })->values(),
        ]);
    }

    public function collectionsBankTransactionLink(
        Request $request,
        DebtCase $debtCase,
        BankTransaction $transaction,
        BankStatementImportService $importService,
        IfirmaInvoicePaymentRegistrationService $paymentRegistration,
        DebtCaseAutoCloseService $autoClose
    ) {
        $validated = $request->validate([
            'register_ifirma_payment' => ['nullable', 'boolean'],
        ]);

        try {
            $accepted = $importService->manuallyLinkTransactionToDebtCase(
                $transaction,
                $debtCase,
                Auth::id()
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('accounting.collections.show', $debtCase)
                ->withErrors(['bank_transaction' => $e->getMessage()]);
        }

        $message = sprintf(
            'Ręcznie powiązano przelew #%d ze sprawą #%d.',
            $transaction->id,
            $debtCase->id
        );

        if ($validated['register_ifirma_payment'] ?? false) {
            try {
                $paymentResult = $paymentRegistration->registerFromAcceptedBankMatch(
                    $accepted,
                    $request->user()
                );
            } catch (\Throwable $e) {
                report($e);

                return redirect()
                    ->route('accounting.collections.show', $debtCase)
                    ->with('success', $message.' Akceptacja lokalna OK.')
                    ->with('warning', 'Wpłata w iFirma nie przeszła: '.$e->getMessage());
            }

            if ($paymentResult['success']) {
                $message .= ' '.$paymentResult['message'];
                if ($autoClose->closeIfFullyPaid($accepted->debtCase, $request->user(), $paymentResult['status'] ?? null)) {
                    $message .= ' Sprawę zamknięto automatycznie.';
                }

                return redirect()
                    ->route('accounting.collections.show', $debtCase)
                    ->with('success', $message);
            }

            return redirect()
                ->route('accounting.collections.show', $debtCase)
                ->with('success', $message.' Akceptacja lokalna OK.')
                ->with('warning', 'Wpłata w iFirma nie przeszła: '.$paymentResult['message']);
        }

        return redirect()
            ->route('accounting.collections.show', $debtCase)
            ->with('success', $message.' Status iFirma nie został zmieniony (powiązanie tylko lokalne).');
    }

    private function bankTransferCandidates(
        string $search,
        ?float $amount,
        ?string $notBeforeDate = null,
        bool $unlinkedOnly = true,
        bool $exactSearch = false
    ) {
        $search = trim($search);
        if ($search === '') {
            return collect();
        }

        return BankTransaction::query()
            ->with(['import', 'matches'])
            ->where('is_incoming', true)
            ->when($unlinkedOnly, function ($query) {
                $query->whereDoesntHave('matches', function ($matchQuery) {
                    $matchQuery->whereIn('status', [
                        BankTransactionMatch::STATUS_ACCEPTED,
                        BankTransactionMatch::STATUS_IGNORED,
                    ]);
                });
            })
            ->when($amount !== null, function ($query) use ($amount) {
                $query->whereBetween('amount', [$amount - 0.01, $amount + 0.01]);
            })
            ->when($notBeforeDate !== null && $notBeforeDate !== '', function ($query) use ($notBeforeDate) {
                $query->whereDate('operation_date', '>=', $notBeforeDate);
            })
            ->where(function ($inner) use ($search, $exactSearch) {
                $this->applyBankTransferTextSearch($inner, $search, $exactSearch);
            })
            ->latest('operation_date')
            ->latest('id')
            ->limit(12)
            ->get();
    }

    /**
     * Partial: LIKE %fraza%. Exact: token w tekście (nie fragment dłuższej liczby, np. 63 ≠ 263).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\BankTransaction>|\Illuminate\Database\Query\Builder  $query
     */
    private function applyBankTransferTextSearch($query, string $search, bool $exactSearch): void
    {
        if (! $exactSearch) {
            $query->where('description', 'like', "%{$search}%")
                ->orWhere('account_label', 'like', "%{$search}%")
                ->orWhere('counterparty_account', 'like', "%{$search}%");

            return;
        }

        $pattern = '(^|[^0-9A-Za-z])'.$this->escapeMysqlRegexpLiteral($search).'([^0-9A-Za-z]|$)';

        $query->whereRaw('description REGEXP ?', [$pattern])
            ->orWhereRaw('account_label REGEXP ?', [$pattern])
            ->orWhereRaw('counterparty_account REGEXP ?', [$pattern]);
    }

    private function escapeMysqlRegexpLiteral(string $value): string
    {
        return preg_replace('/([\\\\.^$*+?()\\[\\]{}|])/', '\\\\$1', $value) ?? $value;
    }

    public function collectionsSyncIfirma(
        DebtCase $debtCase,
        IfirmaInvoicePaymentStatusService $paymentStatusService
    ) {
        $result = $paymentStatusService->syncDebtCase($debtCase, Auth::user());

        if (! ($result['success'] ?? false)) {
            return redirect()
                ->route('accounting.collections.show', $debtCase)
                ->withErrors(['ifirma_sync' => $result['message'] ?? 'Synchronizacja z iFirma nie powiodła się.']);
        }

        $message = $result['message'];
        if (($result['status'] ?? null) === IfirmaInvoicePaymentStatusService::STATUS_PAID
            && $debtCase->fresh()->status !== DebtCase::STATUS_CLOSED) {
            $message .= ' Możesz ręcznie zamknąć sprawę po weryfikacji.';
        }

        return redirect()
            ->route('accounting.collections.show', $debtCase)
            ->with('success', $message);
    }

    public function collectionsUpdate(Request $request, DebtCase $debtCase)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(DebtCase::statusLabels()))],
            'priority' => ['required', 'string', 'in:low,normal,high'],
            'customer_segment' => ['required', 'string', 'in:'.implode(',', array_keys(DebtCase::segmentLabels()))],
            'manual_vip' => ['nullable', 'boolean'],
            'do_not_auto_dun' => ['nullable', 'boolean'],
            'vip_reason' => ['nullable', 'string', 'max:255'],
            'next_action_at' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $wasClosed = $debtCase->status === DebtCase::STATUS_CLOSED;
        $isClosing = $validated['status'] === DebtCase::STATUS_CLOSED;
        $manualVip = $request->boolean('manual_vip');
        $doNotAutoDun = $request->boolean('do_not_auto_dun');

        $changes = $this->describeDebtCaseSettingChanges($debtCase, [
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'customer_segment' => $validated['customer_segment'],
            'manual_vip' => $manualVip,
            'do_not_auto_dun' => $doNotAutoDun,
            'vip_reason' => $validated['vip_reason'] ?? null,
            'next_action_at' => $validated['next_action_at'] ?? null,
            'summary' => $validated['summary'] ?? null,
        ]);

        $debtCase->fill([
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'customer_segment' => $validated['customer_segment'],
            'manual_vip' => $manualVip,
            'do_not_auto_dun' => $doNotAutoDun,
            'vip_reason' => $validated['vip_reason'] ?? null,
            'next_action_at' => $validated['next_action_at'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'assigned_to_id' => Auth::id(),
            'closed_at' => $isClosing ? ($debtCase->closed_at ?? now()) : null,
        ]);

        if ($wasClosed && ! $isClosing) {
            $debtCase->closure_reason = null;
        }

        $debtCase->save();

        if ($changes !== []) {
            $debtCase->actions()->create([
                'user_id' => Auth::id(),
                'action_type' => DebtCaseAction::TYPE_STATUS_UPDATE,
                'happened_at' => now(),
                'next_action_at' => $validated['next_action_at'] ?? null,
                'note' => 'Zmiana ustawień: '.implode('; ', $changes),
            ]);
            $debtCase->update([
                'last_action_at' => now(),
            ]);
        }

        return redirect()
            ->route('accounting.collections.show', $debtCase)
            ->with('success', 'Zapisano ustawienia sprawy.');
    }

    public function collectionsActionStore(Request $request, DebtCase $debtCase)
    {
        $validated = $request->validate([
            'action_type' => ['required', 'string', 'in:'.implode(',', array_keys(DebtCaseAction::typeLabels()))],
            'outcome' => ['nullable', 'string', 'max:64'],
            'happened_at' => ['nullable', 'date'],
            'promised_payment_at' => ['nullable', 'date'],
            'next_action_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        $action = $debtCase->actions()->create([
            'user_id' => Auth::id(),
            'action_type' => $validated['action_type'],
            'outcome' => $validated['outcome'] ?? null,
            'happened_at' => $validated['happened_at'] ?? now(),
            'promised_payment_at' => $validated['promised_payment_at'] ?? null,
            'next_action_at' => $validated['next_action_at'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        $status = match ($action->action_type) {
            DebtCaseAction::TYPE_PAYMENT_PROMISE => DebtCase::STATUS_PROMISED,
            DebtCaseAction::TYPE_DISPUTE => DebtCase::STATUS_DISPUTED,
            DebtCaseAction::TYPE_PAUSE => DebtCase::STATUS_PAUSED,
            DebtCaseAction::TYPE_CLOSE => DebtCase::STATUS_CLOSED,
            default => $debtCase->status === DebtCase::STATUS_OPEN ? DebtCase::STATUS_IN_PROGRESS : $debtCase->status,
        };

        $debtCase->update([
            'status' => $status,
            'assigned_to_id' => Auth::id(),
            'last_action_at' => $action->happened_at,
            'next_action_at' => $action->next_action_at,
            'closed_at' => $status === DebtCase::STATUS_CLOSED ? now() : $debtCase->closed_at,
            'closure_reason' => $status === DebtCase::STATUS_CLOSED ? ($action->note ?: $debtCase->closure_reason) : $debtCase->closure_reason,
        ]);

        return redirect()
            ->route('accounting.collections.show', $debtCase)
            ->with('success', 'Dodano działanie do sprawy.');
    }

    public function collectionsContactStore(Request $request, DebtCase $debtCase)
    {
        $validated = $request->validate([
            'contact_type' => ['required', 'string', 'in:'.implode(',', array_keys(DebtCaseContact::typeLabels()))],
            'value' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $debtCase->contacts()->create([
            'created_by' => Auth::id(),
            'contact_type' => $validated['contact_type'],
            'value' => $validated['value'],
            'label' => $validated['label'] ?? null,
            'source' => $validated['source'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
            'notes' => $validated['notes'] ?? null,
        ]);

        $debtCase->update([
            'assigned_to_id' => Auth::id(),
            'last_action_at' => now(),
        ]);

        return redirect()
            ->route('accounting.collections.show', $debtCase)
            ->with('success', 'Dodano kontakt do sprawy.');
    }

    public function collectionsContactDestroy(DebtCase $debtCase, DebtCaseContact $contact)
    {
        if ((int) $contact->debt_case_id !== (int) $debtCase->id) {
            abort(404);
        }

        $contact->delete();

        $debtCase->update([
            'assigned_to_id' => Auth::id(),
            'last_action_at' => now(),
        ]);

        return redirect()
            ->route('accounting.collections.show', $debtCase)
            ->with('success', 'Usunięto kontakt ze sprawy.');
    }

    /**
     * @param  array{
     *     status: string,
     *     priority: string,
     *     customer_segment: string,
     *     manual_vip: bool,
     *     do_not_auto_dun: bool,
     *     vip_reason: ?string,
     *     next_action_at: ?string,
     *     summary: ?string
     * }  $incoming
     * @return list<string>
     */
    private function describeDebtCaseSettingChanges(DebtCase $debtCase, array $incoming): array
    {
        $changes = [];

        if ($debtCase->status !== $incoming['status']) {
            $from = DebtCase::statusLabels()[$debtCase->status] ?? $debtCase->status;
            $to = DebtCase::statusLabels()[$incoming['status']] ?? $incoming['status'];
            $changes[] = "status {$from} → {$to}";
        }

        if ($debtCase->priority !== $incoming['priority']) {
            $changes[] = "priorytet {$debtCase->priority} → {$incoming['priority']}";
        }

        if ($debtCase->customer_segment !== $incoming['customer_segment']) {
            $from = DebtCase::segmentLabels()[$debtCase->customer_segment] ?? $debtCase->customer_segment;
            $to = DebtCase::segmentLabels()[$incoming['customer_segment']] ?? $incoming['customer_segment'];
            $changes[] = "segment {$from} → {$to}";
        }

        if ((bool) $debtCase->manual_vip !== $incoming['manual_vip']) {
            $changes[] = 'VIP ręcznie: '.($incoming['manual_vip'] ? 'włączono' : 'wyłączono');
        }

        if ((bool) $debtCase->do_not_auto_dun !== $incoming['do_not_auto_dun']) {
            $changes[] = 'bez auto monitu: '.($incoming['do_not_auto_dun'] ? 'włączono' : 'wyłączono');
        }

        if ((string) ($debtCase->vip_reason ?? '') !== (string) ($incoming['vip_reason'] ?? '')) {
            $changes[] = 'powód VIP zmieniony';
        }

        $oldNext = $debtCase->next_action_at?->timezone(config('app.timezone'))->format('Y-m-d H:i');
        $newNext = ! empty($incoming['next_action_at'])
            ? \Illuminate\Support\Carbon::parse($incoming['next_action_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i')
            : null;
        if ($oldNext !== $newNext) {
            $changes[] = 'następny kontakt: '.($oldNext ?: '—').' → '.($newNext ?: '—');
        }

        if ((string) ($debtCase->summary ?? '') !== (string) ($incoming['summary'] ?? '')) {
            $changes[] = 'podsumowanie zmienione';
        }

        return $changes;
    }

    /**
     * Live lookup danych pod ponaglenie po numerze faktury lub numerze KSeF.
     * Uwaga: dla faktur odroczonych status opłacenia jest weryfikowany w iFirma (poza systemem).
     */
    public function debtorsLookup(Request $request, DebtCustomerProfileService $profileService)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:128'],
            'match_mode' => ['nullable', 'string', 'in:exact,partial'],
        ]);

        $query = trim((string) $validated['q']);
        $matchMode = (string) ($validated['match_mode'] ?? 'partial');

        $matchesQuery = FormOrder::query()
            ->with(['primaryParticipant', 'onlinePaymentOrders', 'activeDebtCases'])
            ->where(function ($q) {
                $q->where(function ($invoice) {
                    $invoice->whereNotNull('invoice_number')
                        ->where('invoice_number', '!=', '')
                        ->where('invoice_number', '!=', '0');
                })->orWhere(function ($ksef) {
                    $ksef->whereNotNull('ksef_number')
                        ->where('ksef_number', '!=', '');
                });
            });

        if ($matchMode === 'exact') {
            $matchesQuery->where(function ($q) use ($query) {
                $q->where('invoice_number', $query)
                    ->orWhere('ksef_number', $query);
            });
        } else {
            $matchesQuery
                ->where(function ($q) use ($query) {
                    $q->where('invoice_number', 'LIKE', '%'.$query.'%')
                        ->orWhere('ksef_number', 'LIKE', '%'.$query.'%');
                })
                ->orderByRaw(
                    'CASE WHEN invoice_number = ? OR ksef_number = ? THEN 0 ELSE 1 END',
                    [$query, $query]
                );
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
                        'strategy' => 'none',
                        'strategy_label' => $profileService->strategyLabel('none'),
                        'recipient_nip' => null,
                        'buyer_nip' => null,
                        'orderer_email' => null,
                        'recipient_profile' => null,
                    ],
                    'sources' => [
                        'strategy' => 'none',
                        'related_orders' => 0,
                    ],
                ],
            ]);
        }

        $selected = $matches->first();
        $historyPayload = $this->buildDebtorHistoryPayload($selected, $profileService);

        return response()->json([
            'matches' => $matches->map(fn (FormOrder $order) => [
                'id' => $order->id,
                'invoice_number' => $order->invoice_number,
                'ksef_number' => $order->ksef_number,
                'order_date' => $order->formatOrderDateLocal('Y-m-d H:i'),
                'product_name' => $order->product_name,
                'buyer_name' => $order->buyer_name,
                'recipient_name' => $order->recipient_name,
                'active_debt_case' => $this->debtCaseSummaryForOrder($order),
            ])->values(),
            'selected' => [
                'id' => $selected->id,
                'invoice_number' => $selected->invoice_number,
                'ksef_number' => $selected->ksef_number,
                'order_date' => $selected->formatOrderDateLocal('Y-m-d H:i'),
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
                'active_debt_case' => $this->debtCaseSummaryForOrder($selected),
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

    /**
     * @return array{id: int, status: string, status_label: string, url: string}|null
     */
    private function debtCaseSummaryForOrder(FormOrder $order): ?array
    {
        $case = $order->relationLoaded('activeDebtCases')
            ? $order->activeDebtCases->sortByDesc('id')->first()
            : $order->activeDebtCases()->orderByDesc('id')->first();

        if ($case === null) {
            return null;
        }

        return [
            'id' => $case->id,
            'status' => $case->status,
            'status_label' => $case->statusLabel(),
            'url' => route('accounting.collections.show', $case),
        ];
    }

    private function buildDebtorHistoryPayload(FormOrder $selected, DebtCustomerProfileService $profileService): array
    {
        $identity = $profileService->identityForOrder($selected);
        $allOrders = $profileService->relatedOrders($identity);

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
                'strategy' => $identity['strategy'],
                'strategy_label' => $profileService->strategyLabel($identity['strategy']),
                'recipient_nip' => $identity['recipient_nip'],
                'buyer_nip' => $identity['buyer_nip'],
                'orderer_email' => $identity['orderer_email'],
                'recipient_profile' => $identity['recipient_profile'],
            ],
            'sources' => [
                'strategy' => $identity['strategy'],
                'related_orders' => $allOrders->count(),
            ],
            'stats' => $stats,
            'orders' => $allOrders->map(function (FormOrder $order) use ($identity, $profileService) {
                $linkReasons = $profileService->linkReasonsForRelatedOrder($order, $identity);

                return [
                    'id' => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'order_date' => $order->formatOrderDateLocal('Y-m-d H:i'),
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
}
