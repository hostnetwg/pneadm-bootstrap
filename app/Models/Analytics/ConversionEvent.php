<?php

namespace App\Models\Analytics;

class ConversionEvent extends AnalyticsModel
{
    public const UPDATED_AT = null;

    protected $table = 'conversion_events';

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'amount_snapshot' => 'decimal:2',
        'has_recipient' => 'boolean',
    ];
}
