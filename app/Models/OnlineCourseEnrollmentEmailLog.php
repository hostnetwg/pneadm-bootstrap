<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineCourseEnrollmentEmailLog extends Model
{
    public const TYPE_PLATFORM_MIGRATION = 'platform_migration';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'online_course_id',
        'online_course_enrollment_id',
        'type',
        'status',
        'batch_id',
        'created_by',
        'queued_at',
        'sent_at',
        'failed_at',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function onlineCourse(): BelongsTo
    {
        return $this->belongsTo(OnlineCourse::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(OnlineCourseEnrollment::class, 'online_course_enrollment_id');
    }
}
