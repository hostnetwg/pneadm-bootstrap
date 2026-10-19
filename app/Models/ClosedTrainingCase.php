<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    protected $fillable = [
        'source',
        'training_offer_id',
        'status',
        'assignee_user_id',
        'topic',
        'offer_url',
        'preferred_format',
        'contact_name',
        'contact_email',
        'contact_phone',
        'institution',
        'institution_www',
        'institution_type',
        'institution_type_other',
        'message',
        'submitted_payload',
        'consent_at',
        'next_action',
        'next_action_at',
        'closed_at',
    ];

    protected $casts = [
        'consent_at' => 'datetime',
        'next_action_at' => 'datetime',
        'closed_at' => 'datetime',
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

    public function events(): HasMany
    {
        return $this->hasMany(ClosedTrainingCaseEvent::class)->orderBy('happened_at')->orderBy('id');
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'Nowa',
            self::STATUS_IN_PROGRESS => 'W toku',
            self::STATUS_WAITING => 'Oczekuje',
            self::STATUS_CLOSED => 'Zamknięta',
        ];
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
        $email = trim((string) $this->contact_email);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
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
        if ($this->status === self::STATUS_CLOSED || $this->next_action_at === null) {
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
