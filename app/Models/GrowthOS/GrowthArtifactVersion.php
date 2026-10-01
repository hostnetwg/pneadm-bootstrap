<?php

namespace App\Models\GrowthOS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthArtifactVersion extends Model
{
    public const SOURCE_BASELINE = 'baseline';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AI_APPLY = 'ai_apply';

    public const SOURCE_RESTORE = 'restore';

    public const KEEP_LATEST = 20;

    public const UPDATED_AT = null;

    protected $fillable = [
        'growth_artifact_id',
        'version',
        'source',
        'restored_from_version',
        'payload',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'restored_from_version' => 'integer',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(GrowthArtifact::class, 'growth_artifact_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
