<?php

namespace App\Models\Analytics;

class AnalyticsDailyCampaignStat extends AnalyticsModel
{
    protected $table = 'analytics_daily_campaign_stats';

    protected $casts = [
        'stat_date' => 'date',
        'revenue_snapshot' => 'decimal:2',
    ];
}
