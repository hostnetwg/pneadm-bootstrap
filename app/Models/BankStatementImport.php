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
}
