<?php

namespace App\Models\Analytics;

class LandingPageView extends AnalyticsModel
{
    public const UPDATED_AT = null;

    protected $table = 'landing_page_views';

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
