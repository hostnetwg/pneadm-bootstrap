<?php

namespace App\Support;

use App\Models\Course;
use Carbon\Carbon;

class TrainerSettlement
{
    public static function cutoffDate(): Carbon
    {
        return Carbon::parse(config('trainer_settlements.cutoff_date', '2026-05-01'))->startOfDay();
    }

    public static function isCourseInScope(Course $course): bool
    {
        if (! $course->start_date) {
            return false;
        }

        return Carbon::parse($course->start_date)->greaterThanOrEqualTo(self::cutoffDate());
    }
}
