<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SurveyTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (SurveyTemplate $template) {
            if (blank($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });

        static::saving(function (SurveyTemplate $template) {
            if ($template->is_default) {
                static::query()
                    ->where('id', '!=', $template->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyTemplateQuestion::class)->orderBy('question_order');
    }

    public static function defaultActive(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->with('questions')
            ->first()
            ?? static::query()
                ->where('is_active', true)
                ->with('questions')
                ->orderBy('id')
                ->first();
    }
}
