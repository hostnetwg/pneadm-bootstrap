<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseOnlineDetails extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'platform',
        'meeting_link',
        'meeting_password',
        'clickmeeting_event_id',
        'clickmeeting_join_enabled',
        'embed_on_pnedu',
        'embed_email_link_enabled',
    ];

    protected $casts = [
        'clickmeeting_join_enabled' => 'boolean',
        'embed_on_pnedu' => 'boolean',
        'embed_email_link_enabled' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
