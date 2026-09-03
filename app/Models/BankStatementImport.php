<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
     * Liczba wpływów bez finalnej decyzji operatora (aktywna kolejka + „Na potem”).
     * Wymaga withCount(`pending_review_count`) albo osobnego zapytania.
     */
    public function pendingReviewCount(): int
    {
        if (isset($this->attributes['pending_review_count']) || isset($this->pending_review_count)) {
            return (int) $this->pending_review_count;
        }

        return (int) $this->transactions()->pendingOperatorReview()->count();
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

    /**
     * Szukaj importu po ID, nazwie pliku, okresie, dacie wgrania lub uploaderze.
     *
     * @param  Builder<BankStatementImport>  $query
     * @return Builder<BankStatementImport>
     */
    public function scopeMatchingSearch(Builder $query, ?string $raw): Builder
    {
        $search = trim((string) $raw);
        if ($search === '') {
            return $query;
        }

        $like = '%'.addcslashes($search, '%_\\').'%';
        $parsedDate = self::parseSearchDate($search);
        $idCandidate = 0;
        if (ctype_digit($search)) {
            $idCandidate = (int) $search;
        } elseif (preg_match('/^#(\d+)$/', $search, $matches) === 1) {
            $idCandidate = (int) $matches[1];
        }

        return $query->where(function (Builder $inner) use ($like, $parsedDate, $idCandidate, $search) {
            $inner->where('original_filename', 'like', $like)
                ->orWhere('notes', 'like', $like);

            if ($idCandidate > 0) {
                $inner->orWhere('id', $idCandidate);
            }

            if ($parsedDate !== null) {
                $from = $parsedDate['from'];
                $to = $parsedDate['to'];
                $inner->orWhere(function (Builder $dates) use ($from, $to) {
                    $dates->whereDate('period_from', '>=', $from)->whereDate('period_from', '<=', $to);
                })->orWhere(function (Builder $dates) use ($from, $to) {
                    $dates->whereDate('period_to', '>=', $from)->whereDate('period_to', '<=', $to);
                })->orWhere(function (Builder $dates) use ($from, $to) {
                    $dates->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
                })->orWhere(function (Builder $period) use ($from, $to) {
                    // Import obejmuje szukaną datę / miesiąc (okres nachodzi).
                    $period->whereDate('period_from', '<=', $to)
                        ->whereDate('period_to', '>=', $from);
                });
            }

            $inner->orWhereHas('uploader', function (Builder $uploader) use ($like) {
                $uploader->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });

            // Przelewy ze wszystkich CSV: FV, opis, adres, kwota itd. (jak w podglądzie importu).
            $inner->orWhereHas('transactions', function (Builder $txQuery) use ($search) {
                $txQuery->where('is_incoming', true)
                    ->matchingSearch($search);
            });
        });
    }

    /**
     * @return array{from: string, to: string}|null
     */
    private static function parseSearchDate(string $search): ?array
    {
        $trimmed = trim($search);

        foreach (['Y-m', 'm.Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $trimmed);
            } catch (\Throwable) {
                continue;
            }
            if ($parsed && $parsed->format($format) === $trimmed) {
                return [
                    'from' => $parsed->copy()->startOfMonth()->toDateString(),
                    'to' => $parsed->copy()->endOfMonth()->toDateString(),
                ];
            }
        }

        foreach (['Y-m-d', 'd.m.Y', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $trimmed);
            } catch (\Throwable) {
                continue;
            }
            if ($parsed && $parsed->format($format) === $trimmed) {
                $day = $parsed->toDateString();

                return [
                    'from' => $day,
                    'to' => $day,
                ];
            }
        }

        return null;
    }
}
