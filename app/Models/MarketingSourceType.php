<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MarketingSourceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope - tylko aktywne typy źródeł
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope - sortowanie według kolejności
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Relacja do kampanii marketingowych
     */
    public function marketingCampaigns()
    {
        return $this->hasMany(MarketingCampaign::class, 'source_type_id');
    }

    /**
     * Accessor - kolor z fallback
     */
    public function getColorAttribute($value)
    {
        return $value ?: '#6c757d';
    }

    /**
     * Mutator - slug z name
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = \Str::slug($value);
        }
    }
}
