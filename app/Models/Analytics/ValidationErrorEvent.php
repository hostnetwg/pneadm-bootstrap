<?php

namespace App\Models\Analytics;

class ValidationErrorEvent extends AnalyticsModel
{
    public const UPDATED_AT = null;

    protected $table = 'validation_error_events';

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
