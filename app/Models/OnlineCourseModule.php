<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineCourseModule extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'online_course_id',
        'title',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function onlineCourse(): BelongsTo
    {
        return $this->belongsTo(OnlineCourse::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(OnlineCourseLesson::class)->orderBy('sort_order')->orderBy('id');
    }
}
