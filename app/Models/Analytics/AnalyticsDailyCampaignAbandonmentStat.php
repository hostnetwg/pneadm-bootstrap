<?php

namespace App\Models\Analytics;

class AnalyticsDailyCampaignAbandonmentStat extends AnalyticsModel
{
    protected $table = 'analytics_daily_campaign_abandonment_stats';

    protected $casts = [
        'stat_date' => 'date',
    ];
}
