<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseOnlineDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'platform',
        'meeting_link',
        'meeting_password'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}

