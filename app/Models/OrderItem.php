<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'form_order_id',
        'product_id',
        'product_offer_id',
        'product_price_id',
        'product_type',
        'product_name',
        'product_sku',
        'fulfillment_type',
        'requires_shipping',
        'unit_price',
        'quantity',
        'line_total',
        'currency',
        'tax_treatment',
        'tax_rate',
        'tax_exemption_basis',
        'access_policy',
        'access_starts_at',
        'access_note',
        'satisfaction_guarantee_days',
        'is_extension',
        'previous_access_expires_at',
        'access_duration_value',
        'access_duration_unit',
        'access_expires_at',
        'metadata',
        'commercial_terms',
    ];

    protected $casts = [
        'requires_shipping' => 'boolean',
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'line_total' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'access_starts_at' => 'immutable_datetime',
        'access_duration_value' => 'integer',
        'access_expires_at' => 'immutable_datetime',
        'satisfaction_guarantee_days' => 'integer',
        'is_extension' => 'boolean',
        'previous_access_expires_at' => 'immutable_datetime',
        'metadata' => 'array',
        'commercial_terms' => 'array',
    ];

    public function formOrder(): BelongsTo
    {
        return $this->belongsTo(FormOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class, 'product_price_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(OrderItemRecipient::class);
    }
}
