<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_ONLINE_COURSE = 'online_course';

    public const FULFILLMENT_ONLINE_COURSE_ACCESS = 'online_course_access';

    protected $fillable = [
        'type',
        'resource_id',
        'name',
        'slug',
        'sku',
        'fulfillment_type',
        'is_active',
        'requires_shipping',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'resource_id' => 'integer',
        'is_active' => 'boolean',
        'requires_shipping' => 'boolean',
    ];

    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }

    public function defaultOffer(): HasOne
    {
        return $this->hasOne(ProductOffer::class)
            ->where('code', ProductOffer::DEFAULT_CODE)
            ->where('sales_channel', ProductOffer::CHANNEL_PNEDU);
    }
}
