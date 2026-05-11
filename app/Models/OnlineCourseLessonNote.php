<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineCourseLessonNote extends Model
{
    protected $fillable = [
        'online_course_enrollment_id',
        'online_course_lesson_id',
        'body',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(OnlineCourseEnrollment::class, 'online_course_enrollment_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(OnlineCourseLesson::class, 'online_course_lesson_id');
    }
}
