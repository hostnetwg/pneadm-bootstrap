<?php

namespace App\Models;

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
}
