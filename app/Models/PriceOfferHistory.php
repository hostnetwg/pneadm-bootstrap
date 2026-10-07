<?php

namespace App\Models;

use App\Casts\UtcImmutableDatetime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceOfferHistory extends Model
{
    public const SUBJECT_PRODUCT_PRICE = 'product_price';

    public const SUBJECT_COURSE_VARIANT = 'course_price_variant';

    public const KIND_REGULAR = 'regular';

    public const KIND_PROMOTIONAL = 'promotional';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'offered_price',
        'kind',
        'effective_from',
        'effective_to',
        'excluded_from_omnibus',
        'excluded_reason',
        'excluded_by',
        'excluded_at',
    ];

    protected $casts = [
        'offered_price' => 'decimal:2',
        'effective_from' => UtcImmutableDatetime::class,
        'effective_to' => UtcImmutableDatetime::class,
        'excluded_from_omnibus' => 'boolean',
        'excluded_at' => UtcImmutableDatetime::class,
    ];

    public function excludedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excluded_by');
    }

    public function isOpen(): bool
    {
        return $this->effective_to === null;
    }
}
