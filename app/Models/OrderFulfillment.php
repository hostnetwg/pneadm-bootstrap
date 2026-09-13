<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFulfillment extends Model
{
    public const TYPE_ONLINE_COURSE_ACCESS = 'online_course_access';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_item_recipient_id',
        'type',
        'idempotency_key',
        'status',
        'attempts',
        'pnedu_user_id',
        'online_course_enrollment_id',
        'granted_at',
        'notified_at',
        'failed_at',
        'previous_access_expires_at',
        'new_access_expires_at',
        'error_message',
        'payload',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'pnedu_user_id' => 'integer',
        'online_course_enrollment_id' => 'integer',
        'granted_at' => 'immutable_datetime',
        'notified_at' => 'immutable_datetime',
        'failed_at' => 'immutable_datetime',
        'previous_access_expires_at' => 'immutable_datetime',
        'new_access_expires_at' => 'immutable_datetime',
        'payload' => 'array',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(OrderItemRecipient::class, 'order_item_recipient_id');
    }
}
