<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_id',
        'course_id',
        'certificate_number',
        'file_path',
        'generated_at'
    ];

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
