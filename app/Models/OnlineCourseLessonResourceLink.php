<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineCourseLessonResourceLink extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'online_course_lesson_id',
        'url',
        'title',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(OnlineCourseLesson::class, 'online_course_lesson_id');
    }
}
