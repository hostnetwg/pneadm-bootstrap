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

    protected $fillable = [
        'closed_training_case_id',
        'user_id',
        'event_type',
        'body',
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
