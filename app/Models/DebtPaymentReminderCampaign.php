<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DebtPaymentReminderCampaign extends Model
{
    public const AUDIENCE_STANDARD = 'standard';

    public const AUDIENCE_VIP = 'vip';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'audience',
        'status',
        'started_by',
        'send_email',
        'send_sms',
        'email_template',
        'sms_template',
        'check_ifirma',
        'attach_ifirma_pdf',
        'strip_sms_diacritics',
        'sms_companion_email',
        'batch_id',
        'selected_count',
        'cancelled_at',
    ];

    protected $casts = [
        'send_email' => 'boolean',
        'send_sms' => 'boolean',
        'check_ifirma' => 'boolean',
        'attach_ifirma_pdf' => 'boolean',
        'strip_sms_diacritics' => 'boolean',
        'sms_companion_email' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if (! filled($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DebtPaymentReminderCampaignItem::class, 'campaign_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function audienceLabel(): string
    {
        return $this->audience === self::AUDIENCE_VIP ? 'VIP' : 'Standardowe';
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
