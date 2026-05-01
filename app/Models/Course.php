<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'offer_description_html',
        'offer_summary',
        'start_date',
        'end_date',
        'issue_date_certyficates',
        'is_paid', // 1/0
        'type', // online/offline
        'category', // open/closed
        'instructor_id',
        'image',
        'is_active',
        'certificate_format',
        'certificate_template_id',
        'certificate_download_status',
        'certificate_registration_open',
        'certificate_registration_starts_at',
        'certificate_registration_ends_at',
        'certificate_registration_token',
        'access_duration_days',
        'access_notes',
        'notatki',
        'id_old',
        'source_id_old',
        'show_on_pnedu',
    ];

    protected $casts = [
        'is_paid' => 'boolean', // ✅ Konwersja na boolean dla poprawnego odczytu
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'issue_date_certyficates' => 'date',
        'is_active' => 'boolean',
        'show_on_pnedu' => 'boolean',
        'certificate_registration_open' => 'boolean',
        'certificate_registration_starts_at' => 'datetime',
        'certificate_registration_ends_at' => 'datetime',
    ];

    /**
     * Relacja 1:1 – kurs ma jedną lokalizację (jeśli stacjonarny)
     */
    public function location()
    {
        return $this->hasOne(CourseLocation::class, 'course_id');
    }

    /**
     * Relacja 1:1 – kurs ma szczegóły dostępowe (jeśli online)
     */
    public function onlineDetails()
    {
        return $this->hasOne(CourseOnlineDetails::class, 'course_id');
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    /**
     * Relacja Many-to-Many - kurs może należeć do wielu serii
     * Sortowane po order_in_series w tabeli pivot
     */
    public function series()
    {
        return $this->belongsToMany(CourseSeries::class, 'course_series_course', 'course_id', 'course_series_id')
            ->withPivot('order_in_series')
            ->withTimestamps()
            ->orderByPivot('order_in_series');
    }

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Relacja do ankiet
     */
    public function surveys()
    {
        return $this->hasMany(Survey::class);
    }

    /**
     * Relacja do szablonu certyfikatu
     */
    public function certificateTemplate()
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    /**
     * Cykl życia kursu na potrzeby UI (dropdown wyboru szkolenia w form-orders).
     * Zwraca jedną z wartości:
     *  - upcoming  → start_date > now
     *  - ongoing   → start_date <= now AND end_date is not null AND end_date >= now
     *  - archived  → end_date < now LUB (end_date is null AND start_date < now)
     *  - unknown   → brak start_date
     */
    public function getLifecycleStatus(?\DateTimeInterface $now = null): string
    {
        if (! $this->start_date) {
            return 'unknown';
        }

        $now = $now ? \Carbon\Carbon::instance($now) : now();
        $start = \Carbon\Carbon::parse($this->start_date);
        $end = $this->end_date ? \Carbon\Carbon::parse($this->end_date) : null;

        if ($start->greaterThan($now)) {
            return 'upcoming';
        }

        if ($end && $end->greaterThanOrEqualTo($now)) {
            return 'ongoing';
        }

        return 'archived';
    }

    /**
     * Relacja do wariantów cenowych
     */
    public function priceVariants()
    {
        return $this->hasMany(CoursePriceVariant::class);
    }

    /**
     * Relacja do aktywnych wariantów cenowych
     */
    public function activePriceVariants()
    {
        return $this->hasMany(CoursePriceVariant::class)->where('is_active', true);
    }

    /**
     * Relacja do nagrań wideo
     */
    public function videos()
    {
        return $this->hasMany(CourseVideo::class)->orderBy('order');
    }

    /**
     * Linki do materiałów (np. udostępnione pliki na Dysku Google).
     */
    public function fileLinks()
    {
        return $this->hasMany(CourseFileLink::class)->orderBy('order');
    }

    /**
     * Linki do zewnętrznych ankiet (np. Google Forms, Microsoft Forms, Typeform).
     */
    public function surveyLinks()
    {
        return $this->hasMany(CourseSurveyLink::class)->orderBy('order');
    }
}
