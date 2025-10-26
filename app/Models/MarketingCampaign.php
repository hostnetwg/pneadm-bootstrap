<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campaign_code',
        'name',
        'description',
        'source_type_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope - tylko aktywne kampanie
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Relacja do typu źródła
     */
    public function sourceType()
    {
        return $this->belongsTo(MarketingSourceType::class);
    }

    /**
     * Relacja do zamówień formularza
     */
    public function formOrders()
    {
        return $this->hasMany(FormOrder::class, 'fb_source', 'campaign_code');
    }

    /**
     * Accessor - nazwa typu źródła
     */
    public function getSourceTypeNameAttribute()
    {
        return $this->sourceType?->name ?? 'Nieznany';
    }

    /**
     * Accessor - slug typu źródła
     */
    public function getSourceTypeSlugAttribute()
    {
        return $this->sourceType?->slug ?? 'unknown';
    }
}
