<?php

namespace App\Models\Analytics;

class OrderFormAttribution extends AnalyticsModel
{
    protected $table = 'order_form_attributions';

    protected $casts = [
        'internal_promo_touched' => 'boolean',
        'fbclid_present' => 'boolean',
        'gclid_present' => 'boolean',
        'internal_touch_at' => 'datetime',
    ];
}
