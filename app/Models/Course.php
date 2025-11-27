<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class Course extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'title',
        'description',
        'offer_description_html',
        'offer_summary',
        'start_date',
        'end_date',
        'is_paid', // 1/0
        'type', // online/offline
        'category', // open/closed
        'instructor_id',
        'image',
        'is_active',
        'certificate_format',
        'certificate_template_id',
        'access_duration_days',
        'access_notes',
        'id_old',
        'source_id_old'
    ];

    protected $casts = [
        'is_paid' => 'boolean', // ✅ Konwersja na boolean dla poprawnego odczytu        
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Relacja 1:1 – kurs ma jedną lokalizację (jeśli stacjonarny)
     */
    public function location()
    {
        return $this->hasOne(CourseLocation::class, 'course_id');
    }

    /**
     * Relacja 1:1 – kurs ma szczegóły dostępowe (jeśli online)
     */
    public function onlineDetails()
    {
        return $this->hasOne(CourseOnlineDetails::class, 'course_id');
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    /**
     * Relacja Many-to-Many - kurs może należeć do wielu serii
     * Sortowane po order_in_series w tabeli pivot
     */
    public function series()
    {
        return $this->belongsToMany(CourseSeries::class, 'course_series_course', 'course_id', 'course_series_id')
                    ->withPivot('order_in_series')
                    ->withTimestamps()
                    ->orderByPivot('order_in_series');
    }
        
    public function participants()
    {
        return $this->hasMany(Participant::class);
    }
    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Relacja do ankiet
     */
    public function surveys()
    {
        return $this->hasMany(Survey::class);
    }

    /**
     * Relacja do szablonu certyfikatu
     */
    public function certificateTemplate()
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    /**
     * Relacja do wariantów cenowych
     */
    public function priceVariants()
    {
        return $this->hasMany(CoursePriceVariant::class);
    }

    /**
     * Relacja do aktywnych wariantów cenowych
     */
    public function activePriceVariants()
    {
        return $this->hasMany(CoursePriceVariant::class)->where('is_active', true);
    }

    /**
     * Relacja do nagrań wideo
     */
    public function videos()
    {
        return $this->hasMany(CourseVideo::class)->orderBy('order');
    }
        
}
