<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerPortalNotification extends Model
{
    public const AUDIENCE_ADMIN = 'admin';

    public const AUDIENCE_TRAINER = 'trainer';

    public const KIND_CASE_SHARED = 'case_shared';

    public const KIND_SCHEDULE_SET = 'schedule_set';

    public const KIND_TRAINER_DECLINED = 'trainer_declined';

    protected $fillable = [
        'audience',
        'pneadm_user_id',
        'pnedu_user_id',
        'closed_training_case_id',
        'kind',
        'title',
        'body',
        'read_at',
        'email_sent_at',
        'sms_sent_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'sms_sent_at' => 'datetime',
    ];

    public function closedTrainingCase(): BelongsTo
    {
        return $this->belongsTo(ClosedTrainingCase::class, 'closed_training_case_id');
    }
}
