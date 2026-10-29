<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class ClosedTrainingCase extends Model
{
    use LogsActivity;

    public const SOURCE_OFFER_FORM = 'offer_form';

    public const SOURCE_GENERAL_FORM = 'general_form';

    public const SOURCE_MANUAL = 'manual';

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CLOSED = 'closed';

    public const FORMAT_ONLINE = 'online';

    public const FORMAT_ONSITE = 'onsite';

    public const FORMAT_TO_DISCUSS = 'to_discuss';

    public const INSTITUTION_PRESCHOOL = 'preschool';

    public const INSTITUTION_PRIMARY = 'primary';

    public const INSTITUTION_SECONDARY = 'secondary';

    public const INSTITUTION_COMPLEX = 'complex';

    public const INSTITUTION_SPECIAL = 'special';

    public const INSTITUTION_OTHER = 'other';

    public const FEE_BASIS_GROSS = 'gross';

    public const FEE_BASIS_NET = 'net';

    /** @var list<string> */
    public const TEST_EMAIL_RECIPIENTS = [
        'waldemar.grabowski@hostnet.pl',
        'luman0599@gmail.com',
    ];

    protected $fillable = [
        'source',
        'training_offer_id',
        'status',
        'assignee_user_id',
        'instructor_id',
        'topic',
        'offer_url',
        'preferred_format',
        'contact_name',
        'contact_email',
        'contact_email_alt1',
        'contact_email_alt2',
        'contact_phone',
        'institution',
        'pedagogical_council_size',
        'institution_www',
        'institution_type',
        'institution_type_other',
        'message',
        'client_price',
        'client_price_confirmed_at',
        'instructor_fee',
        'instructor_fee_basis',
        'instructor_fee_confirmed_at',
        'start_date',
        'end_date',
        'schedule_time_known',
        'submitted_payload',
        'consent_at',
        'inquired_at',
        'next_action',
        'next_action_at',
        'closed_at',
    ];

    protected $casts = [
        'consent_at' => 'datetime',
        'inquired_at' => 'datetime',
        'next_action_at' => 'datetime',
        'closed_at' => 'datetime',
        'client_price' => 'decimal:2',
        'client_price_confirmed_at' => 'datetime',
        'instructor_fee' => 'decimal:2',
        'instructor_fee_confirmed_at' => 'datetime',
        'pedagogical_council_size' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'schedule_time_known' => 'boolean',
        'submitted_payload' => 'array',
    ];

    public function trainingOffer(): BelongsTo
    {
        return $this->belongsTo(TrainingOffer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClosedTrainingCaseEvent::class)->orderBy('happened_at')->orderBy('id');
    }

    public function course(): HasOne
    {
        return $this->hasOne(Course::class, 'closed_training_case_id');
    }

    public function isLinkedToCourse(): bool
    {
        if ($this->relationLoaded('course')) {
            return $this->course !== null;
        }

        return $this->course()->exists();
    }

    public function linkedCourseOfferMismatch(): bool
    {
        $course = $this->relationLoaded('course') ? $this->course : $this->course()->first();
        if (! $course) {
            return false;
        }

        $caseOfferId = $this->training_offer_id !== null ? (int) $this->training_offer_id : null;
        $courseOfferId = $course->training_offer_id !== null ? (int) $course->training_offer_id : null;

        return $caseOfferId !== $courseOfferId;
    }

    public function linkedCourseInstructorMismatch(): bool
    {
        $course = $this->relationLoaded('course') ? $this->course : $this->course()->first();
        if (! $course || $this->instructor_id === null) {
            return false;
        }

        return (int) $this->instructor_id !== (int) $course->instructor_id;
    }

    public function linkedCourseScheduleMismatch(): bool
    {
        $course = $this->relationLoaded('course') ? $this->course : $this->course()->first();
        if (! $course || ($this->start_date === null && $this->end_date === null)) {
            return false;
        }

        $caseStart = $this->start_date?->format('Y-m-d H:i');
        $caseEnd = $this->end_date?->format('Y-m-d H:i');
        $courseStart = $course->start_date?->format('Y-m-d H:i');
        $courseEnd = $course->end_date?->format('Y-m-d H:i');

        return $caseStart !== $courseStart || $caseEnd !== $courseEnd;
    }

    public function formatMoney(null|string|float $amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        return number_format((float) $amount, 2, ',', ' ').' PLN brutto';
    }

    public function formatMoneyCompact(null|string|float $amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        return number_format((float) $amount, 2, ',', ' ').' zł';
    }

    public function formatScheduleRange(?Carbon $start, ?Carbon $end, bool $timeKnown): string
    {
        if (! $start) {
            return '—';
        }

        $format = $timeKnown ? 'd.m.Y H:i' : 'd.m.Y';
        $label = $this->formatLocal($start, $format) ?? '—';
        if ($end) {
            $label .= ' – '.($this->formatLocal($end, $format) ?? '');
        }

        if (! $timeKnown) {
            $label .= ' (godzina do ustalenia)';
        }

        return $label;
    }

    /**
     * Aktualny trener do listy: po powiązaniu ze szkolenia, inaczej ze sprawy.
     */
    public function listInstructorName(): string
    {
        $course = $this->relationLoaded('course') ? $this->course : null;
        $instructor = $course?->instructor ?? $this->instructor;
        if (! $instructor) {
            return '—';
        }

        $name = trim((string) ($instructor->full_title_name ?? ($instructor->first_name.' '.$instructor->last_name)));

        return $name !== '' ? $name : '—';
    }

    /**
     * Aktualny termin do listy: po powiązaniu ze szkolenia, inaczej ze sprawy.
     */
    public function listScheduleLabel(): string
    {
        $course = $this->relationLoaded('course') ? $this->course : null;
        if ($course?->start_date) {
            return $this->formatScheduleRange($course->start_date, $course->end_date, true);
        }

        return $this->formatScheduleRange($this->start_date, $this->end_date, (bool) $this->schedule_time_known);
    }

    public function listScheduleLabelCompact(): string
    {
        $course = $this->relationLoaded('course') ? $this->course : null;
        if ($course?->start_date) {
            $start = $this->formatLocal($course->start_date, 'Y-m-d H:i') ?? '—';
            if ($course->end_date) {
                $sameDay = $course->start_date->timezone('Europe/Warsaw')->isSameDay($course->end_date->timezone('Europe/Warsaw'));
                $start .= '–'.($this->formatLocal($course->end_date, $sameDay ? 'H:i' : 'Y-m-d H:i') ?? '');
            }

            return $start;
        }

        if (! $this->start_date) {
            return '—';
        }

        $timeKnown = (bool) $this->schedule_time_known;
        $start = $this->formatLocal($this->start_date, $timeKnown ? 'Y-m-d H:i' : 'Y-m-d') ?? '—';
        if ($this->end_date) {
            $sameDay = $this->start_date->timezone('Europe/Warsaw')->isSameDay($this->end_date->timezone('Europe/Warsaw'));
            if ($timeKnown) {
                $start .= '–'.($this->formatLocal($this->end_date, $sameDay ? 'H:i' : 'Y-m-d H:i') ?? '');
            } elseif (! $sameDay) {
                $start .= '–'.($this->formatLocal($this->end_date, 'Y-m-d') ?? '');
            }
        }

        return $start;
    }

    /**
     * Kwota w stylu listy: "1500 PLN" (bez zbędnych ,00 gdy całkowita).
     */
    public function formatMoneyList(null|string|float $amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $value = (float) $amount;
        $formatted = fmod($value, 1.0) === 0.0
            ? number_format($value, 0, ',', ' ')
            : number_format($value, 2, ',', ' ');

        return $formatted.' PLN';
    }

    /**
     * @return array<string, string>
     */
    public static function instructorFeeBasisOptions(): array
    {
        return [
            self::FEE_BASIS_GROSS => 'brutto',
            self::FEE_BASIS_NET => 'netto',
        ];
    }

    public function instructorFeeBasisLabel(): string
    {
        return self::instructorFeeBasisOptions()[$this->instructor_fee_basis] ?? 'brutto';
    }

    /**
     * Wynagrodzenie na liście: "1 500 PLN brutto".
     */
    public function formatInstructorFeeList(): string
    {
        if ($this->instructor_fee === null || $this->instructor_fee === '') {
            return '—';
        }

        return $this->formatMoneyList($this->instructor_fee).' '.$this->instructorFeeBasisLabel();
    }

    public function lastActivityAt(): ?Carbon
    {
        $eventAt = $this->last_event_at ?? null;
        if ($eventAt) {
            return $eventAt instanceof Carbon ? $eventAt : Carbon::parse($eventAt);
        }

        return $this->updated_at ?? $this->created_at;
    }

    public function datetimeLocalValue(?Carbon $moment, bool $timeKnown): string
    {
        if (! $moment) {
            return '';
        }

        $local = $moment->timezone('Europe/Warsaw');

        return $timeKnown
            ? $local->format('Y-m-d\TH:i')
            : $local->format('Y-m-d');
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'Nowa',
            self::STATUS_IN_PROGRESS => 'W toku',
            self::STATUS_WAITING => 'Oczekuje',
            self::STATUS_SCHEDULED => 'Zaplanowana',
            self::STATUS_DELIVERED => 'Przeprowadzona',
            self::STATUS_CANCELLED => 'Anulowana',
            self::STATUS_CLOSED => 'Zamknięta',
        ];
    }

    /**
     * Statusy końcowe — poza filtrem „Otwarte”, z closed_at, bez zaległego następnego działania.
     *
     * @return list<string>
     */
    public static function terminalStatuses(): array
    {
        return [
            self::STATUS_CANCELLED,
            self::STATUS_CLOSED,
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::terminalStatuses(), true);
    }

    public static function formatOptions(): array
    {
        return [
            self::FORMAT_ONLINE => 'Online',
            self::FORMAT_ONSITE => 'Stacjonarnie',
            self::FORMAT_TO_DISCUSS => 'Do ustalenia',
        ];
    }

    public static function institutionTypeOptions(): array
    {
        return [
            self::INSTITUTION_PRESCHOOL => 'Przedszkole',
            self::INSTITUTION_PRIMARY => 'Szkoła podstawowa',
            self::INSTITUTION_SECONDARY => 'Szkoła ponadpodstawowa',
            self::INSTITUTION_COMPLEX => 'Zespół szkół / placówek',
            self::INSTITUTION_SPECIAL => 'Placówka specjalna',
            self::INSTITUTION_OTHER => 'Inna',
        ];
    }

    public static function formatInstitutionType(?string $type, ?string $other): string
    {
        if ($type === null || $type === '') {
            return 'Nie podano';
        }

        $label = self::institutionTypeOptions()[$type] ?? $type;

        if ($type !== self::INSTITUTION_OTHER) {
            return $label;
        }

        $detail = trim((string) $other);

        return $detail === '' ? 'Inna' : 'Inna — '.$detail;
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'text-bg-primary',
            self::STATUS_IN_PROGRESS => 'text-bg-info',
            self::STATUS_WAITING => 'text-bg-warning',
            self::STATUS_SCHEDULED => 'text-bg-success',
            self::STATUS_DELIVERED => 'text-bg-dark',
            self::STATUS_CANCELLED => 'text-bg-danger',
            self::STATUS_CLOSED => 'text-bg-secondary',
            default => 'text-bg-light',
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_OFFER_FORM => 'Formularz oferty na pnedu.pl',
            self::SOURCE_GENERAL_FORM => 'Formularz ogólny na pnedu.pl',
            self::SOURCE_MANUAL => 'Wpis ręczny w panelu',
            default => $this->source,
        };
    }

    /**
     * @param  array{contact_name: string, contact_email?: ?string, contact_phone?: ?string, institution?: ?string, institution_www?: ?string, institution_type?: ?string, institution_type_other?: ?string, preferred_format?: ?string, training_offer_id?: ?int, offer_title?: ?string, topic: string, message: string}  $fields
     * @return array<string, mixed>
     */
    public static function submittedSnapshot(array $fields): array
    {
        return [
            'contact_name' => $fields['contact_name'],
            'contact_email' => $fields['contact_email'] ?? null,
            'contact_phone' => $fields['contact_phone'] ?? null,
            'institution' => $fields['institution'] ?? null,
            'institution_www' => $fields['institution_www'] ?? null,
            'institution_type' => $fields['institution_type'] ?? null,
            'institution_type_other' => $fields['institution_type_other'] ?? null,
            'preferred_format' => $fields['preferred_format'] ?? null,
            'training_offer_id' => $fields['training_offer_id'] ?? null,
            'offer_title' => $fields['offer_title'] ?? null,
            'topic' => $fields['topic'],
            'message' => $fields['message'],
        ];
    }

    public function originalSubmissionHeading(): string
    {
        return $this->source === self::SOURCE_MANUAL ? 'Oryginał wpisu' : 'Oryginał z formularza';
    }

    /**
     * @return array<string, string>|null
     */
    public function originalSubmissionRows(): ?array
    {
        $payload = $this->submitted_payload;
        if (! is_array($payload) || ! array_key_exists('contact_name', $payload)) {
            return null;
        }

        $topic = trim((string) ($payload['topic'] ?? ''));
        if ($topic === '' || $topic === '(temat nie określony)') {
            $topic = 'Nie podano';
        }

        $offerTitle = trim((string) ($payload['offer_title'] ?? ''));

        return [
            'Osoba' => $this->originalText($payload['contact_name'] ?? null),
            'E-mail' => $this->originalText($payload['contact_email'] ?? null),
            'Telefon' => $this->originalText($payload['contact_phone'] ?? null),
            'Nazwa szkoły / placówki' => $this->originalText($payload['institution'] ?? null),
            'WWW' => $this->originalText($payload['institution_www'] ?? null),
            'Rodzaj placówki' => self::formatInstitutionType($payload['institution_type'] ?? null, $payload['institution_type_other'] ?? null),
            'Forma' => $this->originalFormat($payload['preferred_format'] ?? null),
            'Oferta' => $offerTitle === '' ? 'Brak powiązania z ofertą' : $offerTitle,
            'Temat szkolenia' => $topic,
            'Wiadomość' => $this->originalText($payload['message'] ?? null),
        ];
    }

    public function schoolEmailAddress(): ?string
    {
        $email = trim((string) $this->contact_email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Adresy kontaktu szkoły: główny + opcjonalne dodatkowe (unikalne, tylko poprawne).
     *
     * @return array<string, string> key: primary|alt1|alt2 → e-mail
     */
    public function contactEmailChoices(): array
    {
        $choices = [];
        $seen = [];

        foreach ([
            'primary' => $this->contact_email,
            'alt1' => $this->contact_email_alt1,
            'alt2' => $this->contact_email_alt2,
        ] as $key => $raw) {
            $email = strtolower(trim((string) $raw));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $choices[$key] = $email;
        }

        return $choices;
    }

    /**
     * @return list<string>
     */
    public function contactEmailList(): array
    {
        return array_values($this->contactEmailChoices());
    }

    public function hasAnyContactEmail(): bool
    {
        return $this->contactEmailChoices() !== [];
    }

    /**
     * @return list<string>
     */
    public static function testEmailRecipients(): array
    {
        return self::TEST_EMAIL_RECIPIENTS;
    }

    public static function emailReference(int $id): string
    {
        return '[SZ-'.$id.']';
    }

    public static function emailSubjectWithReference(int $id, string $subject): string
    {
        $subject = trim($subject);
        $reference = self::emailReference($id);
        if ($subject === '') {
            return $reference;
        }
        if (str_contains($subject, $reference)) {
            return $subject;
        }

        return $reference.' '.$subject;
    }

    public function trainingTopicLabel(): string
    {
        $topic = trim((string) $this->topic);

        if ($topic === '' || $topic === '(temat nie określony)') {
            return 'Nie podano';
        }

        return $topic;
    }

    public function referenceCode(): string
    {
        return 'SZ-'.$this->id;
    }

    public function hasContactEmail(): bool
    {
        return $this->hasAnyContactEmail();
    }

    public function defaultSchoolEmailSubject(): string
    {
        $topic = $this->trainingTopicLabel();
        if ($topic === 'Nie podano') {
            $topic = 'Szkolenie dla rady pedagogicznej';
        }

        return '['.$this->referenceCode().'] '.$topic;
    }

    public function withCaseReferenceInSubject(string $subject): string
    {
        $subject = trim($subject);
        $marker = '['.$this->referenceCode().']';

        if (str_starts_with($subject, $marker)) {
            return $subject;
        }

        $subject = preg_replace('/^\[SZ-\d+\]\s*/u', '', $subject) ?? $subject;
        $subject = trim($subject);

        return $subject === '' ? $marker : $marker.' '.$subject;
    }

    public static function defaultSchoolEmailBodyHtml(): string
    {
        return '<p><br></p>'
            .'<p style="margin:0;">Z wyrazami szacunku:<br>'
            .'Waldemar Grabowski<br>'
            .'tel. 501 654 274<br>'
            .'Akredytowany Niepubliczny Ośrodek Doskonalenia Nauczycieli</p>'
            .'<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;margin:0;">'
            .'<tr><td align="center" style="text-align:center;padding:0;margin:0;line-height:1.6;">'
            .'&quot;Platforma Nowoczesnej Edukacji&quot;<br>'
            .'<a href="https://pnedu.pl">pnedu.pl</a>'
            .'</td></tr></table>';
    }

    public static function sanitizeSchoolEmailHtml(string $html): string
    {
        $html = strip_tags($html,
            '<p><br><br/><strong><b><em><i><u><ul><ol><li><a><div><span><hr><hr/>'
            .'<blockquote><h1><h2><h3><h4><table><tr><td><th><tbody><thead>');

        $html = preg_replace_callback(
            '/<a\b([^>]*)>/iu',
            function (array $matches): string {
                if (! preg_match('/href\s*=\s*([\'"])(https?:\/\/[^\'"\s]+)\1/iu', $matches[1], $href)) {
                    return '<a>';
                }

                return '<a href="'.htmlspecialchars($href[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
            },
            $html
        ) ?? $html;

        return trim($html);
    }

    public function institutionTypeLabel(): string
    {
        return self::formatInstitutionType($this->institution_type, $this->institution_type_other);
    }

    public function preferredFormatLabel(): string
    {
        if ($this->preferred_format === null || $this->preferred_format === '') {
            return 'Nie wybrano';
        }

        return self::formatOptions()[$this->preferred_format] ?? $this->preferred_format;
    }

    public function nextActionIsOverdue(): bool
    {
        if ($this->isTerminal() || $this->next_action_at === null) {
            return false;
        }

        return $this->next_action_at->lt(now());
    }

    private function originalText(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '—' : $value;
    }

    private function originalFormat(?string $format): string
    {
        if ($format === null || $format === '') {
            return 'Nie wybrano';
        }

        return self::formatOptions()[$format] ?? $format;
    }

    public function formatLocal(?Carbon $moment, string $format = 'd.m.Y H:i'): ?string
    {
        return $moment?->timezone('Europe/Warsaw')->format($format);
    }
}
