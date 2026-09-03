<?php

namespace App\Services\Bank;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\DebtCustomerProfileService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class BankStatementImportService
{
    private const INSERT_CHUNK = 250;

    private const MATCH_MAX_SUGGESTIONS = 3;

    public function __construct(
        private readonly MbankStatementParser $parser = new MbankStatementParser,
        private readonly BankTransactionMatcher $matcher = new BankTransactionMatcher,
        private readonly DebtCustomerProfileService $profileService = new DebtCustomerProfileService,
        private readonly PayNowGatewayPayoutDetector $payNowGatewayPayoutDetector = new PayNowGatewayPayoutDetector,
        private readonly ?IfirmaInvoicePaymentStatusService $ifirmaPaymentStatus = null,
    ) {}

    public function importUploadedFile(UploadedFile $file, ?int $userId = null): BankStatementImport
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');
        @ini_set('memory_limit', '512M');

        $contents = $file->get();
        if ($contents === false || $contents === '') {
            throw new InvalidArgumentException('Pusty plik CSV.');
        }

        $hash = hash('sha256', $contents);
        $parsed = $this->parser->parse($contents);

        $storedPath = $file->storeAs(
            'bank-statements/'.now()->format('Y/m'),
            now()->format('Ymd_His').'_'.$hash.'.csv',
            'local'
        );

        $this->matcher->warmLookupCaches();

        $existingFingerprints = BankTransaction::query()
            ->pluck('fingerprint')
            ->flip()
            ->all();

        $import = BankStatementImport::create([
            'uploaded_by' => $userId ?? Auth::id(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'file_hash' => $hash,
            'source' => BankStatementImport::SOURCE_MBANK,
            'status' => BankStatementImport::STATUS_PARSED,
            'period_from' => $parsed['period_from'],
            'period_to' => $parsed['period_to'],
            'rows_total' => count($parsed['rows']),
        ]);

        $incoming = 0;
        $matched = 0;
        $duplicates = 0;
        $now = now()->toDateTimeString();
        $pendingInserts = [];

        foreach ($parsed['rows'] as $row) {
            if (isset($existingFingerprints[$row['fingerprint']])) {
                $duplicates++;

                continue;
            }

            // Guard against duplicates within the same file
            $existingFingerprints[$row['fingerprint']] = true;

            $pendingInserts[] = [
                'bank_statement_import_id' => $import->id,
                'operation_date' => $row['operation_date'],
                'amount' => $row['amount'],
                'currency' => $row['currency'],
                'description' => $row['description'],
                'account_label' => $row['account_label'],
                'category' => $row['category'],
                'counterparty_account' => $row['counterparty_account'],
                'fingerprint' => $row['fingerprint'],
                'is_incoming' => $row['is_incoming'] ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($row['is_incoming']) {
                $incoming++;
            }

            if (count($pendingInserts) >= self::INSERT_CHUNK) {
                $matched += $this->flushTransactionChunk($pendingInserts);
                $pendingInserts = [];
            }
        }

        if ($pendingInserts !== []) {
            $matched += $this->flushTransactionChunk($pendingInserts);
        }

        // Samych duplikatów nie zostawiamy jako pusty rekord importu (jak #12 po ponownym wgraniu).
        if ($incoming === 0 && $duplicates > 0) {
            $previous = BankStatementImport::query()
                ->where('file_hash', $hash)
                ->where('id', '!=', $import->id)
                ->latest('id')
                ->first();

            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            $import->delete();

            throw new InvalidArgumentException(
                $previous
                    ? sprintf(
                        'Ten plik był już wgrany (import #%d). Wszystkie operacje są już w bazie — nie utworzono pustego importu.',
                        $previous->id
                    )
                    : 'Wszystkie operacje z pliku są już w bazie (duplikaty). Nie utworzono pustego importu.'
            );
        }

        $import->update([
            'rows_incoming' => $incoming,
            'rows_matched' => $matched,
            'rows_duplicate' => $duplicates,
        ]);

        return $import->fresh();
    }

    /**
     * Czy wolno usunąć import: brak zaakceptowanych powiązań na transakcjach tego importu.
     */
    public function canDeleteImport(BankStatementImport $import): bool
    {
        if ((int) $import->transactions()->count() === 0) {
            return true;
        }

        return ! $import->transactions()
            ->whereHas('matches', fn ($q) => $q->where('status', BankTransactionMatch::STATUS_ACCEPTED))
            ->exists();
    }

    /**
     * Usuwa rekord importu (+ kaskadowo transakcje/sugestie) oraz plik CSV, gdy nie ma accepted.
     */
    public function deleteImport(BankStatementImport $import): void
    {
        if (! $this->canDeleteImport($import)) {
            throw new InvalidArgumentException(
                'Nie można usunąć importu: są zaakceptowane powiązania przelewów ze sprawami. Najpierw cofnij przypisania.'
            );
        }

        $path = $import->stored_path;
        $import->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Przelicz sugestie dla wpływów bez zaakceptowanego dopasowania (np. po zmianie reguł matchera).
     *
     * @return array{reviewed: int, with_suggestions: int}
     */
    public function rematchImport(BankStatementImport $import): array
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');
        @ini_set('memory_limit', '512M');

        $this->matcher->warmLookupCaches();

        $reviewed = 0;
        $withSuggestions = 0;

        $import->transactions()
            ->where('is_incoming', true)
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use (&$reviewed, &$withSuggestions) {
                foreach ($transactions as $transaction) {
                    $transaction->loadMissing('matches');

                    if ($transaction->matches->contains(
                        fn (BankTransactionMatch $match) => in_array($match->status, [
                            BankTransactionMatch::STATUS_ACCEPTED,
                            BankTransactionMatch::STATUS_IGNORED,
                            BankTransactionMatch::STATUS_DEFERRED,
                        ], true)
                    )) {
                        continue;
                    }

                    $reviewed++;
                    $suggestions = $this->matcher->matchAndPersist($transaction);
                    if ($suggestions->isNotEmpty()) {
                        $withSuggestions++;
                    }
                }
            });

        $import->update([
            'rows_matched' => $import->transactions()
                ->where('is_incoming', true)
                ->whereHas('matches', fn ($q) => $q->where('status', BankTransactionMatch::STATUS_SUGGESTED))
                ->count(),
        ]);

        return [
            'reviewed' => $reviewed,
            'with_suggestions' => $withSuggestions,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return int Number of transactions that received at least one suggestion
     */
    private function flushTransactionChunk(array $rows): int
    {
        DB::table('bank_transactions')->insert($rows);

        $fingerprints = array_column($rows, 'fingerprint');
        $transactions = BankTransaction::query()
            ->whereIn('fingerprint', $fingerprints)
            ->get()
            ->keyBy('fingerprint');

        $matchRows = [];
        $matchedCount = 0;
        $now = now()->toDateTimeString();

        foreach ($rows as $row) {
            $transaction = $transactions->get($row['fingerprint']);
            if (! $transaction || ! $transaction->is_incoming) {
                continue;
            }

            $suggestions = array_slice(
                $this->matcher->suggest($transaction),
                0,
                self::MATCH_MAX_SUGGESTIONS
            );

            if ($suggestions === []) {
                continue;
            }

            $matchedCount++;

            foreach ($suggestions as $suggestion) {
                $matchRows[] = [
                    'bank_transaction_id' => $transaction->id,
                    'form_order_id' => $suggestion['form_order_id'],
                    'debt_case_id' => $suggestion['debt_case_id'],
                    'confidence' => $suggestion['confidence'],
                    'match_reasons' => json_encode($suggestion['match_reasons'], JSON_UNESCAPED_UNICODE),
                    'status' => BankTransactionMatch::STATUS_SUGGESTED,
                    'accepted_by' => null,
                    'accepted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($matchRows, self::INSERT_CHUNK) as $chunk) {
            DB::table('bank_transaction_matches')->insert($chunk);
        }

        return $matchedCount;
    }

    public function acceptMatch(
        BankTransactionMatch $match,
        ?int $userId = null,
        bool $acceptedViaIfirmaPaid = false
    ): BankTransactionMatch {
        if ($match->status === BankTransactionMatch::STATUS_ACCEPTED) {
            return $match;
        }

        $userId = $userId ?? Auth::id();

        return DB::transaction(function () use ($match, $userId, $acceptedViaIfirmaPaid) {
            $match->loadMissing(['transaction.matches', 'formOrder', 'debtCase']);

            $debtCase = $match->debtCase;
            $formOrder = $match->formOrder;

            if (! $debtCase && $formOrder) {
                // form_order_id jest UNIQUE — używaj istniejącej sprawy (także closed / soft-deleted),
                // zamiast tworzyć drugą i wywalać Integrity constraint → 500.
                $debtCase = DebtCase::withTrashed()
                    ->where('form_order_id', $formOrder->id)
                    ->latest('id')
                    ->first();

                if ($debtCase?->trashed()) {
                    $debtCase->restore();
                }

                if (! $debtCase) {
                    $debtCase = $this->createDebtCaseFromOrder($formOrder, $userId);
                }
            }

            if (! $debtCase) {
                throw new InvalidArgumentException(
                    'Brak sprawy windykacyjnej i zamówienia — akceptacja wymaga form_order_id lub debt_case_id.'
                );
            }

            $transaction = $match->transaction;
            if (! $transaction) {
                throw new InvalidArgumentException('Brak transakcji bankowej dla tego dopasowania.');
            }

            $transaction->loadMissing('matches');

            if ($transaction->isIgnored()) {
                throw new InvalidArgumentException('Ten przelew jest zignorowany — najpierw cofnij ignorowanie.');
            }

            if ($transaction->isDeferred()) {
                $this->undeferTransaction($transaction);
                $transaction->unsetRelation('matches');
                $transaction->load('matches');
                $match->refresh();
            }

            $formOrderId = (int) ($match->form_order_id ?: $debtCase->form_order_id);
            $duplicateAccepted = $transaction->acceptedMatches()->contains(
                function (BankTransactionMatch $existing) use ($debtCase, $formOrderId) {
                    if ((int) $existing->debt_case_id === (int) $debtCase->id) {
                        return true;
                    }

                    return $formOrderId > 0 && (int) $existing->form_order_id === $formOrderId;
                }
            );
            if ($duplicateAccepted) {
                throw new InvalidArgumentException(
                    'Ten przelew jest już powiązany z tą sprawą/zamówieniem.'
                );
            }

            $remaining = $transaction->remainingAllocatableAmount();
            if ($remaining <= BankTransactionMatcher::AMOUNT_EPSILON) {
                throw new InvalidArgumentException(
                    'Brak wolnej kwoty do podziału na tym przelewie (całość już przypisana).'
                );
            }

            $allocatedAmount = $this->resolveAllocatedAmount($transaction, $debtCase, $formOrder);
            if ($allocatedAmount <= BankTransactionMatcher::AMOUNT_EPSILON) {
                $remainingOnCase = $debtCase->remainingBankAllocatableAmount();
                if ($remainingOnCase !== null && $remainingOnCase <= BankTransactionMatcher::AMOUNT_EPSILON) {
                    throw new InvalidArgumentException(
                        'Ta sprawa/FV jest już w pełni pokryta wpłatami z wyciągu — nie można dodać kolejnego przelewu (nadpłata zablokowana).'
                    );
                }
                throw new InvalidArgumentException('Nie udało się wyliczyć kwoty alokacji dla tego powiązania.');
            }
            if ($allocatedAmount - $remaining > BankTransactionMatcher::AMOUNT_EPSILON) {
                throw new InvalidArgumentException(sprintf(
                    'Kwota alokacji (%s) przekracza wolne %s PLN na przelewie.',
                    number_format($allocatedAmount, 2, ',', ' '),
                    number_format($remaining, 2, ',', ' ')
                ));
            }

            $caseRemainingBefore = $debtCase->remainingBankAllocatableAmount();
            $invoiceAmount = round((float) (
                $debtCase->amount_gross
                ?? $formOrder?->product_price
                ?? $debtCase->formOrder?->product_price
                ?? 0
            ), 2);

            $transferAmount = number_format((float) $transaction->amount, 2, ',', ' ');
            $allocatedFormatted = number_format($allocatedAmount, 2, ',', ' ');
            $date = $transaction->operation_date?->format('Y-m-d') ?? '—';
            $invoice = $debtCase->invoice_number
                ?: ($formOrder?->invoice_number ?? '—');

            $isSplit = abs($allocatedAmount - (float) $transaction->amount) > BankTransactionMatcher::AMOUNT_EPSILON
                || $transaction->acceptedMatches()->isNotEmpty();

            $note = $isSplit
                ? sprintf(
                    'Zaakceptowano część wpłaty z wyciągu mBank: %s z %s %s z dnia %s (FV %s). Transakcja #%d.',
                    $allocatedFormatted,
                    $transferAmount,
                    $transaction->currency,
                    $date,
                    $invoice,
                    $transaction->id
                )
                : sprintf(
                    'Zaakceptowano wpłatę z wyciągu mBank: %s %s z dnia %s (FV %s). Transakcja #%d.',
                    $transferAmount,
                    $transaction->currency,
                    $date,
                    $invoice,
                    $transaction->id
                );
            if ($acceptedViaIfirmaPaid) {
                $note .= ' iFirma przed akceptacją wskazywała fakturę jako opłaconą — nie rejestrowano nowej wpłaty w iFirma.';
            }

            $debtCase->actions()->create([
                'user_id' => $userId,
                'action_type' => DebtCaseAction::TYPE_BANK_MATCH,
                'happened_at' => now(),
                'note' => $note,
            ]);

            $debtCase->forceFill([
                'last_action_at' => now(),
                'assigned_to_id' => $userId ?: $debtCase->assigned_to_id,
            ])->save();

            // Odrzuć tylko konkurencyjne sugestie tej samej FV/sprawy; inne zostają pod podział.
            BankTransactionMatch::query()
                ->where('bank_transaction_id', $match->bank_transaction_id)
                ->where('id', '!=', $match->id)
                ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
                ->where(function ($query) use ($debtCase, $formOrderId) {
                    $query->where('debt_case_id', $debtCase->id);
                    if ($formOrderId > 0) {
                        $query->orWhere('form_order_id', $formOrderId);
                    }
                })
                ->update(['status' => BankTransactionMatch::STATUS_REJECTED]);

            $reasons = array_values(array_unique(array_filter([
                ...($match->match_reasons ?? []),
                $isSplit ? 'split_allocation' : null,
            ])));

            $allocationMatchesInvoice = $invoiceAmount > BankTransactionMatcher::AMOUNT_EPSILON
                && abs($allocatedAmount - $invoiceAmount) <= BankTransactionMatcher::AMOUNT_EPSILON;
            $allocationMatchesRemainder = $caseRemainingBefore !== null
                && $caseRemainingBefore > BankTransactionMatcher::AMOUNT_EPSILON
                && abs($allocatedAmount - $caseRemainingBefore) <= BankTransactionMatcher::AMOUNT_EPSILON;
            $allocationAmountOk = $allocationMatchesInvoice || $allocationMatchesRemainder;

            if ($allocationAmountOk) {
                $reasons = array_values(array_filter(
                    $reasons,
                    fn ($reason) => $reason !== 'amount_mismatch'
                ));
                if (! in_array('amount_match', $reasons, true)) {
                    $reasons[] = 'amount_match';
                }
            } elseif ($invoiceAmount > BankTransactionMatcher::AMOUNT_EPSILON
                && abs($allocatedAmount - $invoiceAmount) > BankTransactionMatcher::AMOUNT_EPSILON
                && ! in_array('amount_mismatch', $reasons, true)) {
                $reasons[] = 'amount_mismatch';
            }

            $confidence = $match->confidence;
            if (in_array('multi_invoice_sum_match', $reasons, true)
                || $allocationAmountOk) {
                $confidence = BankTransactionMatch::CONFIDENCE_HIGH;
            }

            $match->update([
                'debt_case_id' => $debtCase->id,
                'form_order_id' => $match->form_order_id ?: $debtCase->form_order_id,
                'status' => BankTransactionMatch::STATUS_ACCEPTED,
                'allocated_amount' => $allocatedAmount,
                'match_reasons' => $reasons,
                'confidence' => $confidence,
                'accepted_by' => $userId,
                'accepted_at' => now(),
            ]);

            $transaction->unsetRelation('matches');
            $transaction->load('matches');
            if ($transaction->isFullyAllocated()) {
                BankTransactionMatch::query()
                    ->where('bank_transaction_id', $transaction->id)
                    ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
                    ->update(['status' => BankTransactionMatch::STATUS_REJECTED]);
            }

            return $match->fresh(['transaction', 'debtCase', 'formOrder']);
        });
    }

    /**
     * Akceptacja lokalna pakietu sugestii (suma FV ≈ przelew) — bez rejestracji w iFirma.
     *
     * @return list<BankTransactionMatch>
     */
    public function acceptSuggestedSplitPackage(BankTransaction $transaction, ?int $userId = null): array
    {
        $transaction->loadMissing('matches');

        if ($transaction->isDeferred()) {
            $this->undeferTransaction($transaction);
            $transaction->unsetRelation('matches');
            $transaction->load('matches');
        }

        $package = $transaction->matches
            ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
            ->filter(fn (BankTransactionMatch $match) => in_array(
                'multi_invoice_sum_match',
                $match->match_reasons ?? [],
                true
            ))
            ->sortBy('id')
            ->values();

        if ($package->count() < 2) {
            throw new InvalidArgumentException(
                'Brak pakietu sugestii do podziału (wymagane co najmniej 2 FV z sumą ≈ kwota przelewu).'
            );
        }

        $accepted = [];
        foreach ($package as $match) {
            $accepted[] = $this->acceptMatch($match, $userId, false);
        }

        return $accepted;
    }

    /**
     * Kwota z przelewu przypisywana do jednej FV/sprawy (podział lub całość).
     */
    private function resolveAllocatedAmount(
        BankTransaction $transaction,
        DebtCase $debtCase,
        ?FormOrder $formOrder
    ): float {
        $remainingOnTransfer = $transaction->remainingAllocatableAmount();
        $invoiceAmount = round((float) (
            $debtCase->amount_gross
            ?? $formOrder?->product_price
            ?? $debtCase->formOrder?->product_price
            ?? 0
        ), 2);

        if ($invoiceAmount <= BankTransactionMatcher::AMOUNT_EPSILON) {
            return round($remainingOnTransfer, 2);
        }

        $remainingOnCase = $debtCase->remainingBankAllocatableAmount();
        if ($remainingOnCase === null) {
            return round(min($invoiceAmount, $remainingOnTransfer), 2);
        }

        if ($remainingOnCase <= BankTransactionMatcher::AMOUNT_EPSILON) {
            return 0.0;
        }

        return round(min($remainingOnTransfer, $remainingOnCase), 2);
    }

    public function rejectMatch(BankTransactionMatch $match): BankTransactionMatch
    {
        $match->update(['status' => BankTransactionMatch::STATUS_REJECTED]);

        return $match->fresh();
    }

    /**
     * Ręczne powiązanie wpływu ze sprawą windykacyjną + natychmiastowa akceptacja.
     */
    public function manuallyLinkTransactionToDebtCase(
        BankTransaction $transaction,
        DebtCase $debtCase,
        ?int $userId = null
    ): BankTransactionMatch {
        if (! $transaction->is_incoming) {
            throw new InvalidArgumentException('Można powiązać tylko wpływy (przelewy przychodzące).');
        }

        $transaction->loadMissing('matches');

        if ($transaction->isIgnored()) {
            throw new InvalidArgumentException('Ten przelew jest zignorowany.');
        }

        if ($transaction->isDeferred()) {
            $this->undeferTransaction($transaction);
            $transaction->unsetRelation('matches');
            $transaction->load('matches');
        }

        if (! $transaction->canAcceptAdditionalLink()) {
            throw new InvalidArgumentException(
                'Brak wolnej kwoty do podziału na tym przelewie (całość już przypisana).'
            );
        }

        if ($transaction->acceptedMatches()->contains(
            fn (BankTransactionMatch $existing) => (int) $existing->debt_case_id === (int) $debtCase->id
        )) {
            throw new InvalidArgumentException('Ten przelew jest już powiązany z tą sprawą.');
        }

        $debtCase->loadMissing('formOrder');
        $remainingOnCase = $debtCase->remainingBankAllocatableAmount();
        if ($remainingOnCase !== null && $remainingOnCase <= BankTransactionMatcher::AMOUNT_EPSILON) {
            throw new InvalidArgumentException(
                'Ta sprawa/FV jest już w pełni pokryta wpłatami z wyciągu — nie można dodać kolejnego przelewu (nadpłata zablokowana).'
            );
        }

        $userId = $userId ?? Auth::id();

        $invoiceAmount = round((float) ($debtCase->amount_gross ?? $debtCase->formOrder?->product_price ?? 0), 2);
        $remaining = $transaction->remainingAllocatableAmount();
        $allocated = $this->resolveAllocatedAmount($transaction, $debtCase, $debtCase->formOrder);
        if ($allocated <= BankTransactionMatcher::AMOUNT_EPSILON) {
            throw new InvalidArgumentException('Nie udało się wyliczyć kwoty alokacji dla tego powiązania.');
        }
        $isSplit = $transaction->acceptedMatches()->isNotEmpty()
            || abs((float) $transaction->amount - $allocated) > BankTransactionMatcher::AMOUNT_EPSILON;
        $amountMatches = (
            $invoiceAmount > BankTransactionMatcher::AMOUNT_EPSILON
            && abs($allocated - $invoiceAmount) <= BankTransactionMatcher::AMOUNT_EPSILON
        ) || (
            $remainingOnCase !== null
            && abs($allocated - $remainingOnCase) <= BankTransactionMatcher::AMOUNT_EPSILON
        );

        $reasons = array_values(array_filter([
            'manual_case_link',
            $isSplit ? 'split_allocation' : null,
            $amountMatches ? 'amount_match' : 'amount_mismatch',
        ]));

        $confidence = $amountMatches
            ? BankTransactionMatch::CONFIDENCE_HIGH
            : BankTransactionMatch::CONFIDENCE_LOW;

        $match = BankTransactionMatch::query()
            ->where('bank_transaction_id', $transaction->id)
            ->where('debt_case_id', $debtCase->id)
            ->first();

        if ($match) {
            $match->forceFill([
                'form_order_id' => $debtCase->form_order_id,
                'confidence' => $confidence,
                'match_reasons' => $reasons,
                'status' => BankTransactionMatch::STATUS_SUGGESTED,
            ])->save();
        } else {
            $match = BankTransactionMatch::create([
                'bank_transaction_id' => $transaction->id,
                'debt_case_id' => $debtCase->id,
                'form_order_id' => $debtCase->form_order_id,
                'confidence' => $confidence,
                'match_reasons' => $reasons,
                'status' => BankTransactionMatch::STATUS_SUGGESTED,
            ]);
        }

        return $this->acceptMatch($match, $userId);
    }

    /**
     * Ręczne powiązanie wpływu z zamówieniem (bez aktywnej sprawy) + akceptacja.
     * acceptMatch utworzy lub przywróci sprawę windykacyjną.
     */
    public function manuallyLinkTransactionToFormOrder(
        BankTransaction $transaction,
        FormOrder $order,
        ?int $userId = null
    ): BankTransactionMatch {
        if (! $transaction->is_incoming) {
            throw new InvalidArgumentException('Można powiązać tylko wpływy (przelewy przychodzące).');
        }

        $transaction->loadMissing('matches');

        if ($transaction->isIgnored()) {
            throw new InvalidArgumentException('Ten przelew jest zignorowany.');
        }

        if ($transaction->isDeferred()) {
            $this->undeferTransaction($transaction);
            $transaction->unsetRelation('matches');
            $transaction->load('matches');
        }

        if (! $transaction->canAcceptAdditionalLink()) {
            throw new InvalidArgumentException(
                'Brak wolnej kwoty do podziału na tym przelewie (całość już przypisana).'
            );
        }

        if ($transaction->acceptedMatches()->contains(
            fn (BankTransactionMatch $existing) => (int) $existing->form_order_id === (int) $order->id
        )) {
            throw new InvalidArgumentException('Ten przelew jest już powiązany z tym zamówieniem.');
        }

        $activeCase = DebtCase::query()
            ->where('form_order_id', $order->id)
            ->where('status', '!=', DebtCase::STATUS_CLOSED)
            ->latest('id')
            ->first();

        if ($activeCase) {
            return $this->manuallyLinkTransactionToDebtCase($transaction, $activeCase, $userId);
        }

        $userId = $userId ?? Auth::id();
        $invoiceAmount = round((float) ($order->product_price ?? 0), 2);
        $remaining = $transaction->remainingAllocatableAmount();
        $allocated = $invoiceAmount > BankTransactionMatcher::AMOUNT_EPSILON
            ? min($invoiceAmount, $remaining)
            : $remaining;
        $isSplit = $transaction->acceptedMatches()->isNotEmpty()
            || abs((float) $transaction->amount - $allocated) > BankTransactionMatcher::AMOUNT_EPSILON;
        $amountMatches = $invoiceAmount > BankTransactionMatcher::AMOUNT_EPSILON
            && abs($allocated - $invoiceAmount) <= BankTransactionMatcher::AMOUNT_EPSILON;

        $reasons = array_values(array_filter([
            'manual_case_link',
            $isSplit ? 'split_allocation' : null,
            $amountMatches ? 'amount_match' : 'amount_mismatch',
        ]));

        $confidence = $amountMatches
            ? BankTransactionMatch::CONFIDENCE_HIGH
            : BankTransactionMatch::CONFIDENCE_LOW;

        $match = BankTransactionMatch::query()
            ->where('bank_transaction_id', $transaction->id)
            ->where('form_order_id', $order->id)
            ->whereNull('debt_case_id')
            ->first();

        if ($match) {
            $match->forceFill([
                'confidence' => $confidence,
                'match_reasons' => $reasons,
                'status' => BankTransactionMatch::STATUS_SUGGESTED,
            ])->save();
        } else {
            $match = BankTransactionMatch::create([
                'bank_transaction_id' => $transaction->id,
                'form_order_id' => $order->id,
                'debt_case_id' => null,
                'confidence' => $confidence,
                'match_reasons' => $reasons,
                'status' => BankTransactionMatch::STATUS_SUGGESTED,
            ]);
        }

        return $this->acceptMatch($match, $userId);
    }

    public function ignoreMatch(BankTransactionMatch $match): BankTransactionMatch
    {
        $match->update(['status' => BankTransactionMatch::STATUS_IGNORED]);

        return $match->fresh();
    }

    public function ignoreTransaction(BankTransaction $transaction, array $reasons = [BankTransactionMatch::REASON_MANUAL_IGNORE]): void
    {
        $transaction->matches()
            ->whereIn('status', [
                BankTransactionMatch::STATUS_SUGGESTED,
                BankTransactionMatch::STATUS_DEFERRED,
            ])
            ->update(['status' => BankTransactionMatch::STATUS_IGNORED]);

        $hasIgnored = $transaction->matches()
            ->where('status', BankTransactionMatch::STATUS_IGNORED)
            ->exists();

        if (! $hasIgnored) {
            $transaction->matches()->create([
                'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
                'match_reasons' => array_values($reasons),
                'status' => BankTransactionMatch::STATUS_IGNORED,
            ]);
        }
    }

    /**
     * Oznacza wpływ jako „Na potem” (poza aktywną kolejką, ale nadal do przeglądu na liście importów).
     */
    public function deferTransaction(BankTransaction $transaction, array $reasons = [BankTransactionMatch::REASON_MANUAL_DEFER]): void
    {
        $transaction->loadMissing('matches');

        if ($transaction->isIgnored()) {
            throw new InvalidArgumentException('Ten przelew jest zignorowany — nie można oznaczyć „Na potem”.');
        }

        if ($transaction->isDeferred()) {
            return;
        }

        if ($transaction->acceptedMatches()->isNotEmpty() && ! $transaction->canAcceptAdditionalLink()) {
            throw new InvalidArgumentException('Ten przelew jest już w pełni przypisany.');
        }

        $suggested = $transaction->matches()
            ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
            ->get();

        foreach ($suggested as $match) {
            $matchReasons = array_values(array_unique(array_merge(
                array_map('strval', $match->match_reasons ?? []),
                array_map('strval', $reasons)
            )));
            $match->update([
                'status' => BankTransactionMatch::STATUS_DEFERRED,
                'match_reasons' => $matchReasons,
            ]);
        }

        $hasDeferred = $transaction->matches()
            ->where('status', BankTransactionMatch::STATUS_DEFERRED)
            ->exists();

        if (! $hasDeferred) {
            $transaction->matches()->create([
                'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
                'match_reasons' => array_values($reasons),
                'status' => BankTransactionMatch::STATUS_DEFERRED,
            ]);
        }
    }

    /**
     * Przywraca wpływ z „Na potem” do aktywnej kolejki przeglądu.
     */
    public function undeferTransaction(BankTransaction $transaction): void
    {
        $deferred = $transaction->matches()
            ->where('status', BankTransactionMatch::STATUS_DEFERRED)
            ->get();

        if ($deferred->isEmpty()) {
            return;
        }

        foreach ($deferred as $match) {
            $hasTarget = $match->form_order_id || $match->debt_case_id;
            if ($hasTarget) {
                $reasons = array_values(array_filter(
                    array_map('strval', $match->match_reasons ?? []),
                    fn (string $reason) => $reason !== BankTransactionMatch::REASON_MANUAL_DEFER
                ));
                $match->update([
                    'status' => BankTransactionMatch::STATUS_SUGGESTED,
                    'match_reasons' => $reasons,
                ]);
            } else {
                $match->delete();
            }
        }
    }

    /**
     * Ignoruje wyłącznie wpływy rozpoznane jako wypłaty rozliczeniowe PayNow (mElements).
     * Nie opiera się na braku FV/KSeF. Pomija już zaakceptowane i już ignorowane.
     *
     * @return array{candidates: int, ignored: int, skipped_accepted: int, skipped_already_ignored: int}
     */
    public function ignorePayNowGatewayPayouts(BankStatementImport $import): array
    {
        $transactions = $import->transactions()
            ->where('is_incoming', true)
            ->with('matches')
            ->orderBy('id')
            ->get();

        $candidates = 0;
        $ignored = 0;
        $skippedAccepted = 0;
        $skippedAlreadyIgnored = 0;

        foreach ($transactions as $transaction) {
            if (! $this->payNowGatewayPayoutDetector->isPayNowGatewayPayout($transaction)) {
                continue;
            }

            $candidates++;

            if ($transaction->matches->contains(
                fn (BankTransactionMatch $match) => $match->status === BankTransactionMatch::STATUS_ACCEPTED
            )) {
                $skippedAccepted++;

                continue;
            }

            if ($transaction->matches->contains(
                fn (BankTransactionMatch $match) => $match->status === BankTransactionMatch::STATUS_IGNORED
            )) {
                $skippedAlreadyIgnored++;

                continue;
            }

            $this->ignoreTransaction($transaction, [BankTransactionMatch::REASON_GATEWAY_PAYOUT_PAYNOW]);
            $ignored++;
        }

        return [
            'candidates' => $candidates,
            'ignored' => $ignored,
            'skipped_accepted' => $skippedAccepted,
            'skipped_already_ignored' => $skippedAlreadyIgnored,
        ];
    }

    public function countIgnorablePayNowGatewayPayouts(BankStatementImport $import): int
    {
        return $import->transactions()
            ->where('is_incoming', true)
            ->whereDoesntHave('matches', function ($query) {
                $query->whereIn('status', [
                    BankTransactionMatch::STATUS_ACCEPTED,
                    BankTransactionMatch::STATUS_IGNORED,
                ]);
            })
            ->get()
            ->filter(fn (BankTransaction $transaction) => $this->payNowGatewayPayoutDetector->isPayNowGatewayPayout($transaction))
            ->count();
    }

    public function countPayNowGatewayPayouts(BankStatementImport $import): int
    {
        return $this->payNowGatewayPayoutsQuery($import)->count();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\BankTransaction>|\Illuminate\Database\Eloquent\Relations\Relation  $base
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\BankTransaction>
     */
    public function payNowGatewayPayoutsQuery(BankStatementImport $import)
    {
        return $import->transactions()
            ->where('is_incoming', true)
            ->whereHas('matches', function ($query) {
                $query->where('status', BankTransactionMatch::STATUS_IGNORED)
                    ->whereJsonContains('match_reasons', BankTransactionMatch::REASON_GATEWAY_PAYOUT_PAYNOW);
            });
    }

    private function createDebtCaseFromOrder(FormOrder $order, ?int $userId): DebtCase
    {
        $profile = $this->profileService->profileForOrder($order);
        $invoiceDate = $order->invoice_issue_date?->copy()
            ?? $order->order_date?->copy();
        $dueDate = $order->invoice_due_date?->copy();
        if ($dueDate === null) {
            $delay = (int) ($order->invoice_payment_delay ?: 14);
            $dueBase = $order->invoice_issue_date?->copy() ?? $order->order_date?->copy();
            $dueDate = $dueBase?->copy()->addDays($delay);
        }

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'created_by' => $userId,
            'assigned_to_id' => $userId,
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
            'summary' => 'Sprawa utworzona przy akceptacji wpłaty z wyciągu bankowego.',
        ]);

        $case->actions()->create([
            'user_id' => $userId,
            'action_type' => DebtCaseAction::TYPE_CASE_OPENED,
            'happened_at' => now(),
            'note' => 'Utworzono sprawę przy akceptacji dopasowania przelewu z wyciągu mBank.',
        ]);

        $ifirma = $this->ifirmaPaymentStatus ?? app(IfirmaInvoicePaymentStatusService::class);
        $actor = $userId ? User::query()->find($userId) : null;
        $ifirma->syncDebtCaseAfterCreate($case, $actor);

        return $case;
    }
}
