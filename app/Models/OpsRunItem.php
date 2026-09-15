<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OpsRunItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'ops_run_id',
        'subject_type',
        'subject_id',
        'status',
        'message',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OpsRun::class, 'ops_run_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
