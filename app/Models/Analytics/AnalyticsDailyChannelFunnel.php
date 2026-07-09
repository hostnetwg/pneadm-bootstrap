<?php

namespace App\Models\Analytics;

class AnalyticsDailyChannelFunnel extends AnalyticsModel
{
    protected $table = 'analytics_daily_channel_funnels';

    protected $casts = [
        'stat_date' => 'date',
        'internal_promo_touched' => 'boolean',
        'conversion_rate' => 'float',
        'visible_to_first_interaction_rate' => 'float',
        'started_to_created_rate' => 'float',
        'submit_to_created_rate' => 'float',
    ];
}
