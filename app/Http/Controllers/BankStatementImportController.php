<?php

namespace App\Http\Controllers;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\Bank\BankStatementImportService;
use App\Services\DebtCaseAutoCloseService;
use App\Services\IfirmaInvoicePaymentRegistrationService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BankStatementImportController extends Controller
{
    public function index()
    {
        $imports = BankStatementImport::query()
            ->with('uploader')
            ->latest('id')
            ->paginate(20);

        return view('accounting.bank-imports.index', compact('imports'));
    }

    public function store(Request $request, BankStatementImportService $importService)
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ], [
            'csv_file.required' => 'Wybierz plik CSV wyciągu mBank.',
            'csv_file.mimes' => 'Dozwolony jest tylko plik CSV / TXT.',
            'csv_file.max' => 'Plik jest zbyt duży (max 50 MB).',
        ]);

        try {
            $import = $importService->importUploadedFile($validated['csv_file']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['csv_file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                'csv_file' => 'Nie udało się sparsować pliku. Upewnij się, że to wyciąg mBank CSV. Szczegóły: '.$e->getMessage(),
            ]);
        }

        return redirect()
            ->route('accounting.bank-imports.show', $import)
            ->with('success', sprintf(
                'Zaimportowano: %d wierszy (%d wpływów, %d z sugestią, %d duplikatów pominiętych).',
                $import->rows_total,
                $import->rows_incoming,
                $import->rows_matched,
                $import->rows_duplicate
            ));
    }

    public function rematch(BankStatementImport $bankImport, BankStatementImportService $importService)
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        $result = $importService->rematchImport($bankImport);

        return redirect()
            ->route('accounting.bank-imports.show', $bankImport)
            ->with('success', sprintf(
                'Przeliczono sugestie: przejrzano %d transakcji, %d ma co najmniej jedną sugestię (zaakceptowane bez zmian).',
                $result['reviewed'],
                $result['with_suggestions']
            ));
    }

    public function show(Request $request, BankStatementImport $bankImport)
    {
        $filter = $request->string('filter')->toString() ?: 'unmatched';

        $transactionsQuery = $bankImport->transactions()
            ->with([
                'matches' => fn ($q) => $q->with([
                    'formOrder.course.instructor',
                    'formOrder.primaryParticipant.participant',
                    'debtCase',
                ])->latest('id'),
            ])
            ->where('is_incoming', true)
            ->latest('operation_date')
            ->latest('id');

        if ($filter === 'unmatched') {
            $transactionsQuery->whereDoesntHave('matches', function ($q) {
                $q->whereIn('status', [
                    BankTransactionMatch::STATUS_ACCEPTED,
                    BankTransactionMatch::STATUS_IGNORED,
                ]);
            })->where(function ($q) {
                $q->whereDoesntHave('matches')
                    ->orWhereHas('matches', fn ($m) => $m->where('status', BankTransactionMatch::STATUS_SUGGESTED));
            });
        } elseif ($filter === 'unlinked') {
            $transactionsQuery->whereDoesntHave('matches', function ($q) {
                $q->whereIn('status', [
                    BankTransactionMatch::STATUS_SUGGESTED,
                    BankTransactionMatch::STATUS_ACCEPTED,
                    BankTransactionMatch::STATUS_IGNORED,
                ]);
            });
        } elseif (in_array($filter, ['high', 'medium', 'low'], true)) {
            $transactionsQuery->whereHas('matches', function ($q) use ($filter) {
                $q->where('status', BankTransactionMatch::STATUS_SUGGESTED)
                    ->where('confidence', $filter);
            });
        } elseif ($filter === 'accepted') {
            $transactionsQuery->whereHas('matches', function ($q) {
                $q->where('status', BankTransactionMatch::STATUS_ACCEPTED);
            });
        } elseif ($filter === 'ignored') {
            $transactionsQuery->whereHas('matches', function ($q) {
                $q->where('status', BankTransactionMatch::STATUS_IGNORED);
            });
        }

        $transactions = $transactionsQuery->paginate(50)->withQueryString();

        $counts = [
            'unmatched' => $bankImport->transactions()
                ->where('is_incoming', true)
                ->whereDoesntHave('matches', fn ($q) => $q->whereIn('status', [
                    BankTransactionMatch::STATUS_ACCEPTED,
                    BankTransactionMatch::STATUS_IGNORED,
                ]))
                ->count(),
            'unlinked' => $bankImport->transactions()
                ->where('is_incoming', true)
                ->whereDoesntHave('matches', fn ($q) => $q->whereIn('status', [
                    BankTransactionMatch::STATUS_SUGGESTED,
                    BankTransactionMatch::STATUS_ACCEPTED,
                    BankTransactionMatch::STATUS_IGNORED,
                ]))
                ->count(),
            'high' => $this->countByConfidence($bankImport, BankTransactionMatch::CONFIDENCE_HIGH),
            'medium' => $this->countByConfidence($bankImport, BankTransactionMatch::CONFIDENCE_MEDIUM),
            'low' => $this->countByConfidence($bankImport, BankTransactionMatch::CONFIDENCE_LOW),
            'accepted' => $bankImport->transactions()
                ->whereHas('matches', fn ($q) => $q->where('status', BankTransactionMatch::STATUS_ACCEPTED))
                ->count(),
            'ignored' => $bankImport->transactions()
                ->whereHas('matches', fn ($q) => $q->where('status', BankTransactionMatch::STATUS_IGNORED))
                ->count(),
        ];

        return view('accounting.bank-imports.show', [
            'import' => $bankImport->load('uploader'),
            'transactions' => $transactions,
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    public function accept(
        Request $request,
        BankStatementImport $bankImport,
        BankTransactionMatch $match,
        BankStatementImportService $importService,
        IfirmaInvoicePaymentRegistrationService $paymentRegistration,
        DebtCaseAutoCloseService $autoClose
    ) {
        $this->assertMatchBelongsToImport($bankImport, $match);

        try {
            $accepted = $importService->acceptMatch(
                $match,
                null,
                $request->boolean('ifirma_already_paid')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }

        $message = sprintf(
            'Zaakceptowano dopasowanie — sprawa #%d.',
            $accepted->debt_case_id
        );

        if ($request->boolean('register_ifirma_payment')) {
            try {
                $paymentResult = $paymentRegistration->registerFromAcceptedBankMatch(
                    $accepted,
                    $request->user()
                );
            } catch (\Throwable $e) {
                report($e);

                return $this->redirectAfterReview(
                    $request,
                    $bankImport,
                    $message.' Akceptacja lokalna OK.'
                )->with('warning', 'Wpłata w iFirma nie przeszła: '.$e->getMessage());
            }

            if ($paymentResult['success']) {
                $message .= ' '.$paymentResult['message'];
                $message .= $this->appendAutoCloseMessage(
                    $autoClose,
                    $accepted,
                    $request->user(),
                    $paymentResult['status'] ?? null
                );

                return $this->redirectAfterReview($request, $bankImport, $message);
            }

            return $this->redirectAfterReview(
                $request,
                $bankImport,
                $message.' Akceptacja lokalna OK.'
            )->with('warning', 'Wpłata w iFirma nie przeszła: '.$paymentResult['message']);
        }

        if ($request->boolean('ifirma_already_paid')) {
            $message .= ' iFirma wskazywała fakturę jako opłaconą — powiązano tylko lokalnie, bez rejestracji nowej wpłaty.';
            $message .= $this->appendAutoCloseMessage(
                $autoClose,
                $accepted,
                $request->user(),
                IfirmaInvoicePaymentStatusService::STATUS_PAID
            );

            return $this->redirectAfterReview($request, $bankImport, $message);
        }

        return $this->redirectAfterReview(
            $request,
            $bankImport,
            $message.' Status iFirma nie został zmieniony (akceptacja tylko lokalna).'
        );
    }

    public function ifirmaStatus(
        Request $request,
        BankStatementImport $bankImport,
        BankTransactionMatch $match,
        IfirmaInvoicePaymentStatusService $statusService
    ) {
        $this->assertMatchBelongsToImport($bankImport, $match);

        $match->loadMissing(['formOrder', 'debtCase.formOrder']);
        $order = $match->formOrder ?: $match->debtCase?->formOrder;

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Sugestia nie ma powiązanego zamówienia — nie można sprawdzić statusu w iFirma.',
            ], 422);
        }

        if ($match->debtCase) {
            $result = $statusService->syncDebtCase($match->debtCase, $request->user());
        } else {
            $snapshot = $statusService->fetchPaymentSnapshotForOrder($order, $order->invoice_number ?: null, $order->order_date);
            if (! ($snapshot['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $snapshot['message'] ?? 'Nie udało się pobrać statusu z iFirma.',
                ], 422);
            }

            $result = [
                'success' => true,
                'message' => 'Pobrano status płatności z iFirma.',
                'status' => $snapshot['status'],
                'status_label' => $statusService->statusLabel((string) $snapshot['status']),
                'paid_amount' => $snapshot['paid_amount'],
                'gross_amount' => $snapshot['gross_amount'],
                'invoice_id' => $snapshot['invoice_id'] ?? null,
                'invoice_number' => $snapshot['invoice_number'] ?? null,
                'due_date' => $snapshot['due_date'] ?? null,
                'source' => $snapshot['source'] ?? null,
                'changed' => null,
            ];
        }

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Nie udało się pobrać statusu z iFirma.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'status' => $result['status'] ?? null,
            'status_label' => $result['status_label'] ?? $statusService->statusLabel($result['status'] ?? null),
            'paid_amount' => $result['paid_amount'] ?? null,
            'gross_amount' => $result['gross_amount'] ?? null,
            'invoice_id' => $result['invoice_id'] ?? null,
            'invoice_number' => $result['invoice_number'] ?? null,
            'due_date' => $result['due_date'] ?? null,
            'source' => $result['source'] ?? null,
            'can_accept_as_paid' => ($result['status'] ?? null) === IfirmaInvoicePaymentStatusService::STATUS_PAID
                && $match->status === BankTransactionMatch::STATUS_SUGGESTED,
        ]);
    }

    public function reject(
        Request $request,
        BankStatementImport $bankImport,
        BankTransactionMatch $match,
        BankStatementImportService $importService
    ) {
        $this->assertMatchBelongsToImport($bankImport, $match);
        $importService->rejectMatch($match);

        return $this->redirectAfterReview($request, $bankImport, 'Odrzucono sugestię dopasowania.');
    }

    public function ignoreMatch(
        Request $request,
        BankStatementImport $bankImport,
        BankTransactionMatch $match,
        BankStatementImportService $importService
    ) {
        $this->assertMatchBelongsToImport($bankImport, $match);
        $importService->ignoreMatch($match);

        return $this->redirectAfterReview($request, $bankImport, 'Oznaczono sugestię jako ignorowaną.');
    }

    public function ignoreTransaction(
        Request $request,
        BankStatementImport $bankImport,
        BankTransaction $transaction,
        BankStatementImportService $importService
    ) {
        if ((int) $transaction->bank_statement_import_id !== (int) $bankImport->id) {
            abort(404);
        }

        $importService->ignoreTransaction($transaction);

        return $this->redirectAfterReview($request, $bankImport, 'Transakcja oznaczona jako ignorowana.');
    }

    public function lookupDebtCases(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:128'],
        ]);

        $query = trim($validated['q']);
        $digits = preg_replace('/\D+/', '', $query) ?: '';

        $applyOrderSearch = function ($formOrderQuery) use ($query, $digits) {
            $formOrderQuery->where(function ($inner) use ($query, $digits) {
                $inner->where('invoice_number', 'like', "%{$query}%")
                    ->orWhere('ksef_number', 'like', "%{$query}%")
                    ->orWhere('buyer_name', 'like', "%{$query}%")
                    ->orWhere('recipient_name', 'like', "%{$query}%")
                    ->orWhere('orderer_name', 'like', "%{$query}%")
                    ->orWhere('orderer_email', 'like', "%{$query}%")
                    ->orWhere('buyer_nip', 'like', "%{$query}%")
                    ->orWhere('recipient_nip', 'like', "%{$query}%")
                    ->orWhere('product_name', 'like', "%{$query}%");

                if (ctype_digit($query)) {
                    $inner->orWhereKey((int) $query);
                }

                if (strlen($digits) >= 7) {
                    $inner->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(COALESCE(buyer_nip,''), '-', ''), ' ', ''), 'PL', '') LIKE ?",
                        ['%'.$digits.'%']
                    )->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(COALESCE(recipient_nip,''), '-', ''), ' ', ''), 'PL', '') LIKE ?",
                        ['%'.$digits.'%']
                    );
                }
            });
        };

        $formatCourseDate = function (?FormOrder $order): ?string {
            $start = $order?->course?->start_date;
            if (! $start) {
                return null;
            }

            return \Illuminate\Support\Carbon::parse($start)
                ->timezone(config('app.timezone', 'Europe/Warsaw'))
                ->format('Y-m-d');
        };

        $cases = DebtCase::query()
            ->active()
            ->with(['formOrder.course'])
            ->where(function ($outer) use ($query, $applyOrderSearch) {
                $outer->where('invoice_number', 'like', "%{$query}%")
                    ->orWhere('ksef_number', 'like', "%{$query}%")
                    ->orWhereHas('formOrder', $applyOrderSearch);

                if (ctype_digit($query)) {
                    $outer->orWhereKey((int) $query);
                }
            })
            ->latest('id')
            ->limit(12)
            ->get();

        $caseOrderIds = $cases->pluck('form_order_id')->filter()->all();

        $orders = FormOrder::query()
            ->with(['course'])
            ->whereDoesntHave('activeDebtCases')
            ->where(function ($outer) use ($applyOrderSearch) {
                $applyOrderSearch($outer);
            })
            ->when($caseOrderIds !== [], fn ($q) => $q->whereNotIn('id', $caseOrderIds))
            ->latest('id')
            ->limit(12)
            ->get();

        return response()->json([
            'cases' => $cases->map(function (DebtCase $case) use ($formatCourseDate) {
                $order = $case->formOrder;
                $amount = (float) ($case->amount_gross ?? $order?->product_price ?? 0);

                return [
                    'type' => 'case',
                    'id' => $case->id,
                    'status' => $case->status,
                    'status_label' => $case->statusLabel(),
                    'invoice_number' => $case->invoice_number ?: ($order?->invoice_number ?: null),
                    'ksef_number' => $case->ksef_number ?: ($order?->ksef_number ?: null),
                    'amount_gross' => $amount,
                    'order_id' => $order?->id,
                    'order_date' => $order?->order_date
                        ? \Illuminate\Support\Carbon::parse($order->order_date)->format('Y-m-d')
                        : null,
                    'course_date' => $formatCourseDate($order),
                    'buyer_name' => $order?->buyer_name,
                    'recipient_name' => $order?->recipient_name,
                    'product_name' => $order?->product_name,
                    'url' => route('accounting.collections.show', $case),
                ];
            })->values(),
            'orders' => $orders->map(function (FormOrder $order) use ($formatCourseDate) {
                return [
                    'type' => 'order',
                    'id' => $order->id,
                    'invoice_number' => $order->invoice_number ?: null,
                    'ksef_number' => $order->ksef_number ?: null,
                    'amount_gross' => (float) ($order->product_price ?? 0),
                    'order_id' => $order->id,
                    'order_date' => $order->order_date
                        ? \Illuminate\Support\Carbon::parse($order->order_date)->format('Y-m-d')
                        : null,
                    'course_date' => $formatCourseDate($order),
                    'buyer_name' => $order->buyer_name,
                    'recipient_name' => $order->recipient_name,
                    'product_name' => $order->product_name,
                    'url' => route('form-orders.show', $order->id),
                ];
            })->values(),
        ]);
    }

    public function linkTransactionToCase(
        Request $request,
        BankStatementImport $bankImport,
        BankTransaction $transaction,
        BankStatementImportService $importService,
        IfirmaInvoicePaymentRegistrationService $paymentRegistration,
        DebtCaseAutoCloseService $autoClose
    ) {
        if ((int) $transaction->bank_statement_import_id !== (int) $bankImport->id) {
            abort(404);
        }

        $validated = $request->validate([
            'debt_case_id' => ['nullable', 'integer', 'exists:debt_cases,id'],
            'form_order_id' => ['nullable', 'integer', 'exists:form_orders,id'],
            'register_ifirma_payment' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['debt_case_id']) && empty($validated['form_order_id'])) {
            return back()->withErrors(['match' => 'Wybierz sprawę albo zamówienie do powiązania.']);
        }

        try {
            if (! empty($validated['debt_case_id'])) {
                $debtCase = DebtCase::query()->findOrFail($validated['debt_case_id']);
                $accepted = $importService->manuallyLinkTransactionToDebtCase(
                    $transaction,
                    $debtCase,
                    $request->user()?->id
                );
                $message = sprintf(
                    'Ręcznie powiązano przelew #%d ze sprawą #%d.',
                    $transaction->id,
                    $debtCase->id
                );
            } else {
                $order = FormOrder::query()->findOrFail($validated['form_order_id']);
                $accepted = $importService->manuallyLinkTransactionToFormOrder(
                    $transaction,
                    $order,
                    $request->user()?->id
                );
                $message = sprintf(
                    'Ręcznie powiązano przelew #%d z zamówieniem #%d (sprawa #%d).',
                    $transaction->id,
                    $order->id,
                    $accepted->debt_case_id
                );
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }

        if ($request->boolean('register_ifirma_payment')) {
            try {
                $paymentResult = $paymentRegistration->registerFromAcceptedBankMatch(
                    $accepted,
                    $request->user()
                );
            } catch (\Throwable $e) {
                report($e);

                return $this->redirectAfterReview(
                    $request,
                    $bankImport,
                    $message.' Akceptacja lokalna OK.'
                )->with('warning', 'Wpłata w iFirma nie przeszła: '.$e->getMessage());
            }

            if ($paymentResult['success']) {
                $message .= ' '.$paymentResult['message'];
                $message .= $this->appendAutoCloseMessage(
                    $autoClose,
                    $accepted,
                    $request->user(),
                    $paymentResult['status'] ?? null
                );

                return $this->redirectAfterReview($request, $bankImport, $message);
            }

            return $this->redirectAfterReview(
                $request,
                $bankImport,
                $message.' Akceptacja lokalna OK.'
            )->with('warning', 'Wpłata w iFirma nie przeszła: '.$paymentResult['message']);
        }

        return $this->redirectAfterReview(
            $request,
            $bankImport,
            $message.' Status iFirma nie został zmieniony (powiązanie tylko lokalne).'
        );
    }

    private function appendAutoCloseMessage(
        DebtCaseAutoCloseService $autoClose,
        BankTransactionMatch $accepted,
        ?User $user,
        ?string $ifirmaStatus
    ): string {
        $case = $accepted->debtCase ?: $accepted->fresh(['debtCase'])?->debtCase;
        if (! $case) {
            return '';
        }

        if (! $autoClose->closeIfFullyPaid($case, $user, $ifirmaStatus)) {
            return '';
        }

        return ' Sprawę zamknięto automatycznie.';
    }

    private function redirectAfterReview(Request $request, BankStatementImport $import, string $message)
    {
        $params = ['bankImport' => $import];
        $filter = $request->input('filter');
        if (is_string($filter) && $filter !== '') {
            $params['filter'] = $filter;
        }
        $preview = $request->input('preview');
        if ($preview !== null && $preview !== '') {
            $params['preview'] = (int) $preview;
        }

        return redirect()
            ->route('accounting.bank-imports.show', $params)
            ->with('success', $message);
    }

    private function assertMatchBelongsToImport(BankStatementImport $import, BankTransactionMatch $match): void
    {
        $match->loadMissing('transaction');
        if (! $match->transaction || (int) $match->transaction->bank_statement_import_id !== (int) $import->id) {
            abort(404);
        }
    }

    private function countByConfidence(BankStatementImport $import, string $confidence): int
    {
        return $import->transactions()
            ->where('is_incoming', true)
            ->whereHas('matches', function ($q) use ($confidence) {
                $q->where('status', BankTransactionMatch::STATUS_SUGGESTED)
                    ->where('confidence', $confidence);
            })
            ->count();
    }
}
