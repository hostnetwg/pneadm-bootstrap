<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPaymentReminderCampaignItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED_IFIRMA = 'skipped_ifirma';

    public const STATUS_SKIPPED_CASE = 'skipped_case';

    public const STATUS_REQUIRES_REVIEW = 'requires_review';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'campaign_id',
        'debt_case_id',
        'status',
        'skip_reason',
        'email_outcome',
        'sms_outcome',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DebtPaymentReminderCampaign::class, 'campaign_id');
    }

    public function debtCase(): BelongsTo
    {
        return $this->belongsTo(DebtCase::class);
    }

    public function statusLabel(): string
    {
        return self::statusLabelFor($this->status);
    }

    public static function statusLabelFor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Oczekuje',
            self::STATUS_PROCESSING => 'W trakcie',
            self::STATUS_SENT => 'Wysłano',
            self::STATUS_SKIPPED_IFIRMA => 'Pominięto po iFirma',
            self::STATUS_SKIPPED_CASE => 'Pominięto (zmiana sprawy)',
            self::STATUS_REQUIRES_REVIEW => 'Wymaga sprawdzenia',
            self::STATUS_FAILED => 'Błąd wysyłki',
            self::STATUS_CANCELLED => 'Anulowano',
            default => (string) $status,
        };
    }
}
