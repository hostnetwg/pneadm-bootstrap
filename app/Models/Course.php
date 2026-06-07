<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const POST_END_RULE_DURATION = 'duration';

    public const POST_END_RULE_UNLIMITED = 'unlimited';

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
        'certificate_registration_collect_birth_data',
        'certificate_registration_birth_data_required',
        'next_participant_order',
        'access_duration_days',
        'access_notes',
        'post_end_access_duration_value',
        'post_end_access_duration_unit',
        'post_end_access_rule',
        'notatki',
        'id_old',
        'source_id_old',
        'show_on_pnedu',
        'sendy_suppression_list_id',
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
        'certificate_registration_collect_birth_data' => 'boolean',
        'certificate_registration_birth_data_required' => 'boolean',
        'next_participant_order' => 'integer',
        'post_end_access_duration_value' => 'integer',
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

    public function hasEnded(): bool
    {
        return $this->end_date !== null && $this->end_date->isPast();
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

    /**
     * Czy publiczny formularz rejestracji zaświadczenia jest włączony i w oknie czasowym od–do.
     */
    public function isCertificateRegistrationActiveNow(?\DateTimeInterface $now = null): bool
    {
        if (! $this->certificate_registration_open) {
            return false;
        }

        if (trim((string) ($this->certificate_registration_token ?? '')) === '') {
            return false;
        }

        $now = $now ? \Carbon\Carbon::instance($now) : now();

        if ($this->certificate_registration_starts_at && $now->lt($this->certificate_registration_starts_at)) {
            return false;
        }

        if ($this->certificate_registration_ends_at && $now->gt($this->certificate_registration_ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Pozycja faktury trenera przypisana do tego szkolenia (rozliczenie wypłaty).
     */
    public function instructorInvoiceItems()
    {
        return $this->hasMany(InstructorInvoiceItem::class, 'course_id');
    }

    public function instructorSettlementItem()
    {
        return $this->hasOne(InstructorInvoiceItem::class, 'course_id')->latestOfMany();
    }

    /**
     * Publiczny URL formularza rejestracji zaświadczenia na pnedu.pl (lub null).
     */
    public function certificateRegistrationPublicUrl(): ?string
    {
        $token = trim((string) ($this->certificate_registration_token ?? ''));
        if ($token === '') {
            return null;
        }

        $base = rtrim((string) config('services.pnedu_frontend_url', ''), '/');
        if ($base === '') {
            return null;
        }

        return $base.'/certificate-registration/'.$token;
    }

    /**
     * URL do dodania szkolenia w Google Calendar (link TEMPLATE — bez OAuth).
     */
    public function googleCalendarUrl(): ?string
    {
        if (! $this->start_date) {
            return null;
        }

        $start = $this->start_date->copy()->timezone('Europe/Warsaw');
        $end = $this->calendarEventEnd()->timezone('Europe/Warsaw');

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addHours(2);
        }

        $params = [
            'action' => 'TEMPLATE',
            'text' => $this->calendarEventTitle(),
            'dates' => $start->format('Ymd\THis').'/'.$end->format('Ymd\THis'),
            'details' => implode("\n", $this->calendarEventDetailLines()),
            'ctz' => 'Europe/Warsaw',
        ];

        $location = $this->calendarEventLocation();
        if ($location !== null) {
            $params['location'] = $location;
        }

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    protected function calendarEventTitle(): string
    {
        $title = $this->plainTitle() ?: 'Szkolenie';

        if ($this->instructor) {
            $instructorName = trim($this->instructor->getFullTitleNameAttribute());
            if ($instructorName !== '') {
                $title .= ' ['.$instructorName.']';
            }
        }

        return $title;
    }

    /**
     * @return list<string>
     */
    protected function calendarEventDetailLines(): array
    {
        $lines = [];

        if ($this->instructor) {
            $lines[] = 'Instruktor: '.$this->instructor->getFullTitleNameAttribute();
        }

        $lines[] = route('courses.show', $this->id);

        if ($this->type === 'online' && $this->onlineDetails) {
            $meetingLink = trim((string) ($this->onlineDetails->meeting_link ?? ''));
            $platform = mb_strtolower(trim((string) ($this->onlineDetails->platform ?? '')));

            if ($meetingLink !== '') {
                if ($this->isClickMeetingLink($meetingLink, $platform)) {
                    $lines[] = 'ClickMeeting: '.$meetingLink;
                }

                if ($this->isYouTubeLink($meetingLink, $platform)) {
                    $lines[] = 'YouTube: '.$meetingLink;
                }
            }
        }

        return $lines;
    }

    protected function isClickMeetingLink(string $url, string $platform): bool
    {
        if (str_contains($platform, 'clickmeeting')) {
            return true;
        }

        return str_contains(mb_strtolower($url), 'clickmeeting');
    }

    protected function isYouTubeLink(string $url, string $platform): bool
    {
        if (str_contains($platform, 'youtube')) {
            return true;
        }

        return (bool) preg_match('/(?:youtube\.com|youtu\.be|youtube-nocookie\.com)/i', $url);
    }

    protected function calendarEventEnd(): \Carbon\Carbon
    {
        if ($this->end_date) {
            return $this->end_date->copy();
        }

        return $this->start_date->copy()->addHours(2);
    }

    protected function calendarEventLocation(): ?string
    {
        if ($this->type !== 'offline' || ! $this->location) {
            return null;
        }

        $location = trim(implode(', ', array_filter([
            $this->location->location_name,
            $this->location->address,
            trim(($this->location->postal_code ?? '').' '.($this->location->post_office ?? '')),
            $this->location->country,
        ])));

        return $location !== '' ? $location : null;
    }

    protected function plainTitle(): string
    {
        $title = strip_tags(html_entity_decode((string) ($this->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return trim(preg_replace('/\s+/u', ' ', $title));
    }
}
