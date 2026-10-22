<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosedTrainingCaseEvent extends Model
{
    public const TYPE_CREATED = 'created';

    public const TYPE_NOTE = 'note';

    public const TYPE_UPDATED = 'updated';

    public const TYPE_EMAIL_SENT = 'email_sent';

    public const NOTE_CHANNEL_PHONE = 'phone';

    public const NOTE_CHANNEL_EMAIL = 'email';

    public const NOTE_CHANNEL_SMS = 'sms';

    public const NOTE_CHANNEL_MESSENGER = 'messenger';

    public const NOTE_CHANNEL_OTHER = 'other';

    public const NOTE_PARTY_INSTRUCTOR = 'instructor';

    public const NOTE_PARTY_CLIENT = 'client';

    protected $fillable = [
        'closed_training_case_id',
        'user_id',
        'event_type',
        'body',
        'note_channel',
        'note_channel_other',
        'note_party',
        'note_party_name',
        'happened_at',
    ];

    protected $casts = [
        'happened_at' => 'datetime',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(ClosedTrainingCase::class, 'closed_training_case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->event_type) {
            self::TYPE_CREATED => 'Założenie',
            self::TYPE_NOTE => 'Notatka',
            self::TYPE_UPDATED => 'Zmiana',
            self::TYPE_EMAIL_SENT => 'E-mail do szkoły',
            default => $this->event_type,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function noteChannelOptions(): array
    {
        return [
            self::NOTE_CHANNEL_PHONE => 'Telefon',
            self::NOTE_CHANNEL_EMAIL => 'E-mail',
            self::NOTE_CHANNEL_SMS => 'SMS',
            self::NOTE_CHANNEL_MESSENGER => 'Messenger',
            self::NOTE_CHANNEL_OTHER => 'Inny',
        ];
    }

    /**
     * Bootstrap Icons class for history list (without leading "bi ").
     */
    public function historyIconClass(): string
    {
        if ($this->event_type === self::TYPE_UPDATED) {
            return 'bi-pencil';
        }

        if ($this->event_type === self::TYPE_EMAIL_SENT) {
            return 'bi-envelope';
        }

        if ($this->event_type === self::TYPE_CREATED) {
            return 'bi-plus-circle';
        }

        if ($this->event_type === self::TYPE_NOTE) {
            return match ($this->note_channel) {
                self::NOTE_CHANNEL_PHONE => 'bi-telephone',
                self::NOTE_CHANNEL_EMAIL => 'bi-envelope',
                self::NOTE_CHANNEL_SMS => 'bi-chat-left-text',
                self::NOTE_CHANNEL_MESSENGER => 'bi-messenger',
                self::NOTE_CHANNEL_OTHER => 'bi-chat-dots',
                default => 'bi-sticky',
            };
        }

        return 'bi-circle';
    }

    public function historyIconTitle(): string
    {
        if ($this->event_type === self::TYPE_NOTE && $this->noteChannelLabel()) {
            return $this->noteChannelLabel();
        }

        return $this->typeLabel();
    }

    /**
     * @return array<string, string>
     */
    public static function notePartyOptions(): array
    {
        return [
            self::NOTE_PARTY_INSTRUCTOR => 'Trener',
            self::NOTE_PARTY_CLIENT => 'Zamawiający',
        ];
    }

    public function noteChannelLabel(): ?string
    {
        if ($this->note_channel === null || $this->note_channel === '') {
            return null;
        }

        $label = self::noteChannelOptions()[$this->note_channel] ?? $this->note_channel;

        if ($this->note_channel === self::NOTE_CHANNEL_OTHER) {
            $detail = trim((string) $this->note_channel_other);

            return $detail === '' ? $label : $label.': '.$detail;
        }

        return $label;
    }

    public function notePartyLabel(): ?string
    {
        if ($this->note_party === null || $this->note_party === '') {
            return null;
        }

        $role = self::notePartyOptions()[$this->note_party] ?? $this->note_party;
        $name = trim((string) $this->note_party_name);

        return $name === '' ? $role : $role.': '.$name;
    }

    /**
     * Jedna linia etykiet nad treścią notatki, np. „Telefon · Zamawiający: Anna Nowak”.
     */
    public function noteContextLabel(): ?string
    {
        if ($this->event_type !== self::TYPE_NOTE) {
            return null;
        }

        $parts = array_values(array_filter([
            $this->noteChannelLabel(),
            $this->notePartyLabel(),
        ]));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public function historyItemClass(): string
    {
        return match ($this->event_type) {
            self::TYPE_UPDATED => 'bg-secondary-subtle',
            self::TYPE_EMAIL_SENT => 'bg-success-subtle',
            self::TYPE_NOTE => match ($this->note_party) {
                self::NOTE_PARTY_INSTRUCTOR => 'bg-info-subtle',
                self::NOTE_PARTY_CLIENT => 'bg-warning-subtle',
                default => '',
            },
            default => '',
        };
    }

    public function schoolEmailMeta(): ?string
    {
        if ($this->event_type !== self::TYPE_EMAIL_SENT) {
            return null;
        }

        $parts = preg_split("/\n\n/", (string) $this->body, 2);

        return $parts[0] ?? null;
    }

    public function schoolEmailHtml(): ?string
    {
        if ($this->event_type !== self::TYPE_EMAIL_SENT) {
            return null;
        }

        $parts = preg_split("/\n\n/", (string) $this->body, 2);

        return isset($parts[1]) ? ClosedTrainingCase::sanitizeSchoolEmailHtml($parts[1]) : null;
    }
}
