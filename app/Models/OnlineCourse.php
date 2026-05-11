<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OnlineCourse extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'offer_description_html',
        'instructor_id',
        'image',
        'is_active',
        'visible_in_dashboard',
        'internal_notes',
        'legacy_publigo_product_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'visible_in_dashboard' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (OnlineCourse $course) {
            if (empty(trim((string) $course->slug))) {
                $course->slug = static::generateUniqueSlug((string) $course->title);
            }
        });
    }

    public static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'kurs';
        }
        $slug = $base;
        $i = 0;
        while (static::query()->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(OnlineCourseModule::class)->orderBy('sort_order')->orderBy('id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(OnlineCourseEnrollment::class);
    }

    /** Moduły z lekcjami (widok nauki po stronie pnedu). */
    public function modulesWithPublishedLessons(): HasMany
    {
        return $this->modules()
            ->with(['lessons' => fn ($q) => $q->where('is_published', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with(['embeds', 'resourceLinks']),
            ])
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
