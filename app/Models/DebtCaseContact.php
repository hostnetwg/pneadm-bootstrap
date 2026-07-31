<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtCaseContact extends Model
{
    use HasFactory;

    public const TYPE_EMAIL = 'email';

    public const TYPE_PHONE = 'phone';

    public const TYPE_WEBSITE = 'website';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'debt_case_id',
        'created_by',
        'contact_type',
        'value',
        'label',
        'source',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function debtCase(): BelongsTo
    {
        return $this->belongsTo(DebtCase::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->contact_type] ?? (string) $this->contact_type;
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_EMAIL => 'E-mail',
            self::TYPE_PHONE => 'Telefon',
            self::TYPE_WEBSITE => 'WWW',
            self::TYPE_OTHER => 'Inne',
        ];
    }
}
