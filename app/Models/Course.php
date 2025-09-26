<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'is_paid', // 1/0
        'type', // online/offline
        'category', // open/closed
        'instructor_id',
        'image',
        'is_active',
        'certificate_format',
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
        
}
