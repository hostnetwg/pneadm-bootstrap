<?php

namespace App\Models;

use App\Services\Bank\BankTransactionMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_statement_import_id',
        'operation_date',
        'amount',
        'currency',
        'description',
        'account_label',
        'category',
        'counterparty_account',
        'fingerprint',
        'is_incoming',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'amount' => 'decimal:2',
        'is_incoming' => 'boolean',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankTransactionMatch::class);
    }

    public function bestSuggestedMatch(): ?BankTransactionMatch
    {
        return $this->matches
            ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
            ->sortBy(fn (BankTransactionMatch $match) => match ($match->confidence) {
                BankTransactionMatch::CONFIDENCE_HIGH => 0,
                BankTransactionMatch::CONFIDENCE_MEDIUM => 1,
                default => 2,
            })
            ->first();
    }

    public function acceptedMatch(): ?BankTransactionMatch
    {
        return $this->matches->firstWhere('status', BankTransactionMatch::STATUS_ACCEPTED);
    }

    /**
     * @return \Illuminate\Support\Collection<int, BankTransactionMatch>
     */
    public function acceptedMatches()
    {
        return $this->matches->where('status', BankTransactionMatch::STATUS_ACCEPTED)->values();
    }

    public function isIgnored(): bool
    {
        return $this->matches->contains(
            fn (BankTransactionMatch $match) => $match->status === BankTransactionMatch::STATUS_IGNORED
        );
    }

    public function isDeferred(): bool
    {
        return $this->matches->contains(
            fn (BankTransactionMatch $match) => $match->status === BankTransactionMatch::STATUS_DEFERRED
        );
    }

    public function allocatedAcceptedSum(): float
    {
        return round((float) $this->acceptedMatches()->sum(function (BankTransactionMatch $match) {
            if ($match->allocated_amount !== null) {
                return (float) $match->allocated_amount;
            }

            return (float) $this->amount;
        }), 2);
    }

    public function remainingAllocatableAmount(): float
    {
        if ($this->isIgnored()) {
            return 0.0;
        }

        return round(max(0, (float) $this->amount - $this->allocatedAcceptedSum()), 2);
    }

    public function isFullyAllocated(): bool
    {
        return $this->remainingAllocatableAmount() <= BankTransactionMatcher::AMOUNT_EPSILON;
    }

    public function canAcceptAdditionalLink(): bool
    {
        return $this->is_incoming
            && ! $this->isIgnored()
            && $this->remainingAllocatableAmount() > BankTransactionMatcher::AMOUNT_EPSILON;
    }

    /**
     * Wpływy z wolną kwotą (nie zignorowane / nie „na potem”;
     * bez akceptacji lub suma alokacji < kwota przelewu).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\BankTransaction>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\BankTransaction>
     */
    public function scopeWithRemainingAllocatable($query)
    {
        return $query
            ->whereDoesntHave('matches', function ($matchQuery) {
                $matchQuery->whereIn('status', [
                    BankTransactionMatch::STATUS_IGNORED,
                    BankTransactionMatch::STATUS_DEFERRED,
                ]);
            })
            ->where(function ($inner) {
                $inner->whereDoesntHave('matches', function ($matchQuery) {
                    $matchQuery->where('status', BankTransactionMatch::STATUS_ACCEPTED);
                })->orWhereRaw(
                    '(
                        SELECT COALESCE(SUM(COALESCE(allocated_amount, bank_transactions.amount)), 0)
                        FROM bank_transaction_matches
                        WHERE bank_transaction_matches.bank_transaction_id = bank_transactions.id
                          AND bank_transaction_matches.status = ?
                    ) < bank_transactions.amount - 0.009',
                    [BankTransactionMatch::STATUS_ACCEPTED]
                );
            });
    }

    /**
     * Wpływy czekające na decyzję operatora: aktywna kolejka + odroczone „Na potem”.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\BankTransaction>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\BankTransaction>
     */
    public function scopePendingOperatorReview($query)
    {
        return $query
            ->where('is_incoming', true)
            ->where(function ($outer) {
                $outer->where(function ($active) {
                    $active->withRemainingAllocatable()
                        ->where(function ($inner) {
                            $inner->whereDoesntHave('matches')
                                ->orWhereHas('matches', fn ($m) => $m->where(
                                    'status',
                                    BankTransactionMatch::STATUS_SUGGESTED
                                ));
                        });
                })->orWhereHas('matches', fn ($m) => $m->where(
                    'status',
                    BankTransactionMatch::STATUS_DEFERRED
                ));
            });
    }

    /**
     * Szukaj wpływu po opisie/nadawcy, kwocie, dacie, ID oraz powiązanej FV/sprawie.
     *
     * @param  Builder<BankTransaction>  $query
     * @return Builder<BankTransaction>
     */
    public function scopeMatchingSearch(Builder $query, ?string $raw): Builder
    {
        $search = trim((string) $raw);
        if ($search === '') {
            return $query;
        }

        $like = '%'.addcslashes($search, '%_\\').'%';
        $amount = self::parseSearchAmount($search);
        $date = self::parseSearchDate($search);
        $idCandidate = 0;
        if (ctype_digit($search)) {
            $idCandidate = (int) $search;
        } elseif (preg_match('/^#(\d+)$/', $search, $matches) === 1) {
            $idCandidate = (int) $matches[1];
        }

        return $query->where(function (Builder $inner) use ($like, $amount, $date, $idCandidate) {
            $inner->where('description', 'like', $like)
                ->orWhere('account_label', 'like', $like)
                ->orWhere('category', 'like', $like)
                ->orWhere('counterparty_account', 'like', $like);

            if ($idCandidate > 0) {
                $inner->orWhere('id', $idCandidate);
            }

            if ($amount !== null) {
                $inner->orWhereBetween('amount', [$amount - 0.01, $amount + 0.01]);
            }

            if ($date !== null) {
                $inner->orWhereDate('operation_date', $date);
            }

            $inner->orWhereHas('matches', function (Builder $matchQuery) use ($like, $idCandidate) {
                $matchQuery->where(function (Builder $matchInner) use ($like, $idCandidate) {
                    if ($idCandidate > 0) {
                        $matchInner->where('form_order_id', $idCandidate)
                            ->orWhere('debt_case_id', $idCandidate);
                    }

                    $matchInner->orWhereHas('formOrder', function (Builder $orderQuery) use ($like, $idCandidate) {
                        $orderQuery->where(function (Builder $orderInner) use ($like, $idCandidate) {
                            $orderInner->where('invoice_number', 'like', $like)
                                ->orWhere('ksef_number', 'like', $like)
                                ->orWhere('buyer_name', 'like', $like)
                                ->orWhere('recipient_name', 'like', $like)
                                ->orWhere('orderer_name', 'like', $like)
                                ->orWhere('orderer_email', 'like', $like)
                                ->orWhere('buyer_address', 'like', $like)
                                ->orWhere('buyer_city', 'like', $like)
                                ->orWhere('buyer_postal_code', 'like', $like)
                                ->orWhere('recipient_address', 'like', $like)
                                ->orWhere('recipient_city', 'like', $like)
                                ->orWhere('recipient_postal_code', 'like', $like);
                            if ($idCandidate > 0) {
                                $orderInner->orWhere('id', $idCandidate);
                            }
                        });
                    })->orWhereHas('debtCase', function (Builder $caseQuery) use ($like, $idCandidate) {
                        $caseQuery->where(function (Builder $caseInner) use ($like, $idCandidate) {
                            $caseInner->where('invoice_number', 'like', $like)
                                ->orWhere('ksef_number', 'like', $like);
                            if ($idCandidate > 0) {
                                $caseInner->orWhere('id', $idCandidate);
                            }
                        });
                    });
                });
            });
        });
    }

    private static function parseSearchAmount(string $search): ?float
    {
        $normalized = str_replace(["\u{00A0}", ' '], '', trim($search));
        $normalized = preg_replace('/pln$/i', '', $normalized) ?? $normalized;
        $normalized = str_replace(',', '.', $normalized);
        if (preg_match('/^\d+(\.\d{1,2})?$/', $normalized) !== 1) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private static function parseSearchDate(string $search): ?string
    {
        $trimmed = trim($search);
        foreach (['Y-m-d', 'd.m.Y', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $trimmed);
            } catch (\Throwable) {
                continue;
            }
            if ($parsed && $parsed->format($format) === $trimmed) {
                return $parsed->toDateString();
            }
        }

        return null;
    }
}
