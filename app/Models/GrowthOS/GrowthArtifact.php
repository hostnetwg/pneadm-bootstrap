<?php

namespace App\Models\GrowthOS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthArtifact extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_NOT_STARTED,
        self::STATUS_DRAFT,
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'growth_campaign_id',
        'key',
        'type',
        'status',
        'title',
        'summary',
        'schema_version',
        'version',
        'payload',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'version' => 'integer',
            'payload' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GrowthCampaign::class, 'growth_campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(GrowthTask::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(GrowthDecision::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(GrowthArtifactVersion::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(GrowthArtifactImage::class);
    }
}
