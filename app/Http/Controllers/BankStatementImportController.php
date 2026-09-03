<?php

namespace App\Http\Controllers;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\Bank\BankStatementCoverageService;
use App\Services\Bank\BankStatementImportService;
use App\Services\Bank\BankTransactionUnlinkService;
use App\Services\DebtCaseAutoCloseService;
use App\Services\IfirmaInvoicePaymentRegistrationService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BankStatementImportController extends Controller
{
    public function index(Request $request, BankStatementCoverageService $coverageService)
    {
        $search = trim($request->string('q')->toString());
        if (mb_strlen($search) > 128) {
            $search = mb_substr($search, 0, 128);
        }

        $imports = BankStatementImport::query()
            ->with('uploader')
            ->withCount([
                'transactions as pending_review_count' => function ($q) {
                    $q->pendingOperatorReview();
                },
            ])
            ->matchingSearch($search)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $matchingTransactions = null;
        if ($search !== '') {
            $matchingTransactions = BankTransaction::query()
                ->with([
                    'import',
                    'matches' => fn ($q) => $q->with(['formOrder', 'debtCase'])->latest('id'),
                ])
                ->where('is_incoming', true)
                ->matchingSearch($search)
                ->latest('operation_date')
                ->latest('id')
                ->paginate(25, ['*'], 'tx_page')
                ->withQueryString();
        }

        $coverageGaps = $coverageService->detectGaps();

        return view('accounting.bank-imports.index', compact(
            'imports',
            'coverageGaps',
            'search',
            'matchingTransactions'
        ));
    }

    public function store(
        Request $request,
        BankStatementImportService $importService,
        BankStatementCoverageService $coverageService
    ) {
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

        $redirect = redirect()
            ->route('accounting.bank-imports.show', $import)
            ->with('success', sprintf(
                'Zaimportowano: %d wierszy (%d wpływów, %d z sugestią, %d duplikatów pominiętych).',
                $import->rows_total,
                $import->rows_incoming,
                $import->rows_matched,
                $import->rows_duplicate
            ));

        $gaps = $coverageService->detectGaps();
        if ($gaps !== []) {
            $summary = $coverageService->formatGapsSummary($gaps);
            $redirect->with(
                'warning',
                'Wykryto lukę w okresach wyciągów (pole „Za okres”): '.$summary
                .'. Sprawdź, czy nie brakuje eksportu z mBank między tymi datami.'
            );
        }

        return $redirect;
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

    public function destroy(Request $request, BankStatementImport $bankImport, BankStatementImportService $importService)
    {
        try {
            $id = $bankImport->id;
            $importService->deleteImport($bankImport);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('accounting.bank-imports.index', array_filter([
                    'q' => trim((string) $request->input('q', '')) ?: null,
                ]))
                ->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('accounting.bank-imports.index', array_filter([
                'q' => trim((string) $request->input('q', '')) ?: null,
            ]))
            ->with('success', sprintf('Usunięto import #%d (bez zaakceptowanych powiązań).', $id));
    }

    public function show(Request $request, BankStatementImport $bankImport, BankStatementImportService $importService)
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

        $previewId = $request->integer('preview');
        $search = trim($request->string('q')->toString());
        if (mb_strlen($search) > 128) {
            $search = mb_substr($search, 0, 128);
        }

        if ($filter === 'unmatched') {
            $transactionsQuery->withRemainingAllocatable()
                ->where(function ($q) {
                    $q->whereDoesntHave('matches')
                        ->orWhereHas('matches', fn ($m) => $m->where('status', BankTransactionMatch::STATUS_SUGGESTED));
                });
        } elseif ($filter === 'unlinked') {
            $transactionsQuery->whereDoesntHave('matches', function ($q) {
                $q->whereIn('status', [
                    BankTransactionMatch::STATUS_SUGGESTED,
                    BankTransactionMatch::STATUS_ACCEPTED,
                    BankTransactionMatch::STATUS_IGNORED,
                    BankTransactionMatch::STATUS_DEFERRED,
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
        } elseif ($filter === 'deferred') {
            $transactionsQuery->whereHas('matches', function ($q) {
                $q->where('status', BankTransactionMatch::STATUS_DEFERRED);
            });
        } elseif ($filter === 'paynow') {
            $transactionsQuery->whereHas('matches', function ($q) {
                $q->where('status', BankTransactionMatch::STATUS_IGNORED)
                    ->whereJsonContains('match_reasons', BankTransactionMatch::REASON_GATEWAY_PAYOUT_PAYNOW);
            });
        } elseif ($filter === 'ignored') {
            $transactionsQuery->whereHas('matches', function ($q) {
                $q->where('status', BankTransactionMatch::STATUS_IGNORED)
                    ->where(function ($reasons) {
                        $reasons->whereNull('match_reasons')
                            ->orWhereJsonDoesntContain('match_reasons', BankTransactionMatch::REASON_GATEWAY_PAYOUT_PAYNOW);
                    });
            });
        }

        $transactionsQuery->matchingSearch($search);

        // Deep-link ze sprawy: ?preview={txId} — ten przelew na początku listy, żeby modal się otworzył.
        if ($previewId > 0
            && $bankImport->transactions()->whereKey($previewId)->where('is_incoming', true)->exists()) {
            $transactionsQuery->reorder()
                ->orderByRaw('CASE WHEN bank_transactions.id = ? THEN 0 ELSE 1 END', [$previewId])
                ->orderByDesc('operation_date')
                ->orderByDesc('id');
        }

        $transactions = $transactionsQuery->paginate(50)->withQueryString();

        $counts = [
            'unmatched' => $bankImport->transactions()
                ->where('is_incoming', true)
                ->withRemainingAllocatable()
                ->where(function ($q) {
                    $q->whereDoesntHave('matches')
                        ->orWhereHas('matches', fn ($m) => $m->where('status', BankTransactionMatch::STATUS_SUGGESTED));
                })
                ->count(),
            'unlinked' => $bankImport->transactions()
                ->where('is_incoming', true)
                ->whereDoesntHave('matches', fn ($q) => $q->whereIn('status', [
                    BankTransactionMatch::STATUS_SUGGESTED,
                    BankTransactionMatch::STATUS_ACCEPTED,
                    BankTransactionMatch::STATUS_IGNORED,
                    BankTransactionMatch::STATUS_DEFERRED,
                ]))
                ->count(),
            'high' => $this->countByConfidence($bankImport, BankTransactionMatch::CONFIDENCE_HIGH),
            'medium' => $this->countByConfidence($bankImport, BankTransactionMatch::CONFIDENCE_MEDIUM),
            'low' => $this->countByConfidence($bankImport, BankTransactionMatch::CONFIDENCE_LOW),
            'accepted' => $bankImport->transactions()
                ->whereHas('matches', fn ($q) => $q->where('status', BankTransactionMatch::STATUS_ACCEPTED))
                ->count(),
            'deferred' => $bankImport->transactions()
                ->where('is_incoming', true)
                ->whereHas('matches', fn ($q) => $q->where('status', BankTransactionMatch::STATUS_DEFERRED))
                ->count(),
            'paynow' => $importService->countPayNowGatewayPayouts($bankImport),
            'ignored' => $bankImport->transactions()
                ->where('is_incoming', true)
                ->whereHas('matches', function ($q) {
                    $q->where('status', BankTransactionMatch::STATUS_IGNORED)
                        ->where(function ($reasons) {
                            $reasons->whereNull('match_reasons')
                                ->orWhereJsonDoesntContain('match_reasons', BankTransactionMatch::REASON_GATEWAY_PAYOUT_PAYNOW);
                        });
                })
                ->count(),
        ];

        $counts['pending_review'] = ($counts['unmatched'] ?? 0) + ($counts['deferred'] ?? 0);

        return view('accounting.bank-imports.show', [
            'import' => $bankImport->load('uploader'),
            'transactions' => $transactions,
            'filter' => $filter,
            'search' => $search,
            'counts' => $counts,
            'payNowIgnorableCount' => $importService->countIgnorablePayNowGatewayPayouts($bankImport),
        ]);
    }

    public function ignorePayNowGatewayPayouts(
        BankStatementImport $bankImport,
        BankStatementImportService $importService
    ) {
        $result = $importService->ignorePayNowGatewayPayouts($bankImport);

        if ($result['ignored'] === 0 && $result['candidates'] === 0) {
            return redirect()
                ->route('accounting.bank-imports.show', $bankImport)
                ->with('warning', 'Nie znaleziono wypłat rozliczeniowych PayNow (mElements / WYPŁATA ŚRODKÓW NR PON-…) do zignorowania.');
        }

        $parts = [
            sprintf('Zignorowano wypłat PayNow: %d.', $result['ignored']),
        ];
        if ($result['skipped_already_ignored'] > 0) {
            $parts[] = sprintf('Już ignorowane: %d.', $result['skipped_already_ignored']);
        }
        if ($result['skipped_accepted'] > 0) {
            $parts[] = sprintf('Pominięto zaakceptowane: %d.', $result['skipped_accepted']);
        }

        return redirect()
            ->route('accounting.bank-imports.show', ['bankImport' => $bankImport, 'filter' => 'paynow'])
            ->with('success', implode(' ', $parts));
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
            return $this->redirectAfterReviewModalAlert(
                $request,
                $bankImport,
                $e->getMessage(),
                (int) $match->bank_transaction_id
            );
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

    public function acceptPackage(
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

        try {
            $accepted = $importService->acceptSuggestedSplitPackage(
                $transaction,
                $request->user()?->id
            );
        } catch (\InvalidArgumentException $e) {
            return $this->redirectAfterReviewModalAlert(
                $request,
                $bankImport,
                $e->getMessage(),
                (int) $transaction->id
            );
        }

        $caseIds = collect($accepted)
            ->pluck('debt_case_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $message = sprintf(
            'Zaakceptowano pakiet podziału: %d powiązań%s.',
            count($accepted),
            $caseIds !== [] ? ' (sprawy: #'.implode(', #', $caseIds).')' : ''
        );

        if (! $request->boolean('register_ifirma_payment')) {
            return $this->redirectAfterReview(
                $request,
                $bankImport,
                $message.' Status iFirma nie został zmieniony (akceptacja tylko lokalna).'
            );
        }

        $ifirmaOk = 0;
        $ifirmaFail = [];
        foreach ($accepted as $match) {
            try {
                $paymentResult = $paymentRegistration->registerFromAcceptedBankMatch(
                    $match,
                    $request->user()
                );
            } catch (\Throwable $e) {
                report($e);
                $ifirmaFail[] = sprintf(
                    'sprawa #%s: %s',
                    $match->debt_case_id ?: '—',
                    $e->getMessage()
                );

                continue;
            }

            if ($paymentResult['success'] ?? false) {
                $ifirmaOk++;
                $message .= $this->appendAutoCloseMessage(
                    $autoClose,
                    $match,
                    $request->user(),
                    $paymentResult['status'] ?? null
                );
            } else {
                $ifirmaFail[] = sprintf(
                    'sprawa #%s: %s',
                    $match->debt_case_id ?: '—',
                    $paymentResult['message'] ?? 'nieznany błąd'
                );
            }
        }

        if ($ifirmaOk > 0) {
            $message .= sprintf(' Zarejestrowano wpłaty w iFirma: %d/%d.', $ifirmaOk, count($accepted));
        }

        $redirect = $this->redirectAfterReview($request, $bankImport, $message);

        if ($ifirmaFail !== []) {
            $redirect->with(
                'warning',
                'Część wpłat w iFirma nie przeszła (akceptacja lokalna OK): '.implode(' | ', $ifirmaFail)
            );
        } elseif ($ifirmaOk === 0) {
            $redirect->with(
                'warning',
                'Akceptacja lokalna OK, ale żadna wpłata w iFirma nie została zarejestrowana.'
            );
        }

        return $redirect;
    }

    public function registerIfirmaPayment(
        Request $request,
        BankStatementImport $bankImport,
        BankTransactionMatch $match,
        IfirmaInvoicePaymentRegistrationService $paymentRegistration,
        DebtCaseAutoCloseService $autoClose
    ) {
        $this->assertMatchBelongsToImport($bankImport, $match);

        $match = $match->fresh(['debtCase', 'transaction']) ?? $match;
        if ($match->status !== BankTransactionMatch::STATUS_ACCEPTED) {
            return $this->redirectAfterReview(
                $request,
                $bankImport,
                'Wpłatę w iFirma można rejestrować tylko dla zaakceptowanego dopasowania.'
            )->with('warning', 'Najpierw zaakceptuj dopasowanie lokalnie.');
        }

        try {
            $paymentResult = $paymentRegistration->registerFromAcceptedBankMatch(
                $match,
                $request->user()
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->redirectAfterReview(
                $request,
                $bankImport,
                'Dopasowanie jest zaakceptowane lokalnie.'
            )->with('warning', 'Wpłata w iFirma nie przeszła: '.$e->getMessage());
        }

        if ($paymentResult['success']) {
            $message = $paymentResult['message'];
            $message .= $this->appendAutoCloseMessage(
                $autoClose,
                $match,
                $request->user(),
                $paymentResult['status'] ?? null
            );

            return $this->redirectAfterReview($request, $bankImport, $message);
        }

        return $this->redirectAfterReview(
            $request,
            $bankImport,
            'Dopasowanie jest zaakceptowane lokalnie.'
        )->with('warning', 'Wpłata w iFirma nie przeszła: '.$paymentResult['message']);
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
                'issue_date' => $snapshot['issue_date'] ?? null,
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

        return $this->ifirmaStatusJsonResponse(
            $result,
            $order,
            $statusService,
            canAcceptAsPaid: ($result['status'] ?? null) === IfirmaInvoicePaymentStatusService::STATUS_PAID
                && $match->status === BankTransactionMatch::STATUS_SUGGESTED,
        );
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

    public function unlink(
        Request $request,
        BankStatementImport $bankImport,
        BankTransactionMatch $match,
        BankTransactionUnlinkService $unlinkService
    ) {
        $this->assertMatchBelongsToImport($bankImport, $match);

        try {
            $result = $unlinkService->unlinkAcceptedMatch($match, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->redirectAfterReview($request, $bankImport, $e->getMessage())
                ->with('warning', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->redirectAfterReview(
                $request,
                $bankImport,
                'Nie udało się cofnąć przypisania: '.$e->getMessage()
            )->with('warning', $e->getMessage());
        }

        $redirect = $this->redirectAfterReview($request, $bankImport, $result['message']);
        if (! empty($result['warning'])) {
            $redirect->with('warning', $result['warning']);
        }

        return $redirect;
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

    public function deferTransaction(
        Request $request,
        BankStatementImport $bankImport,
        BankTransaction $transaction,
        BankStatementImportService $importService
    ) {
        if ((int) $transaction->bank_statement_import_id !== (int) $bankImport->id) {
            abort(404);
        }

        try {
            $importService->deferTransaction($transaction);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('accounting.bank-imports.show', $this->reviewRedirectParams($request, $bankImport))
                ->with('warning', $e->getMessage());
        }

        return $this->redirectAfterReview($request, $bankImport, 'Przelew oznaczony jako „Na potem”.');
    }

    public function undeferTransaction(
        Request $request,
        BankStatementImport $bankImport,
        BankTransaction $transaction,
        BankStatementImportService $importService
    ) {
        if ((int) $transaction->bank_statement_import_id !== (int) $bankImport->id) {
            abort(404);
        }

        $importService->undeferTransaction($transaction);

        return $this->redirectAfterReview($request, $bankImport, 'Przelew przywrócony do przeglądu.');
    }

    public function lookupDebtCases(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:128'],
            'exact' => ['nullable', 'boolean'],
        ]);

        $query = trim($validated['q']);
        $exact = $request->boolean('exact', false);
        if ($query === '') {
            throw ValidationException::withMessages([
                'q' => 'Podaj frazę wyszukiwania.',
            ]);
        }
        if (! ctype_digit($query) && mb_strlen($query) < 2) {
            throw ValidationException::withMessages([
                'q' => 'Wpisz co najmniej 2 znaki (albo samo ID zamówienia/sprawy).',
            ]);
        }

        $digits = preg_replace('/\D+/', '', $query) ?: '';

        $applyOrderSearch = function ($formOrderQuery) use ($query, $digits, $exact) {
            $formOrderQuery->where(function ($inner) use ($query, $digits, $exact) {
                if ($exact) {
                    $inner->where('invoice_number', $query)
                        ->orWhere('ksef_number', $query);
                } else {
                    $inner->where('invoice_number', 'like', "%{$query}%")
                        ->orWhere('ksef_number', 'like', "%{$query}%");
                }

                $inner->orWhere('buyer_name', 'like', "%{$query}%")
                    ->orWhere('recipient_name', 'like', "%{$query}%")
                    ->orWhere('orderer_name', 'like', "%{$query}%")
                    ->orWhere('orderer_email', 'like', "%{$query}%")
                    ->orWhere('product_name', 'like', "%{$query}%")
                    ->orWhere('buyer_address', 'like', "%{$query}%")
                    ->orWhere('buyer_city', 'like', "%{$query}%")
                    ->orWhere('buyer_postal_code', 'like', "%{$query}%")
                    ->orWhere('recipient_address', 'like', "%{$query}%")
                    ->orWhere('recipient_city', 'like', "%{$query}%")
                    ->orWhere('recipient_postal_code', 'like', "%{$query}%");

                if ($exact) {
                    $inner->orWhere('buyer_nip', $query)
                        ->orWhere('recipient_nip', $query);
                } else {
                    $inner->orWhere('buyer_nip', 'like', "%{$query}%")
                        ->orWhere('recipient_nip', 'like', "%{$query}%");
                }

                if (ctype_digit($query)) {
                    $inner->orWhere('id', (int) $query);
                }

                if (strlen($digits) >= 7) {
                    if ($exact) {
                        $inner->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(COALESCE(buyer_nip,''), '-', ''), ' ', ''), 'PL', '') = ?",
                            [$digits]
                        )->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(COALESCE(recipient_nip,''), '-', ''), ' ', ''), 'PL', '') = ?",
                            [$digits]
                        );
                    } else {
                        $inner->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(COALESCE(buyer_nip,''), '-', ''), ' ', ''), 'PL', '') LIKE ?",
                            ['%'.$digits.'%']
                        )->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(COALESCE(recipient_nip,''), '-', ''), ' ', ''), 'PL', '') LIKE ?",
                            ['%'.$digits.'%']
                        );
                    }
                }

                $titleExtractor = new \App\Services\Bank\PaymentTitleExtractor;
                if ($exact && $titleExtractor->looksLikeInvoiceNumber($query)) {
                    $notesPattern = $titleExtractor->invoiceNumberSqlBoundaryPattern($query);
                    $inner->orWhereRaw('notes REGEXP ?', [$notesPattern])
                        ->orWhereRaw('invoice_notes REGEXP ?', [$notesPattern]);
                } else {
                    $inner->orWhere('notes', 'like', "%{$query}%")
                        ->orWhere('invoice_notes', 'like', "%{$query}%");
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
            ->where(function ($outer) use ($query, $applyOrderSearch, $exact) {
                if ($exact) {
                    $outer->where('invoice_number', $query)
                        ->orWhere('ksef_number', $query);
                } else {
                    $outer->where('invoice_number', 'like', "%{$query}%")
                        ->orWhere('ksef_number', 'like', "%{$query}%");
                }

                $outer->orWhereHas('formOrder', $applyOrderSearch);

                if (ctype_digit($query)) {
                    $outer->orWhere('id', (int) $query)
                        ->orWhere('form_order_id', (int) $query);
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

    public function lookupOrderPreview(Request $request)
    {
        $validated = $request->validate([
            'form_order_id' => ['required', 'integer', 'exists:form_orders,id'],
        ]);

        $order = FormOrder::query()
            ->with(['course.instructor'])
            ->findOrFail($validated['form_order_id']);

        return response()->json([
            'order' => $this->formatOrderPreviewPayload($order),
        ]);
    }

    /**
     * Status płatności iFirma dla kandydata z ręcznego wyszukiwania (bez istniejącego matcha).
     */
    public function lookupIfirmaStatus(
        Request $request,
        IfirmaInvoicePaymentStatusService $statusService
    ) {
        $validated = $request->validate([
            'form_order_id' => ['nullable', 'integer', 'exists:form_orders,id'],
            'debt_case_id' => ['nullable', 'integer', 'exists:debt_cases,id'],
        ]);

        if (empty($validated['form_order_id']) && empty($validated['debt_case_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Podaj zamówienie albo sprawę, aby sprawdzić status w iFirma.',
            ], 422);
        }

        $debtCase = null;
        if (! empty($validated['debt_case_id'])) {
            $debtCase = DebtCase::query()
                ->with('formOrder')
                ->findOrFail($validated['debt_case_id']);
        }

        $order = $debtCase?->formOrder;
        if ($order === null && ! empty($validated['form_order_id'])) {
            $order = FormOrder::query()->findOrFail($validated['form_order_id']);
        }

        if ($order === null) {
            return response()->json([
                'success' => false,
                'message' => 'Brak powiązanego zamówienia — nie można sprawdzić statusu w iFirma.',
            ], 422);
        }

        if ($debtCase !== null) {
            $result = $statusService->syncDebtCase($debtCase, $request->user());
        } else {
            $snapshot = $statusService->fetchPaymentSnapshotForOrder(
                $order,
                $order->invoice_number ?: null,
                $order->order_date
            );
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
                'issue_date' => $snapshot['issue_date'] ?? null,
                'due_date' => $snapshot['due_date'] ?? null,
                'source' => $snapshot['source'] ?? null,
            ];
        }

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Nie udało się pobrać statusu z iFirma.',
            ], 422);
        }

        // Brak sugestii match — akceptacja „już opłacone” idzie przez ręczne powiązanie lokalne.
        return $this->ifirmaStatusJsonResponse($result, $order, $statusService, canAcceptAsPaid: false);
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
            return $this->redirectAfterReviewModalAlert(
                $request,
                $bankImport,
                'Wybierz sprawę albo zamówienie do powiązania.',
                (int) $transaction->id
            );
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
            return $this->redirectAfterReviewModalAlert(
                $request,
                $bankImport,
                $e->getMessage(),
                (int) $transaction->id
            );
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
        return redirect()
            ->route('accounting.bank-imports.show', $this->reviewRedirectParams($request, $import))
            ->with('success', $message);
    }

    /**
     * Błąd / ostrzeżenie przy pracy w modalu: wróć do tego samego przelewu i pokaż komunikat w oknie podglądu.
     */
    private function redirectAfterReviewModalAlert(
        Request $request,
        BankStatementImport $import,
        string $message,
        int $previewTransactionId,
        string $type = 'danger'
    ) {
        $params = $this->reviewRedirectParams($request, $import);
        $params['preview'] = $previewTransactionId;

        return redirect()
            ->route('accounting.bank-imports.show', $params)
            ->with('modal_alert', [
                'type' => in_array($type, ['danger', 'warning', 'success', 'info'], true) ? $type : 'danger',
                'message' => $message,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewRedirectParams(Request $request, BankStatementImport $import): array
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
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $params['q'] = mb_substr($search, 0, 128);
        }

        return $params;
    }

    private function assertMatchBelongsToImport(BankStatementImport $import, BankTransactionMatch $match): void
    {
        $match->loadMissing('transaction');
        if (! $match->transaction || (int) $match->transaction->bank_statement_import_id !== (int) $import->id) {
            abort(404);
        }
    }

    /**
     * Payload prawej kolumny „Zamówienie / sugestia” w modalu importu wyciągu.
     *
     * @return array<string, mixed>
     */
    private function formatOrderPreviewPayload(FormOrder $order): array
    {
        $order->loadMissing(['course.instructor']);

        $course = $order->course;
        $courseTitle = $course
            ? $course->plainTitle((string) ($order->product_name ?: 'Szkolenie'))
            : (string) ($order->product_name ?: '—');
        $courseDate = $course?->start_date
            ? $course->start_date->timezone(config('app.timezone'))->format('d.m.Y H:i')
            : null;
        $courseInstructor = trim(
            ($course?->instructor?->first_name ?? '').' '.($course?->instructor?->last_name ?? '')
        );
        $productLabel = $courseTitle;
        if ($courseDate) {
            $productLabel .= ' ('.$courseDate.')';
        }
        if ($courseInstructor !== '') {
            $productLabel .= ' — '.$courseInstructor;
        }

        return [
            'id' => $order->id,
            'url' => route('form-orders.show', $order->id),
            'invoice' => $order->invoice_number ?: '—',
            'invoice_issue_date' => $order->invoice_issue_date?->format('Y-m-d'),
            'ksef' => $order->ksef_number ?: '—',
            'amount' => $order->product_price !== null
                ? number_format((float) $order->product_price, 2, ',', ' ').' PLN'
                : '—',
            'product' => $productLabel !== '' ? $productLabel : '—',
            'course_id' => $course?->id,
            'course_url' => $course?->id ? route('courses.show', $course->id) : null,
            'buyer_name' => $order->buyer_name ?: '—',
            'buyer_nip' => $order->buyer_nip ?: 'brak NIP',
            'buyer_postal_code' => trim((string) ($order->buyer_postal_code ?? '')),
            'buyer_city' => trim((string) ($order->buyer_city ?? '')),
            'buyer_address' => trim(implode(', ', array_filter([
                $order->buyer_address,
                trim(($order->buyer_postal_code ?? '').' '.($order->buyer_city ?? '')),
            ]))) ?: '—',
            'recipient_name' => $order->recipient_name ?: '—',
            'recipient_nip' => $order->recipient_nip ?: 'brak NIP',
            'recipient_postal_code' => trim((string) ($order->recipient_postal_code ?? '')),
            'recipient_city' => trim((string) ($order->recipient_city ?? '')),
            'recipient_address' => trim(implode(', ', array_filter([
                $order->recipient_address,
                trim(($order->recipient_postal_code ?? '').' '.($order->recipient_city ?? '')),
            ]))) ?: '—',
            'participant_name' => trim((string) ($order->display_participant_name ?? '')) ?: '—',
            'participant_email' => trim((string) ($order->display_participant_email ?? '')) ?: '',
            'orderer_name' => trim((string) ($order->orderer_name ?? '')) ?: '—',
            'orderer_email' => trim((string) ($order->orderer_email ?? '')) ?: '',
            'order_date' => $order->order_date
                ? Carbon::parse($order->order_date)->format('Y-m-d')
                : '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function ifirmaStatusJsonResponse(
        array $result,
        FormOrder $order,
        IfirmaInvoicePaymentStatusService $statusService,
        bool $canAcceptAsPaid,
    ): JsonResponse {
        $status = $result['status'] ?? null;
        $issueDate = $result['issue_date'] ?? $order->invoice_issue_date?->format('Y-m-d');
        $dueDate = $result['due_date'] ?? $order->invoice_due_date?->format('Y-m-d');

        $daysOverdue = null;
        if ($status !== IfirmaInvoicePaymentStatusService::STATUS_PAID && is_string($dueDate) && $dueDate !== '') {
            try {
                $due = Carbon::parse($dueDate)->startOfDay();
                $today = Carbon::today();
                if ($due->lt($today)) {
                    $daysOverdue = (int) $due->diffInDays($today);
                }
            } catch (\Throwable) {
                $daysOverdue = null;
            }
        }

        $debtCase = DebtCase::query()
            ->where('form_order_id', $order->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Pobrano status płatności z iFirma.',
            'status' => $status,
            'status_label' => $result['status_label'] ?? $statusService->statusLabel($status),
            'paid_amount' => $result['paid_amount'] ?? null,
            'gross_amount' => $result['gross_amount'] ?? null,
            'invoice_id' => $result['invoice_id'] ?? null,
            'invoice_number' => $result['invoice_number'] ?? null,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'days_overdue' => $daysOverdue,
            'source' => $result['source'] ?? null,
            'debt_case' => $debtCase ? [
                'id' => $debtCase->id,
                'url' => route('accounting.collections.show', $debtCase),
                'status' => $debtCase->status,
                'status_label' => $debtCase->statusLabel(),
            ] : null,
            'can_accept_as_paid' => $canAcceptAsPaid,
        ]);
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
