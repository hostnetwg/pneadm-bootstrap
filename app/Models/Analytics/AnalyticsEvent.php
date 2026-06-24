<?php

namespace App\Models\Analytics;

class AnalyticsEvent extends AnalyticsModel
{
    public const UPDATED_AT = null;

    protected $table = 'analytics_events';

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];
}
