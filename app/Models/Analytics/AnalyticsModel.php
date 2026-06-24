<?php

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;

abstract class AnalyticsModel extends Model
{
    protected $connection = 'analytics';

    protected $guarded = [];
}
