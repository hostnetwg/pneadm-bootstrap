<?php

namespace App\Models\GrowthOS;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_LIVE = 'live';

    public const STATUS_FOLLOW_UP = 'follow_up';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PLANNING,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_LIVE,
        self::STATUS_FOLLOW_UP,
        self::STATUS_COMPLETED,
        self::STATUS_PAUSED,
        self::STATUS_CANCELLED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'name',
        'type',
        'status',
        'goal',
        'host_name',
        'owner_user_id',
        'primary_instructor_id',
        'communication_voice_instructor_id',
        'working_topic',
        'summary',
        'live_at',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'live_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function primaryInstructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'primary_instructor_id');
    }

    /**
     * Whose style the communication follows. Null means the neutral PNE voice (DEC-035).
     */
    public function communicationVoiceInstructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'communication_voice_instructor_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(GrowthArtifact::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(GrowthTask::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(GrowthDecision::class);
    }
}
