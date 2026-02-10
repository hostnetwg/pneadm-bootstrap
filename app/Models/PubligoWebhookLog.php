<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Logi webhooków z Publigo.pl – tabela webhook_logs
 * Osobna od payment_webhook_logs (PayU, PayNow)
 */
class PubligoWebhookLog extends Model
{
    protected $table = 'webhook_logs';

    protected $fillable = [
        'source',
        'endpoint',
        'method',
        'request_data',
        'response_data',
        'status_code',
        'ip_address',
        'user_agent',
        'headers',
        'error_message',
        'success',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'headers' => 'array',
        'success' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
