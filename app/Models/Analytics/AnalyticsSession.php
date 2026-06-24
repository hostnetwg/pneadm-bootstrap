<?php

namespace App\Models\Analytics;

class AnalyticsSession extends AnalyticsModel
{
    protected $table = 'analytics_sessions';

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'sample_rate' => 'integer',
    ];
}
