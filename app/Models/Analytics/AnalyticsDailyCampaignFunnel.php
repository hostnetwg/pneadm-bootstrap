<?php

namespace App\Models\Analytics;

class AnalyticsDailyCampaignFunnel extends AnalyticsModel
{
    protected $table = 'analytics_daily_campaign_funnels';

    protected $casts = [
        'stat_date' => 'date',
        'internal_promo_touched' => 'boolean',
        'conversion_rate' => 'float',
    ];
}
