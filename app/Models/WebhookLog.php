<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    use HasFactory;

    protected $table = 'payment_webhook_logs';

    protected $fillable = [
        'online_payment_order_id',
        'payment_gateway',
        'gateway_payment_id',
        'external_id',
        'status',
        'status_mapped',
        'payload',
        'signature',
        'signature_valid',
        'ip_address',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlinePaymentOrder::class, 'online_payment_order_id');
    }

    /**
     * Zmapuj status z bramki płatności na status zamówienia.
     */
    public static function mapStatus(string $gateway, string $gatewayStatus): ?string
    {
        $gatewayStatus = strtoupper($gatewayStatus);

        if ($gateway === 'payu') {
            return match ($gatewayStatus) {
                'COMPLETED' => OnlinePaymentOrder::STATUS_PAID,
                'CANCELED', 'REJECTED', 'EXPIRED' => OnlinePaymentOrder::STATUS_CANCELLED,
                'PENDING', 'NEW' => OnlinePaymentOrder::STATUS_PENDING,
                default => null,
            };
        }

        if ($gateway === 'paynow') {
            return match ($gatewayStatus) {
                'CONFIRMED' => OnlinePaymentOrder::STATUS_PAID,
                'CANCELLED', 'REJECTED', 'EXPIRED', 'ERROR' => OnlinePaymentOrder::STATUS_CANCELLED,
                'PENDING', 'NEW' => OnlinePaymentOrder::STATUS_PENDING,
                default => null,
            };
        }

        return null;
    }
}
