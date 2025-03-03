<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'order',        
        'first_name',
        'last_name',
        'email',
        'birth_date',
        'birth_place'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class, 'participant_id');
    }
    
}
