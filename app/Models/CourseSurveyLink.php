<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CourseSurveyLink extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (CourseSurveyLink $link) {
            if (blank($link->public_token)) {
                $link->public_token = static::generateUniquePublicToken();
            }
        });
    }

    /**
     * Jednorazowy publiczny fragment URL dla bramki ankiet na pnedu.pl.
     */
    public static function generateUniquePublicToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (static::where('public_token', $token)->exists());

        return $token;
    }

    /**
     * Adres przez bramkę {@see https://pnedu.pl/ankieta/{token}} gdy ustawione {@see PNEDU_FRONTEND_URL}; w przeciwnym razie surowy URL ankiety.
     */
    public function participantFacingSurveyUrl(): string
    {
        $raw = trim((string) ($this->url ?? ''));
        $base = rtrim((string) config('services.pnedu_frontend_url', ''), '/');
        $tok = trim((string) ($this->public_token ?? ''));

        if ($base !== '' && $tok !== '') {
            return $base.'/ankieta/'.$tok;
        }

        return $raw;
    }

    protected $fillable = [
        'course_id',
        'url',
        'title',
        'provider',
        'is_active',
        'opens_at',
        'closes_at',
        'public_token',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'order' => 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Auto-detekcja dostawcy ankiety na podstawie URL.
     */
    public static function detectProvider(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $u = strtolower($url);

        if (str_contains($u, 'docs.google.com/forms') || str_contains($u, 'forms.gle')) {
            return 'google_forms';
        }

        if (str_contains($u, 'forms.office.com') || str_contains($u, 'forms.microsoft.com')) {
            return 'microsoft_forms';
        }

        if (str_contains($u, 'typeform.com')) {
            return 'typeform';
        }

        if (str_contains($u, 'surveymonkey.com')) {
            return 'survey_monkey';
        }

        return 'other';
    }

    /**
     * Aktywne ankiety w bieżącym oknie czasowym (daty z panelu adm = Europe/Warsaw).
     */
    public function scopeAvailableNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('opens_at')->orWhere('opens_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('closes_at')->orWhere('closes_at', '>', $now);
            });
    }

    /**
     * Czy ankieta jest aktualnie dostępna (aktywna i w oknie czasowym)?
     */
    public function isAvailableNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->opens_at && $now->lt($this->opens_at)) {
            return false;
        }

        // Zamknięcie o HH:MM = od tej chwili niedostępna (np. zamknięcie 15:35, wysyłka 15:40 → bez ankiety w mailu).
        if ($this->closes_at && $now->gte($this->closes_at)) {
            return false;
        }

        return true;
    }

    /**
     * datetime-local z panelu adm (bez strefy) traktujemy jako czas warszawski.
     */
    public static function parseAdminDatetimeLocal(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value, 'Europe/Warsaw');
    }

    /**
     * Etykieta dostawcy do wyświetlenia w UI.
     */
    public function providerLabel(): string
    {
        return match ($this->provider) {
            'google_forms' => 'Google Forms',
            'microsoft_forms' => 'Microsoft Forms',
            'typeform' => 'Typeform',
            'survey_monkey' => 'SurveyMonkey',
            default => 'Inny',
        };
    }

    /**
     * Klasa ikony Bootstrap Icons / Font Awesome dla dostawcy.
     */
    public function providerIconClass(): string
    {
        return match ($this->provider) {
            'google_forms' => 'fab fa-google text-danger',
            'microsoft_forms' => 'fab fa-microsoft text-primary',
            'typeform' => 'fas fa-poll text-dark',
            'survey_monkey' => 'fas fa-poll-h text-success',
            default => 'fas fa-clipboard-list text-secondary',
        };
    }
}
