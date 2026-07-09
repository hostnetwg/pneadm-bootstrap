<?php

namespace App\Models\Analytics;

class AnalyticsDailyDataQuality extends AnalyticsModel
{
    protected $table = 'analytics_daily_data_quality';

    protected $casts = [
        'stat_date' => 'date',
        'frontend_tracking_coverage_rate' => 'float',
        'attribution_coverage_rate' => 'float',
        'traffic_channel_coverage_rate' => 'float',
        'campaign_coverage_rate' => 'float',
        'schema_v2_event_rate' => 'float',
        'tracking_data_quality_flags' => 'array',
    ];
}
