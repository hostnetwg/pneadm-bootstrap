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
        'online_course_id',
        'online_course_enrollment_id',
        'holder_first_name',
        'holder_last_name',
        'holder_birth_date',
        'holder_birth_place',
        'holder_email_normalized',
        'certificate_number',
        'file_path',
        'download_count',
        'first_downloaded_at',
        'last_downloaded_at',
        'issue_date',
        'generated_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'holder_birth_date' => 'date',
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

    public function onlineCourse()
    {
        return $this->belongsTo(OnlineCourse::class);
    }

    public function onlineCourseEnrollment()
    {
        return $this->belongsTo(OnlineCourseEnrollment::class);
    }

    public function isOnlineCourseCertificate(): bool
    {
        return $this->online_course_enrollment_id !== null;
    }
}
