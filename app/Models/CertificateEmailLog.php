<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateEmailLog extends Model
{
    use HasFactory;

    public const TYPE_LIST_LINK = 'list_link';
    public const TYPE_SINGLE_CERTIFICATE = 'single_certificate';
    public const TYPE_COURSE_ACCESS = 'course_access';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'course_id',
        'participant_id',
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

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}

