<?php

namespace App\Models\Analytics;

class AnalyticsDailyGusChannelFunnel extends AnalyticsModel
{
    protected $table = 'analytics_daily_gus_channel_funnels';

    protected $casts = [
        'stat_date' => 'date',
        'gus_success_rate' => 'float',
        'gus_error_rate' => 'float',
        'conversion_rate_with_gus' => 'float',
        'conversion_rate_without_gus' => 'float',
        'conversion_rate_after_gus_success' => 'float',
        'conversion_rate_after_gus_error' => 'float',
        'gus_conversion_delta' => 'float',
    ];
}
