<?php

namespace App\Models\Analytics;

class AnalyticsDailyFormAbandonmentStat extends AnalyticsModel
{
    protected $table = 'analytics_daily_form_abandonment_stats';

    protected $casts = [
        'stat_date' => 'date',
    ];
}
