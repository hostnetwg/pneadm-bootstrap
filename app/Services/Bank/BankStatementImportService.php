<?php

namespace App\Services\Bank;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Services\DebtCustomerProfileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $import->update([
            'rows_incoming' => $incoming,
            'rows_matched' => $matched,
            'rows_duplicate' => $duplicates,
        ]);

        return $import->fresh();
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
                    if ($transaction->matches()
                        ->where('status', BankTransactionMatch::STATUS_ACCEPTED)
                        ->exists()) {
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
            $match->loadMissing(['transaction', 'formOrder', 'debtCase']);

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
            $amount = number_format((float) $transaction->amount, 2, ',', ' ');
            $date = $transaction->operation_date?->format('Y-m-d') ?? '—';
            $invoice = $debtCase->invoice_number
                ?: ($formOrder?->invoice_number ?? '—');

            $note = sprintf(
                'Zaakceptowano wpłatę z wyciągu mBank: %s %s z dnia %s (FV %s). Transakcja #%d.',
                $amount,
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

            BankTransactionMatch::query()
                ->where('bank_transaction_id', $match->bank_transaction_id)
                ->where('id', '!=', $match->id)
                ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
                ->update(['status' => BankTransactionMatch::STATUS_REJECTED]);

            $match->update([
                'debt_case_id' => $debtCase->id,
                'form_order_id' => $match->form_order_id ?: $debtCase->form_order_id,
                'status' => BankTransactionMatch::STATUS_ACCEPTED,
                'accepted_by' => $userId,
                'accepted_at' => now(),
            ]);

            return $match->fresh(['transaction', 'debtCase', 'formOrder']);
        });
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

        if ($transaction->matches()
            ->whereIn('status', [
                BankTransactionMatch::STATUS_ACCEPTED,
                BankTransactionMatch::STATUS_IGNORED,
            ])
            ->exists()) {
            throw new InvalidArgumentException('Ten przelew jest już zaakceptowany albo zignorowany.');
        }

        $debtCase->loadMissing('formOrder');
        $userId = $userId ?? Auth::id();

        $amountMatches = abs(
            round((float) $transaction->amount, 2)
            - round((float) ($debtCase->amount_gross ?? $debtCase->formOrder?->product_price ?? 0), 2)
        ) <= 0.01;

        $reasons = [
            'manual_case_link',
            $amountMatches ? 'amount_match' : 'amount_mismatch',
        ];

        $match = BankTransactionMatch::query()
            ->where('bank_transaction_id', $transaction->id)
            ->where('debt_case_id', $debtCase->id)
            ->first();

        if ($match) {
            $match->forceFill([
                'form_order_id' => $debtCase->form_order_id,
                'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
                'match_reasons' => $reasons,
                'status' => BankTransactionMatch::STATUS_SUGGESTED,
            ])->save();
        } else {
            $match = BankTransactionMatch::create([
                'bank_transaction_id' => $transaction->id,
                'debt_case_id' => $debtCase->id,
                'form_order_id' => $debtCase->form_order_id,
                'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
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

        if ($transaction->matches()
            ->whereIn('status', [
                BankTransactionMatch::STATUS_ACCEPTED,
                BankTransactionMatch::STATUS_IGNORED,
            ])
            ->exists()) {
            throw new InvalidArgumentException('Ten przelew jest już zaakceptowany albo zignorowany.');
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
        $amountMatches = abs(
            round((float) $transaction->amount, 2)
            - round((float) ($order->product_price ?? 0), 2)
        ) <= 0.01;

        $reasons = [
            'manual_case_link',
            $amountMatches ? 'amount_match' : 'amount_mismatch',
        ];

        $match = BankTransactionMatch::query()
            ->where('bank_transaction_id', $transaction->id)
            ->where('form_order_id', $order->id)
            ->whereNull('debt_case_id')
            ->first();

        if ($match) {
            $match->forceFill([
                'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
                'match_reasons' => $reasons,
                'status' => BankTransactionMatch::STATUS_SUGGESTED,
            ])->save();
        } else {
            $match = BankTransactionMatch::create([
                'bank_transaction_id' => $transaction->id,
                'form_order_id' => $order->id,
                'debt_case_id' => null,
                'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
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
            ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
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
        $delay = (int) ($order->invoice_payment_delay ?: 14);
        $invoiceDate = $order->order_date?->copy();
        $dueDate = $invoiceDate?->copy()->addDays($delay);

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

        return $case;
    }
}
