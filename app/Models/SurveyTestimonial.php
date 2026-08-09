<?php

namespace App\Models;

use App\Support\SurveyAvatarPresets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyTestimonial extends Model
{
    protected $fillable = [
        'survey_id',
        'survey_response_id',
        'course_id',
        'author_name',
        'author_role',
        'author_city',
        'avatar_type',
        'avatar_preset',
        'avatar_path',
        'quote',
        'rating',
        'publish_consent',
        'is_published',
        'published_at',
        'display_order',
    ];

    protected $casts = [
        'rating' => 'integer',
        'publish_consent' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'display_order' => 'integer',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where('publish_consent', true)
            ->orderBy('display_order')
            ->orderByDesc('published_at');
    }

    public function subtitle(): string
    {
        return collect([$this->author_role, $this->author_city])
            ->filter(fn ($v) => filled($v))
            ->implode(', ');
    }

    public function hasAvatar(): bool
    {
        if ($this->avatar_type === 'none') {
            return false;
        }

        if ($this->avatar_type === 'upload') {
            return filled($this->avatar_path);
        }

        return SurveyAvatarPresets::migrateLegacyKey($this->avatar_preset) !== null;
    }

    public function avatarUrl(): ?string
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        $base = rtrim((string) config('services.pnedu_frontend_url', 'https://pnedu.pl'), '/');

        if ($this->avatar_type === 'upload' && filled($this->avatar_path)) {
            return $base.'/'.ltrim((string) $this->avatar_path, '/');
        }

        $key = SurveyAvatarPresets::migrateLegacyKey($this->avatar_preset);

        return $key ? SurveyAvatarPresets::url($key) : null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->author_name)) ?: [];
        $letters = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : '?';
    }

    public function publish(): void
    {
        $this->update([
            'is_published' => true,
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    public function unpublish(): void
    {
        $this->update([
            'is_published' => false,
        ]);
    }
}
