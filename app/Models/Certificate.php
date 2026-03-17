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
        'download_count',
        'first_downloaded_at',
        'last_downloaded_at',
        'issue_date',
        'generated_at'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'generated_at' => 'datetime',
        'first_downloaded_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
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
