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
        'utm_medium',
        'utm_content',
        'course_id',
        'landing_target',
        'order_form_variant',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'course_id' => 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

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

    public function statsDaily()
    {
        return $this->hasMany(MarketingCampaignStatsDaily::class, 'campaign_code', 'campaign_code');
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
