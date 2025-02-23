<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'postal_code',  // ✅ Dodano kod pocztowy
        'post_office',  // ✅ Dodano pocztę
        'address',
        'country'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}