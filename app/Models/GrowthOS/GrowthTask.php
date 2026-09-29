<?php

namespace App\Models\GrowthOS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthTask extends Model
{
    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_BLOCKED,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'growth_campaign_id',
        'growth_artifact_id',
        'title',
        'description',
        'status',
        'assignee_user_id',
        'due_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GrowthCampaign::class, 'growth_campaign_id');
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(GrowthArtifact::class, 'growth_artifact_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(GrowthDecision::class);
    }
}
