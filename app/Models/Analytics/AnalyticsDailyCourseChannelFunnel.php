<?php

namespace App\Models\Analytics;

class AnalyticsDailyCourseChannelFunnel extends AnalyticsModel
{
    protected $table = 'analytics_daily_course_channel_funnels';

    protected $casts = [
        'stat_date' => 'date',
        'internal_promo_touched' => 'boolean',
        'conversion_rate' => 'float',
        'visible_to_first_interaction_rate' => 'float',
        'started_to_created_rate' => 'float',
        'submit_to_created_rate' => 'float',
    ];
}
