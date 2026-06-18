<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingCampaignStatsDaily extends Model
{
    protected $table = 'marketing_campaign_stats_daily';

    protected $fillable = [
        'campaign_code',
        'stat_date',
        'link_entries',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'link_entries' => 'integer',
    ];

    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_code', 'campaign_code')->withTrashed();
    }
}
