<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

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
        'success'
    ];

    protected $casts = [
        'headers' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
        'success' => 'boolean',
    ];

    public function scopePubligo($query)
    {
        return $query->where('source', 'publigo');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
