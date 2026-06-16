<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursePageStatsDaily extends Model
{
    protected $table = 'course_page_stats_daily';

    protected $fillable = [
        'course_id',
        'stat_date',
        'views_course_show',
        'views_order_form',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'stat_date' => 'date',
        'views_course_show' => 'integer',
        'views_order_form' => 'integer',
    ];
}
