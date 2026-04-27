<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSurveyLink extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'course_id',
        'url',
        'title',
        'provider',
        'is_active',
        'opens_at',
        'closes_at',
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

        if ($this->closes_at && $now->gt($this->closes_at)) {
            return false;
        }

        return true;
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
