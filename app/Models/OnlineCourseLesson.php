<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineCourseLesson extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'online_course_module_id',
        'title',
        'body_html',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(OnlineCourseModule::class, 'online_course_module_id');
    }

    public function embeds(): HasMany
    {
        return $this->hasMany(OnlineCourseLessonEmbed::class)->orderBy('sort_order')->orderBy('id');
    }

    public function resourceLinks(): HasMany
    {
        return $this->hasMany(OnlineCourseLessonResourceLink::class)->orderBy('sort_order')->orderBy('id');
    }

    public function onlineCourse(): ?OnlineCourse
    {
        return $this->module?->onlineCourse;
    }
}
