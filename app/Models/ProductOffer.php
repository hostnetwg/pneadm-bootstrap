<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductOffer extends Model
{
    use HasFactory, SoftDeletes;

    public const DEFAULT_CODE = 'default';

    public const CHANNEL_PNEDU = 'pnedu';

    protected $fillable = [
        'product_id',
        'code',
        'sales_channel',
        'is_active',
        'is_public',
        'allow_multiple_recipients',
        'allow_deferred_invoice',
        'allow_payu',
        'allow_paynow',
        'satisfaction_guarantee_days',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'allow_multiple_recipients' => 'boolean',
        'allow_deferred_invoice' => 'boolean',
        'allow_payu' => 'boolean',
        'allow_paynow' => 'boolean',
        'satisfaction_guarantee_days' => 'integer',
        'sort_order' => 'integer',
    ];

    public function satisfactionGuaranteeDays(): int
    {
        return max(0, (int) ($this->satisfaction_guarantee_days ?? 0));
    }

    public function hasSatisfactionGuarantee(): bool
    {
        return $this->satisfactionGuaranteeDays() > 0;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activePrices(): HasMany
    {
        return $this->prices()->where('is_active', true);
    }

    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereHas('product', fn (Builder $product) => $product->where('is_active', true));
    }

    public function hasEnabledPaymentMethod(): bool
    {
        return $this->allow_deferred_invoice || $this->allow_payu || $this->allow_paynow;
    }
}
