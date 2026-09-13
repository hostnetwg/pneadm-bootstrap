<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zamówienia płatności online (PayU, Paynow) – widoczne w panelu adm.pnedu.pl
 */
class OnlinePaymentOrder extends Model
{
    use HasFactory;

    protected $table = 'online_payment_orders';

    protected $fillable = [
        'form_order_id',
        'ident',
        'course_id',
        'payment_gateway',
        'payu_order_id',
        'status',
        'total_amount',
        'currency',
        'buyer_type',
        'customer_profile',
        'terms_version',
        'terms_hash',
        'early_performance_scope',
        'early_performance_statement_version',
        'early_performance_accepted_at',
        'legal_confirmation_sent_at',
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
        'early_performance_accepted_at' => 'datetime',
        'legal_confirmation_sent_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_CREATED = 'created';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public function formOrder(): BelongsTo
    {
        return $this->belongsTo(FormOrder::class, 'form_order_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function displayProductName(): string
    {
        $item = $this->formOrder?->orderItems?->first();

        return (string) ($item?->product_name ?: $this->course?->title ?: 'Produkt');
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
