<?php

namespace App\Models\Analytics;

class OrderFormSession extends AnalyticsModel
{
    protected $table = 'order_form_sessions';

    protected $casts = [
        'started_at' => 'datetime',
        'first_interaction_at' => 'datetime',
        'last_event_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'has_recipient' => 'boolean',
        'gus_lookup_used' => 'boolean',
        'gus_lookup_success' => 'boolean',
    ];
}
