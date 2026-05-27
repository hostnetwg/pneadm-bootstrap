<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstructorInvoice extends Model
{
    public const SETTLEMENT_TYPE_INVOICE = 'invoice';

    public const SETTLEMENT_TYPE_MANDATE = 'mandate';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const PAYMENT_STATUS_PAID = 'paid';

    protected $fillable = [
        'instructor_id',
        'settlement_type',
        'invoice_number',
        'ksef_number',
        'invoice_date',
        'payment_status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InstructorInvoiceItem::class, 'instructor_invoice_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    public function isMandate(): bool
    {
        return $this->settlement_type === self::SETTLEMENT_TYPE_MANDATE;
    }

    public function isInvoice(): bool
    {
        return $this->settlement_type === self::SETTLEMENT_TYPE_INVOICE;
    }

    public function settlementTypeLabel(): string
    {
        return match ($this->settlement_type) {
            self::SETTLEMENT_TYPE_MANDATE => 'Umowa zlecenie',
            default => 'Faktura',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function settlementTypeOptions(): array
    {
        return [
            self::SETTLEMENT_TYPE_INVOICE => 'Faktura',
            self::SETTLEMENT_TYPE_MANDATE => 'Umowa zlecenie',
        ];
    }

    public function totalItemsAmount(): float
    {
        return (float) $this->items()->sum('amount_gross');
    }
}
