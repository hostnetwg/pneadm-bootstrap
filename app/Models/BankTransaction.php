<?php

namespace App\Models;

use App\Services\Bank\BankTransactionMatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Wpływy z wolną kwotą (nie zignorowane; bez akceptacji lub suma alokacji < kwota przelewu).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\BankTransaction>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\BankTransaction>
     */
    public function scopeWithRemainingAllocatable($query)
    {
        return $query
            ->whereDoesntHave('matches', function ($matchQuery) {
                $matchQuery->where('status', BankTransactionMatch::STATUS_IGNORED);
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
}
