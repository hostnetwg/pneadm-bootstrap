<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class CourseSeries extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relacja Many-to-Many - seria ma wiele kursów
     * Kurs może należeć do wielu serii
     * Sortowane po order_in_series w tabeli pivot
     */
    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_series_course', 'course_series_id', 'course_id')
                    ->withPivot('order_in_series')
                    ->withTimestamps()
                    ->orderByPivot('order_in_series');
    }

    /**
     * Relacja do aktywnych kursów w serii
     * Sortowane po order_in_series w tabeli pivot
     */
    public function activeCourses()
    {
        return $this->belongsToMany(Course::class, 'course_series_course', 'course_series_id', 'course_id')
                    ->where('courses.is_active', true)
                    ->withPivot('order_in_series')
                    ->withTimestamps()
                    ->orderByPivot('order_in_series');
    }
}

