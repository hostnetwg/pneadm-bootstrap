<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrainingOffer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const PRICE_MODE_INDIVIDUAL = 'individual';

    public const PRICE_MODE_FIXED = 'fixed';

    public const COURSE_CATEGORY_OPEN = 'open';

    public const COURSE_CATEGORY_CLOSED = 'closed';

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description_html',
        'scope',
        'audience',
        'price_mode',
        'price_amount',
        'instructor_id',
        'image',
        'default_course_category',
        'is_active',
        'show_on_pnedu',
        'sort_order',
        'meta_title',
        'meta_description',
        'internal_notes',
    ];

    protected $casts = [
        'price_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'show_on_pnedu' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (TrainingOffer $offer) {
            if (empty(trim((string) $offer->slug))) {
                $offer->slug = static::generateUniqueSlug((string) $offer->title, $offer->id);
            } else {
                $offer->slug = static::generateUniqueSlug((string) $offer->slug, $offer->id);
            }
        });
    }

    public static function generateUniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'oferta-szkolenia';
        }

        $slug = $base;
        $i = 0;

        while (static::withTrashed()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'training_offer_id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('show_on_pnedu', true);
    }

    public function priceModeLabel(): string
    {
        return match ($this->price_mode) {
            self::PRICE_MODE_FIXED => 'Cena konkretna',
            default => 'Cena ustalana indywidualnie',
        };
    }

    public function formattedPrice(): string
    {
        if ($this->price_mode !== self::PRICE_MODE_FIXED || $this->price_amount === null) {
            return 'Cena ustalana indywidualnie';
        }

        return number_format((float) $this->price_amount, 2, ',', ' ').' PLN brutto';
    }

    public function defaultCourseCategoryLabel(): string
    {
        return match ($this->default_course_category) {
            self::COURSE_CATEGORY_OPEN => 'Otwarte',
            default => 'Zamknięte',
        };
    }

    public function publicImageUrl(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }
}
