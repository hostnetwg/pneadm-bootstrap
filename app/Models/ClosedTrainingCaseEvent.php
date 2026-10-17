<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosedTrainingCaseEvent extends Model
{
    public const TYPE_CREATED = 'created';

    public const TYPE_NOTE = 'note';

    public const TYPE_UPDATED = 'updated';

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
            default => $this->event_type,
        };
    }
}
