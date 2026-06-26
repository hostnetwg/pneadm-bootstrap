<?php

namespace App\Models\Analytics;

class AnalyticsDailyCourseRevenueStat extends AnalyticsModel
{
    protected $table = 'analytics_daily_course_revenue_stats';

    protected $casts = [
        'stat_date' => 'date',
        'ordered_revenue_gross' => 'decimal:2',
        'online_paid_revenue_gross' => 'decimal:2',
        'deferred_invoiced_revenue_gross' => 'decimal:2',
        'settled_revenue_gross' => 'decimal:2',
    ];
}
