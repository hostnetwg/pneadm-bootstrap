<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantLiveAccess extends Model
{
    protected $table = 'participant_live_access';

    protected $fillable = [
        'participant_id',
        'course_id',
        'form_order_id',
        'platform',
        'clickmeeting_event_id',
        'access_type',
        'room_url',
        'token',
        'embed_token_consumed_at',
        'embed_first_entered_at',
        'embed_last_entered_at',
        'status',
        'message',
        'synced_at',
        'expires_at',
    ];

    protected $casts = [
        'access_type' => 'integer',
        'embed_token_consumed_at' => 'datetime',
        'embed_first_entered_at' => 'datetime',
        'embed_last_entered_at' => 'datetime',
        'synced_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function formOrder(): BelongsTo
    {
        return $this->belongsTo(FormOrder::class, 'form_order_id');
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function hasEnteredEmbedOnPnedu(): bool
    {
        return $this->embed_last_entered_at !== null;
    }
}
