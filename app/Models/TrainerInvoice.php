<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainerInvoice extends Model
{
    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const PAYMENT_STATUS_PAID = 'paid';

    protected $fillable = [
        'instructor_id',
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
        return $this->hasMany(TrainerInvoiceItem::class, 'trainer_invoice_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    public function totalItemsAmount(): float
    {
        return (float) $this->items()->sum('amount_gross');
    }
}
