<?php

namespace App\Models\Analytics;

class AnalyticsDailyCourseStat extends AnalyticsModel
{
    protected $table = 'analytics_daily_course_stats';

    protected $casts = [
        'stat_date' => 'date',
        'revenue_snapshot' => 'decimal:2',
    ];
}
