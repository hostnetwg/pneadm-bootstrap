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
        'message',
        'consent_at',
        'next_action',
        'next_action_at',
        'closed_at',
    ];

    protected $casts = [
        'consent_at' => 'datetime',
        'next_action_at' => 'datetime',
        'closed_at' => 'datetime',
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

    public function trainingTopicLabel(): string
    {
        $topic = trim((string) $this->topic);

        if ($topic === '' || $topic === '(temat nie określony)') {
            return 'Nie podano';
        }

        return $topic;
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

    public function formatLocal(?Carbon $moment, string $format = 'd.m.Y H:i'): ?string
    {
        return $moment?->timezone('Europe/Warsaw')->format($format);
    }
}
