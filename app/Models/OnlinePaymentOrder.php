<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Zamówienia płatności online (PayU, Paynow) – widoczne w panelu adm.pnedu.pl
 */
class OnlinePaymentOrder extends Model
{
    use HasFactory;

    protected $table = 'online_payment_orders';

    protected $fillable = [
        'ident',
        'course_id',
        'payment_gateway',
        'payu_order_id',
        'status',
        'total_amount',
        'currency',
        'buyer_type',
        'email',
        'first_name',
        'last_name',
        'phone',
        'order_comment',
        'address_data',
        'form_data',
        'ip_address',
    ];

    protected $casts = [
        'address_data' => 'array',
        'form_data' => 'array',
        'total_amount' => 'decimal:2',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_CREATED = 'created';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function webhookLogs()
    {
        return $this->hasMany(WebhookLog::class, 'online_payment_order_id');
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'success',
            self::STATUS_CREATED => 'info',
            self::STATUS_CANCELLED, self::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }
}
