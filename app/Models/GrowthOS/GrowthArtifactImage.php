<?php

namespace App\Models\GrowthOS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthArtifactImage extends Model
{
    public const SOURCE_OPENAI = 'openai';

    public const SOURCE_SIMULATION = 'simulation';

    public const KEEP_LATEST = 10;

    public const UPDATED_AT = null;

    protected $fillable = [
        'growth_artifact_id',
        'source_image_id',
        'format',
        'width',
        'height',
        'disk',
        'path',
        'base_path',
        'mime',
        'size_bytes',
        'prompt',
        'include_headline',
        'overlay_headline',
        'overlay_date',
        'include_pne_logo',
        'include_sponsor_logo',
        'source',
        'provider',
        'model',
        'quality',
        'prompt_version',
        'is_selected',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'include_headline' => 'boolean',
            'include_pne_logo' => 'boolean',
            'include_sponsor_logo' => 'boolean',
            'is_selected' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(GrowthArtifact::class, 'growth_artifact_id');
    }

    public function sourceImage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_image_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function downloadName(): string
    {
        return sprintf('grafika-glowna-%s-%dx%d-%d.jpg', $this->format, $this->width, $this->height, $this->id);
    }
}
