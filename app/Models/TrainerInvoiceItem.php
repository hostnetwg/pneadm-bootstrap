<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerInvoiceItem extends Model
{
    protected $fillable = [
        'trainer_invoice_id',
        'course_id',
        'amount_gross',
        'amount_net',
        'notes',
    ];

    protected $casts = [
        'amount_gross' => 'decimal:2',
        'amount_net' => 'decimal:2',
    ];

    public function trainerInvoice(): BelongsTo
    {
        return $this->belongsTo(TrainerInvoice::class, 'trainer_invoice_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
