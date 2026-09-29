<?php

namespace App\Models\GrowthOS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthDecision extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CHANGES_REQUESTED,
        self::STATUS_SUPERSEDED,
    ];

    protected $fillable = [
        'growth_campaign_id',
        'growth_artifact_id',
        'growth_task_id',
        'type',
        'status',
        'question',
        'decision',
        'decided_by_user_id',
        'decided_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'meta' => 'array',
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(GrowthTask::class, 'growth_task_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
