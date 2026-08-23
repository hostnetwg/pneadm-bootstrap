<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content_html',
        'status',
        'published_at',
        'author_id',
        'cover_image',
        'meta_title',
        'meta_description',
        'comments_enabled',
        'internal_notes',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'comments_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Article $article): void {
            static::query()->increment('sort_order');
            $article->sort_order = 0;
        });

        static::saving(function (Article $article): void {
            foreach (['title', 'excerpt', 'meta_title', 'meta_description'] as $field) {
                if ($article->{$field} !== null) {
                    $article->{$field} = static::normalizeNonBreakingSpaces((string) $article->{$field});
                }
            }

            if ($article->content_html !== null) {
                $article->content_html = static::normalizeHtmlNonBreakingSpaces((string) $article->content_html);
            }

            if (empty(trim((string) $article->slug))) {
                $article->slug = static::generateUniqueSlug((string) $article->title, $article->id);
            } else {
                $article->slug = static::generateUniqueSlug((string) $article->slug, $article->id);
            }

            if ($article->status === self::STATUS_PUBLISHED && $article->published_at === null) {
                $article->published_at = now();
            }
        });
    }

    /**
     * Przy zapisie encje → znak U+00A0. W formularzu edycji pokazujemy z powrotem &nbsp;.
     */
    public function editorText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $nbsp = html_entity_decode('&nbsp;', ENT_HTML5, 'UTF-8');

        return str_replace($nbsp, '&nbsp;', $value);
    }

    /**
     * Przy zapisie encje → znak U+00A0.
     */
    public static function normalizeNonBreakingSpaces(string $value): string
    {
        $nbsp = html_entity_decode('&nbsp;', ENT_HTML5, 'UTF-8');

        for ($i = 0; $i < 2; $i++) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return str_replace(
            ['&nbsp;', '&#160;', '&#xA0;', '&#x00A0;'],
            $nbsp,
            $value
        );
    }

    /**
     * W HTML zostawia poprawne &nbsp; (nie zamienia na zwykłą spację).
     */
    public static function normalizeHtmlNonBreakingSpaces(string $html): string
    {
        $html = str_replace('&amp;nbsp;', '&nbsp;', $html);

        return preg_replace('/&#x0*A0;|&#160;/i', '&nbsp;', $html) ?? $html;
    }

    public function plainText(?string $value = null): string
    {
        $plain = trim(strip_tags((string) ($value ?? '')));

        return static::normalizeNonBreakingSpaces($plain);
    }

    public function plainTitle(string $fallback = ''): string
    {
        $plain = $this->plainText($this->title);

        return $plain !== '' ? $plain : $fallback;
    }

    public function plainExcerpt(): string
    {
        if (filled($this->excerpt)) {
            return $this->plainText($this->excerpt);
        }

        return Str::limit($this->plainText($this->content_html), 320);
    }

    public static function generateUniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'artykul';
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

    /**
     * Nazwa pliku grafiki: slug SEO + krótki unikalny sufiks (nowy URL przy każdej wymianie obrazu).
     */
    public static function seoCoverImageFilename(Article $article, string $extension): string
    {
        $base = Str::slug($article->slug ?: $article->title ?: 'artykul');
        if ($base === '') {
            $base = 'artykul';
        }

        $base = Str::substr($base, 0, 80);
        $suffix = Str::lower(Str::random(6));
        $extension = strtolower(ltrim($extension, '.'));

        return "{$base}-{$suffix}.{$extension}";
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'Opublikowany',
            default => 'Szkic',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'bg-success',
            default => 'bg-secondary',
        };
    }

    public function publicImageUrl(): ?string
    {
        if (empty($this->cover_image)) {
            return null;
        }

        return Storage::disk('public')->url($this->cover_image);
    }

    public function seoTitle(): string
    {
        $title = filled($this->meta_title)
            ? $this->plainText($this->meta_title)
            : $this->plainTitle();

        return $title;
    }

    public function seoDescription(): string
    {
        if (filled($this->meta_description)) {
            return $this->plainText($this->meta_description);
        }

        if (filled($this->excerpt)) {
            return $this->plainText($this->excerpt);
        }

        return Str::limit($this->plainText($this->content_html), 160);
    }
}
