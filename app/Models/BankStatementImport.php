<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    public const STATUS_PARSED = 'parsed';

    public const STATUS_REVIEWED = 'reviewed';

    public const SOURCE_MBANK = 'mbank';

    protected $fillable = [
        'uploaded_by',
        'original_filename',
        'stored_path',
        'file_hash',
        'source',
        'status',
        'period_from',
        'period_to',
        'rows_total',
        'rows_incoming',
        'rows_matched',
        'rows_duplicate',
        'notes',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PARSED => 'Sparsowany',
            self::STATUS_REVIEWED => 'Przejrzany',
            default => (string) $this->status,
        };
    }

    /**
     * Liczba wpływów bez decyzji operatora (brak accepted/ignored).
     * Wymaga withCount(`pending_review_count`) albo osobnego zapytania.
     */
    public function pendingReviewCount(): int
    {
        if (isset($this->attributes['pending_review_count']) || isset($this->pending_review_count)) {
            return (int) $this->pending_review_count;
        }

        return (int) $this->transactions()
            ->where('is_incoming', true)
            ->whereDoesntHave('matches', fn ($q) => $q->whereIn('status', [
                BankTransactionMatch::STATUS_ACCEPTED,
                BankTransactionMatch::STATUS_IGNORED,
            ]))
            ->count();
    }

    public function isFullyReviewed(): bool
    {
        if ((int) $this->rows_incoming === 0) {
            return true;
        }

        return $this->pendingReviewCount() === 0;
    }

    /**
     * Brak zaakceptowanych powiązań na transakcjach tego importu (można bezpiecznie usunąć).
     */
    public function canBeDeleted(): bool
    {
        if (! $this->transactions()->exists()) {
            return true;
        }

        return ! $this->transactions()
            ->whereHas('matches', fn ($q) => $q->where(
                'status',
                BankTransactionMatch::STATUS_ACCEPTED
            ))
            ->exists();
    }

    public function reviewProgressLabel(): string
    {
        if ((int) $this->rows_incoming === 0) {
            return 'Brak wpływów';
        }

        $pending = $this->pendingReviewCount();
        if ($pending === 0) {
            return 'Przejrzany';
        }

        return 'Do przeglądu: '.$pending;
    }
}
