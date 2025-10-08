<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'location_name',
        'address',        
        'postal_code',
        'post_office',
        'country'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}