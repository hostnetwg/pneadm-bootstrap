<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseSeries extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'sort_order',
        'certificate_format',
        'certificate_template_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function certificateTemplate()
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function hasCertificateDefaults(): bool
    {
        return filled($this->certificate_format) || $this->certificate_template_id !== null;
    }

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
