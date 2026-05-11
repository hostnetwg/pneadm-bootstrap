<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineCourseLessonCompletion extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'online_course_enrollment_id',
        'online_course_lesson_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
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
