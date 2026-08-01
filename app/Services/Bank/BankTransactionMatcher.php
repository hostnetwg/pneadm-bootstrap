<?php

namespace App\Services\Bank;

use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\FormOrder;
use Illuminate\Support\Collection;

class BankTransactionMatcher
{
    public const AMOUNT_EPSILON = 0.01;

    /** @var array<string, list<FormOrder>>|null */
    private ?array $ordersByInvoice = null;

    /** @var array<string, list<DebtCase>>|null */
    private ?array $casesByInvoice = null;

    /** @var array<string, list<FormOrder>>|null */
    private ?array $ordersByKsef = null;

    /** @var array<int, FormOrder>|null */
    private ?array $ordersById = null;

    /** @var array<string, list<FormOrder>>|null */
    private ?array $ordersByNip = null;

    /** @var array<string, list<FormOrder>>|null keyed by amount "365.00" — zamówienia bez NIP z buyer_name */
    private ?array $ordersByAmountNoNip = null;

    /** @var array<int, DebtCase>|null */
    private ?array $activeCasesByOrderId = null;

    private bool $cachesWarmed = false;

    public function __construct(
        private readonly PaymentTitleExtractor $titleExtractor = new PaymentTitleExtractor,
    ) {}

    /**
     * Preload lookup maps so bulk import avoids per-row SQL.
     */
    public function warmLookupCaches(): void
    {
        $this->ordersByInvoice = [];
        $this->casesByInvoice = [];
        $this->ordersByKsef = [];
        $this->ordersById = [];
        $this->ordersByNip = [];
        $this->ordersByAmountNoNip = [];
        $this->activeCasesByOrderId = [];

        FormOrder::query()
            ->select([
                'id',
                'invoice_number',
                'product_price',
                'ksef_number',
                'buyer_nip',
                'buyer_name',
                'recipient_nip',
                'recipient_name',
            ])
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->where('invoice_number', '!=', '0')
            ->orderBy('id')
            ->chunkById(2000, function (Collection $orders) {
                foreach ($orders as $order) {
                    $this->ordersById[(int) $order->id] = $order;

                    $invoiceKey = $this->titleExtractor->normalizeInvoiceNumber((string) $order->invoice_number);
                    $this->ordersByInvoice[$invoiceKey][] = $order;

                    if (is_string($order->ksef_number) && trim($order->ksef_number) !== '') {
                        $ksefKey = $this->titleExtractor->normalizeKsefNumber($order->ksef_number);
                        if ($ksefKey !== null) {
                            $this->ordersByKsef[$ksefKey][] = $order;
                        }
                    }

                    $buyerNip = preg_replace('/\D+/', '', (string) ($order->buyer_nip ?? '')) ?: '';
                    if (strlen($buyerNip) === 10) {
                        $this->ordersByNip[$buyerNip][] = $order;
                    } elseif ($this->isUsableBuyerName((string) ($order->buyer_name ?? ''))) {
                        $amountKey = number_format((float) $order->product_price, 2, '.', '');
                        $this->ordersByAmountNoNip[$amountKey][] = $order;
                    }

                    $recipientNip = preg_replace('/\D+/', '', (string) ($order->recipient_nip ?? '')) ?: '';
                    if (strlen($recipientNip) === 10 && $recipientNip !== $buyerNip) {
                        $this->ordersByNip[$recipientNip][] = $order;
                    }
                }
            });

        DebtCase::query()
            ->select(['id', 'form_order_id', 'invoice_number', 'amount_gross', 'status'])
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->orderBy('id')
            ->chunkById(2000, function (Collection $cases) {
                foreach ($cases as $case) {
                    $invoiceKey = $this->titleExtractor->normalizeInvoiceNumber((string) $case->invoice_number);
                    $this->casesByInvoice[$invoiceKey][] = $case;
                }
            });

        DebtCase::query()
            ->select(['id', 'form_order_id', 'invoice_number', 'amount_gross', 'status'])
            ->where('status', '!=', DebtCase::STATUS_CLOSED)
            ->whereNotNull('form_order_id')
            ->orderBy('id')
            ->chunkById(2000, function (Collection $cases) {
                foreach ($cases as $case) {
                    $orderId = (int) $case->form_order_id;
                    $existing = $this->activeCasesByOrderId[$orderId] ?? null;
                    if (! $existing || (int) $case->id > (int) $existing->id) {
                        $this->activeCasesByOrderId[$orderId] = $case;
                    }
                }
            });

        $this->cachesWarmed = true;
    }

    /**
     * Build suggested matches for a transaction (does not persist).
     *
     * @return list<array{
     *     form_order_id: ?int,
     *     debt_case_id: ?int,
     *     confidence: string,
     *     match_reasons: list<string>,
     *     score: int
     * }>
     */
    public function suggest(BankTransaction $transaction): array
    {
        if (! $transaction->is_incoming || (float) $transaction->amount <= 0) {
            return [];
        }

        $extracted = $this->titleExtractor->extract($transaction->description);
        $amount = (float) $transaction->amount;
        $candidates = [];
        $senderEstimate = (new MbankStatementParser)->splitDescriptionParts($transaction->description)['sender_estimate'] ?? null;

        foreach ($extracted['invoice_numbers'] as $invoiceNumber) {
            foreach ($this->findOrdersByInvoiceNumber($invoiceNumber) as $order) {
                $key = 'order:'.$order->id;
                $reasons = ['invoice_number:'.$invoiceNumber];
                $confidence = BankTransactionMatch::CONFIDENCE_MEDIUM;
                $score = 50;

                if ($this->amountsMatch($amount, (float) $order->product_price)) {
                    $confidence = BankTransactionMatch::CONFIDENCE_HIGH;
                    $score = 100;
                    $reasons[] = 'amount_match';
                } else {
                    $reasons[] = 'amount_mismatch';
                    $score = 40;
                }

                $debtCase = $this->activeDebtCaseForOrder($order);
                if ($debtCase) {
                    $reasons[] = 'existing_debt_case';
                    $score += 5;
                }

                $candidates[$key] = $this->mergeCandidate($candidates[$key] ?? null, [
                    'form_order_id' => $order->id,
                    'debt_case_id' => $debtCase?->id,
                    'confidence' => $confidence,
                    'match_reasons' => $reasons,
                    'score' => $score,
                ]);
            }

            foreach ($this->findDebtCasesByInvoiceNumber($invoiceNumber) as $case) {
                if ($case->form_order_id && isset($candidates['order:'.$case->form_order_id])) {
                    continue;
                }

                $key = 'case:'.$case->id;
                $reasons = ['debt_case_invoice_number:'.$invoiceNumber];
                $confidence = BankTransactionMatch::CONFIDENCE_MEDIUM;
                $score = 45;

                if ($case->amount_gross !== null && $this->amountsMatch($amount, (float) $case->amount_gross)) {
                    $confidence = BankTransactionMatch::CONFIDENCE_HIGH;
                    $score = 95;
                    $reasons[] = 'amount_match';
                }

                $candidates[$key] = $this->mergeCandidate($candidates[$key] ?? null, [
                    'form_order_id' => $case->form_order_id,
                    'debt_case_id' => $case->id,
                    'confidence' => $confidence,
                    'match_reasons' => $reasons,
                    'score' => $score,
                ]);
            }
        }

        foreach ($extracted['ksef_numbers'] as $ksef) {
            foreach ($this->findOrdersByKsef($ksef) as $order) {
                $key = 'order:'.$order->id;
                $reasons = ['ksef_number:'.$ksef];
                $confidence = BankTransactionMatch::CONFIDENCE_MEDIUM;
                $score = 55;

                if ($this->amountsMatch($amount, (float) $order->product_price)) {
                    $confidence = BankTransactionMatch::CONFIDENCE_HIGH;
                    $score = 90;
                    $reasons[] = 'amount_match';
                }

                $debtCase = $this->activeDebtCaseForOrder($order);
                $candidates[$key] = $this->mergeCandidate($candidates[$key] ?? null, [
                    'form_order_id' => $order->id,
                    'debt_case_id' => $debtCase?->id,
                    'confidence' => $confidence,
                    'match_reasons' => $reasons,
                    'score' => $score,
                ]);
            }
        }

        foreach ($extracted['order_ids'] as $orderId) {
            $order = $this->findOrderById($orderId);
            if (! $order) {
                continue;
            }

            $key = 'order:'.$order->id;
            $reasons = ['order_id:'.$orderId];
            $confidence = BankTransactionMatch::CONFIDENCE_MEDIUM;
            $score = 60;

            if ($this->amountsMatch($amount, (float) $order->product_price)) {
                $score = 75;
                $reasons[] = 'amount_match';
            } else {
                $reasons[] = 'amount_mismatch';
                $score = 35;
            }

            $debtCase = $this->activeDebtCaseForOrder($order);
            $candidates[$key] = $this->mergeCandidate($candidates[$key] ?? null, [
                'form_order_id' => $order->id,
                'debt_case_id' => $debtCase?->id,
                'confidence' => $confidence,
                'match_reasons' => $reasons,
                'score' => $score,
            ]);
        }

        foreach ($extracted['nips'] as $nip) {
            foreach ($this->findOrdersByNip($nip) as $order) {
                if (! $this->amountsMatch($amount, (float) $order->product_price)) {
                    continue;
                }

                $key = 'order:'.$order->id;
                $reasons = ['nip:'.$nip, 'amount_match'];
                $debtCase = $this->activeDebtCaseForOrder($order);

                $candidates[$key] = $this->mergeCandidate($candidates[$key] ?? null, [
                    'form_order_id' => $order->id,
                    'debt_case_id' => $debtCase?->id,
                    'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
                    'match_reasons' => $reasons,
                    'score' => 25,
                ]);
            }
        }

        // Osoba prywatna (brak NIP nabywcy): imię i nazwisko z tytułu ≈ buyer_name + kwota → Medium
        $normalizedDescription = $this->normalizePersonName($transaction->description);
        foreach ($this->findPrivateOrdersByAmount($amount) as $order) {
            $buyerName = (string) ($order->buyer_name ?? '');
            if (! $this->isUsableBuyerName($buyerName)) {
                continue;
            }

            $normalizedBuyer = $this->normalizePersonName($buyerName);
            if ($normalizedBuyer === '' || ! str_contains($normalizedDescription, $normalizedBuyer)) {
                continue;
            }

            $key = 'order:'.$order->id;
            $reasons = ['buyer_name:'.$buyerName, 'amount_match'];
            $debtCase = $this->activeDebtCaseForOrder($order);

            $candidates[$key] = $this->mergeCandidate($candidates[$key] ?? null, [
                'form_order_id' => $order->id,
                'debt_case_id' => $debtCase?->id,
                'confidence' => BankTransactionMatch::CONFIDENCE_MEDIUM,
                'match_reasons' => $reasons,
                'score' => 55,
            ]);
        }

        foreach ($candidates as $key => $candidate) {
            if (empty($candidate['form_order_id'])) {
                continue;
            }
            $order = $this->findOrderById((int) $candidate['form_order_id']);
            if (! $order) {
                continue;
            }
            [$confidence, $score, $reasons] = $this->applyConflictSignals(
                $candidate['confidence'],
                $candidate['score'],
                $candidate['match_reasons'],
                $extracted['ksef_numbers'],
                $order,
                $senderEstimate
            );
            $candidates[$key]['confidence'] = $confidence;
            $candidates[$key]['score'] = $score;
            $candidates[$key]['match_reasons'] = $reasons;
        }

        $list = array_values($candidates);
        $list = $this->applyKsefPriorityRules($list, $extracted);
        usort($list, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        if (count($list) > 1) {
            foreach ($list as &$item) {
                $item['match_reasons'][] = 'multiple_candidates';
            }
            unset($item);
        }

        return array_map(static function (array $item): array {
            unset($item['score']);

            return $item;
        }, $list);
    }

    /**
     * Gdy w tytule jest nr KSeF i istnieje zamówienie z tym KSeF → tylko te hity
     * (FV z tytułu może być błędna). Brak hitu KSeF w DB → zostaw kandydatów po FV itd.
     *
     * @param  list<array{form_order_id: ?int, debt_case_id: ?int, confidence: string, match_reasons: list<string>, score: int}>  $list
     * @param  array{invoice_numbers: list<string>, ksef_numbers: list<string>, order_ids: list<int>, nips: list<string>}  $extracted
     * @return list<array{form_order_id: ?int, debt_case_id: ?int, confidence: string, match_reasons: list<string>, score: int}>
     */
    private function applyKsefPriorityRules(array $list, array $extracted): array
    {
        $titleKsefs = array_values(array_filter(array_map(
            fn ($k) => $this->titleExtractor->normalizeKsefNumber((string) $k),
            $extracted['ksef_numbers'] ?? []
        )));

        if ($titleKsefs === []) {
            return $list;
        }

        $ksefHits = array_values(array_filter(
            $list,
            fn (array $item): bool => collect($item['match_reasons'] ?? [])->contains(
                fn ($r) => str_starts_with((string) $r, 'ksef_number:')
            )
        ));

        // KSeF w tytule, ale brak zamówienia z tym numerem → pierwszeństwo ma FV / inne sygnały
        if ($ksefHits === []) {
            return $list;
        }

        $titleInvoices = array_values(array_unique(array_map(
            fn ($n) => $this->titleExtractor->normalizeInvoiceNumber((string) $n),
            $extracted['invoice_numbers'] ?? []
        )));

        foreach ($ksefHits as &$item) {
            if (empty($item['form_order_id'])) {
                continue;
            }

            $order = $this->findOrderById((int) $item['form_order_id']);
            if (! $order) {
                continue;
            }

            $orderInvoice = $this->titleExtractor->normalizeInvoiceNumber((string) ($order->invoice_number ?? ''));
            if ($titleInvoices === [] || $orderInvoice === '' || in_array($orderInvoice, $titleInvoices, true)) {
                continue;
            }

            $mismatchLabel = implode(', ', $titleInvoices);
            $item['match_reasons'][] = 'invoice_number_mismatch:'.$mismatchLabel;

            $hasAmountMatch = in_array('amount_match', $item['match_reasons'], true);
            if ($hasAmountMatch) {
                // KSeF + kwota OK, ale FV w tytule ≠ FV w DB (częsty błąd ręcznego numeru)
                $item['confidence'] = BankTransactionMatch::CONFIDENCE_MEDIUM;
                $item['score'] = min((int) $item['score'], 60);
            } else {
                $item['confidence'] = BankTransactionMatch::CONFIDENCE_LOW;
                $item['score'] = min((int) $item['score'], 30);
            }
        }
        unset($item);

        return $ksefHits;
    }

    /**
     * Persist suggested matches for transaction (replaces previous suggested rows).
     *
     * @return Collection<int, BankTransactionMatch>
     */
    public function matchAndPersist(BankTransaction $transaction, int $maxSuggestions = 3): Collection
    {
        $transaction->matches()
            ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
            ->delete();

        $suggestions = array_slice($this->suggest($transaction), 0, $maxSuggestions);
        $created = collect();

        foreach ($suggestions as $suggestion) {
            $created->push($transaction->matches()->create([
                'form_order_id' => $suggestion['form_order_id'],
                'debt_case_id' => $suggestion['debt_case_id'],
                'confidence' => $suggestion['confidence'],
                'match_reasons' => $suggestion['match_reasons'],
                'status' => BankTransactionMatch::STATUS_SUGGESTED,
            ]));
        }

        return $created;
    }

    public function amountsMatch(float $a, float $b): bool
    {
        return abs($a - $b) <= self::AMOUNT_EPSILON;
    }

    /**
     * Konflikty dowodów: błędny FV w tytule vs inny KSeF / inny kontrahent.
     *
     * @param  list<string>  $titleKsefs
     * @param  list<string>  $reasons
     * @return array{0: string, 1: int, 2: list<string>}
     */
    private function applyConflictSignals(
        string $confidence,
        int $score,
        array $reasons,
        array $titleKsefs,
        FormOrder $order,
        ?string $senderEstimate
    ): array {
        $orderKsef = $this->titleExtractor->normalizeKsefNumber(
            is_string($order->ksef_number) ? $order->ksef_number : null
        );
        $normalizedTitleKsefs = array_values(array_filter(array_map(
            fn ($k) => $this->titleExtractor->normalizeKsefNumber((string) $k),
            $titleKsefs
        )));

        $hasPositiveKsefReason = collect($reasons)->contains(
            fn ($r) => str_starts_with((string) $r, 'ksef_number:')
        );

        if ($normalizedTitleKsefs !== [] && $orderKsef !== null && ! $hasPositiveKsefReason) {
            $ksefMatches = in_array($orderKsef, $normalizedTitleKsefs, true);
            if (! $ksefMatches) {
                $reasons[] = 'ksef_mismatch:'.$normalizedTitleKsefs[0];
                $confidence = BankTransactionMatch::CONFIDENCE_LOW;
                $score = min($score, 25);
            }
        }

        if (! $this->partyNamesCompatible(
            $senderEstimate,
            (string) ($order->buyer_name ?? ''),
            (string) ($order->recipient_name ?? '')
        )) {
            $reasons[] = 'party_name_mismatch';
            if ($confidence === BankTransactionMatch::CONFIDENCE_HIGH) {
                $confidence = BankTransactionMatch::CONFIDENCE_MEDIUM;
                $score = min($score, 55);
            } elseif ($confidence === BankTransactionMatch::CONFIDENCE_MEDIUM) {
                $score = min($score, 40);
            }
            // LOW (np. po ksef_mismatch) zostaje LOW
        }

        return [$confidence, $score, array_values(array_unique($reasons))];
    }

    /**
     * Czy nadawca z wyciągu „pasuje” do nabywcy lub odbiorcy (wspólne istotne tokeny).
     */
    public function partyNamesCompatible(?string $senderEstimate, string $buyerName, string $recipientName): bool
    {
        $senderTokens = $this->significantNameTokens($senderEstimate ?? '');
        if ($senderTokens === []) {
            return true; // brak sygnału → nie karz
        }

        $partyTokens = array_values(array_unique(array_merge(
            $this->significantNameTokens($buyerName),
            $this->significantNameTokens($recipientName)
        )));
        if ($partyTokens === []) {
            return true;
        }

        foreach ($senderTokens as $token) {
            foreach ($partyTokens as $partyToken) {
                if ($token === $partyToken
                    || str_contains($partyToken, $token)
                    || str_contains($token, $partyToken)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function significantNameTokens(string $name): array
    {
        $normalized = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $name) ?? ''), 'UTF-8');
        if ($normalized === '') {
            return [];
        }

        $stop = [
            'UL', 'ULICA', 'AL', 'PL', 'OS', 'OSIEDLE', 'IM', 'NR', 'SP', 'ZOO', 'SPOLKA',
            'SPÓŁKA', 'OGRANICZONA', 'ODPOWIEDZIALNOSCIA', 'ODPOWIEDZIALNOŚCIĄ',
            'PRZELEW', 'POLSKA', 'PLATNOSC', 'PŁATNOŚĆ', 'ZA', 'DO', 'W', 'I', 'NA',
            'SZKOLNA', 'SZKOŁA', // too generic alone — keep longer school-specific tokens
        ];

        $parts = preg_split('/[^A-ZĄĆĘŁŃÓŚŹŻ0-9]+/u', $normalized) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || mb_strlen($part) < 4) {
                continue;
            }
            if (in_array($part, $stop, true)) {
                continue;
            }
            if (preg_match('/^\d+$/', $part)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Porównanie bez wielkości liter: "Jan Kowalski" = "JAN KOWALSKI".
     */
    public function normalizePersonName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return mb_strtoupper($name, 'UTF-8');
    }

    public function isUsableBuyerName(string $name): bool
    {
        $normalized = $this->normalizePersonName($name);
        if (mb_strlen($normalized) < 5) {
            return false;
        }

        $parts = array_values(array_filter(explode(' ', $normalized), fn (string $p) => $p !== ''));

        return count($parts) >= 2 && collect($parts)->every(fn (string $p) => mb_strlen($p) >= 2);
    }

    /**
     * @return Collection<int, FormOrder>
     */
    private function findPrivateOrdersByAmount(float $amount): Collection
    {
        $amountKey = number_format($amount, 2, '.', '');

        if ($this->cachesWarmed) {
            return collect($this->ordersByAmountNoNip[$amountKey] ?? [])->take(40);
        }

        return FormOrder::query()
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->where('invoice_number', '!=', '0')
            ->whereNotNull('buyer_name')
            ->where('buyer_name', '!=', '')
            ->where(function ($q) {
                $q->whereNull('buyer_nip')
                    ->orWhere('buyer_nip', '')
                    ->orWhere('buyer_nip', '0');
            })
            ->whereRaw('ABS(COALESCE(product_price, 0) - ?) <= ?', [$amount, self::AMOUNT_EPSILON])
            ->limit(40)
            ->get()
            ->filter(fn (FormOrder $order) => $this->isUsableBuyerName((string) $order->buyer_name))
            ->values();
    }

    /**
     * @return Collection<int, FormOrder>
     */
    private function findOrdersByInvoiceNumber(string $invoiceNumber): Collection
    {
        $normalized = $this->titleExtractor->normalizeInvoiceNumber($invoiceNumber);

        if ($this->cachesWarmed) {
            return collect($this->ordersByInvoice[$normalized] ?? [])->take(10);
        }

        return FormOrder::query()
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->where('invoice_number', '!=', '0')
            ->whereRaw("REPLACE(invoice_number, ' ', '') = ?", [$normalized])
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, DebtCase>
     */
    private function findDebtCasesByInvoiceNumber(string $invoiceNumber): Collection
    {
        $normalized = $this->titleExtractor->normalizeInvoiceNumber($invoiceNumber);

        if ($this->cachesWarmed) {
            return collect($this->casesByInvoice[$normalized] ?? [])->take(10);
        }

        return DebtCase::query()
            ->whereNotNull('invoice_number')
            ->whereRaw("REPLACE(invoice_number, ' ', '') = ?", [$normalized])
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, FormOrder>
     */
    private function findOrdersByKsef(string $ksef): Collection
    {
        $normalized = $this->titleExtractor->normalizeKsefNumber($ksef);
        if ($normalized === null) {
            return collect();
        }

        if ($this->cachesWarmed) {
            return collect($this->ordersByKsef[$normalized] ?? [])->take(5);
        }

        return FormOrder::query()
            ->whereNotNull('ksef_number')
            ->where('ksef_number', '!=', '')
            ->get()
            ->filter(fn (FormOrder $order) => $this->titleExtractor->normalizeKsefNumber($order->ksef_number) === $normalized)
            ->take(5)
            ->values();
    }

    private function findOrderById(int $orderId): ?FormOrder
    {
        if ($this->cachesWarmed) {
            return $this->ordersById[$orderId] ?? null;
        }

        return FormOrder::query()->find($orderId);
    }

    /**
     * @return Collection<int, FormOrder>
     */
    private function findOrdersByNip(string $nip): Collection
    {
        if ($this->cachesWarmed) {
            return collect($this->ordersByNip[$nip] ?? [])->take(15);
        }

        return FormOrder::query()
            ->where(function ($q) use ($nip) {
                $q->whereRaw("REPLACE(REPLACE(buyer_nip, '-', ''), ' ', '') = ?", [$nip])
                    ->orWhereRaw("REPLACE(REPLACE(recipient_nip, '-', ''), ' ', '') = ?", [$nip]);
            })
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->limit(15)
            ->get();
    }

    private function activeDebtCaseForOrder(FormOrder $order): ?DebtCase
    {
        if ($this->cachesWarmed) {
            return $this->activeCasesByOrderId[(int) $order->id] ?? null;
        }

        return DebtCase::query()
            ->where('form_order_id', $order->id)
            ->where('status', '!=', DebtCase::STATUS_CLOSED)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array{form_order_id:?int,debt_case_id:?int,confidence:string,match_reasons:list<string>,score:int}|null  $existing
     * @param  array{form_order_id:?int,debt_case_id:?int,confidence:string,match_reasons:list<string>,score:int}  $incoming
     * @return array{form_order_id:?int,debt_case_id:?int,confidence:string,match_reasons:list<string>,score:int}
     */
    private function mergeCandidate(?array $existing, array $incoming): array
    {
        if ($existing === null) {
            return $incoming;
        }

        $winner = $incoming['score'] >= $existing['score'] ? $incoming : $existing;
        $loser = $incoming['score'] >= $existing['score'] ? $existing : $incoming;

        $winner['match_reasons'] = array_values(array_unique(array_merge($winner['match_reasons'], $loser['match_reasons'])));
        $winner['debt_case_id'] = $winner['debt_case_id'] ?? $loser['debt_case_id'];
        $winner['form_order_id'] = $winner['form_order_id'] ?? $loser['form_order_id'];

        $rank = [
            BankTransactionMatch::CONFIDENCE_HIGH => 3,
            BankTransactionMatch::CONFIDENCE_MEDIUM => 2,
            BankTransactionMatch::CONFIDENCE_LOW => 1,
        ];
        if (($rank[$loser['confidence']] ?? 0) > ($rank[$winner['confidence']] ?? 0)) {
            $winner['confidence'] = $loser['confidence'];
        }

        return $winner;
    }
}
