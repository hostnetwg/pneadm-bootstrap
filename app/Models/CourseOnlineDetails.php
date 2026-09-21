<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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
        'guest_live_token',
        'live_bar_attendance_enabled',
        'live_bar_materials_enabled',
        'live_bar_survey_enabled',
        'live_bar_certificate_enabled',
        'live_offer_course_id',
        'live_offer_enabled',
        'live_offer_enabled_at',
        'live_offer_auto_hide',
    ];

    protected $casts = [
        'clickmeeting_join_enabled' => 'boolean',
        'embed_on_pnedu' => 'boolean',
        'embed_email_link_enabled' => 'boolean',
        'live_bar_attendance_enabled' => 'boolean',
        'live_bar_materials_enabled' => 'boolean',
        'live_bar_survey_enabled' => 'boolean',
        'live_bar_certificate_enabled' => 'boolean',
        'live_offer_course_id' => 'integer',
        'live_offer_enabled' => 'boolean',
        'live_offer_enabled_at' => 'datetime',
        'live_offer_auto_hide' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public static function courseForEventId(string $eventId): ?Course
    {
        $eventId = trim($eventId);
        if ($eventId === '') {
            return null;
        }

        $details = static::query()
            ->where('clickmeeting_event_id', $eventId)
            ->whereHas('course')
            ->with('course:id,title,start_date,category')
            ->orderByDesc('course_id')
            ->first();

        return $details?->course;
    }

    /**
     * @param  list<string>  $eventIds
     * @return Collection<string, self>
     */
    public static function coursesKeyedByEventId(array $eventIds): Collection
    {
        $eventIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => trim((string) $id), $eventIds),
            static fn (string $id) => $id !== ''
        )));

        if ($eventIds === []) {
            return collect();
        }

        return static::query()
            ->select(['id', 'course_id', 'clickmeeting_event_id', 'meeting_link'])
            ->whereIn('clickmeeting_event_id', $eventIds)
            ->whereHas('course')
            ->with('course:id,title,start_date,category')
            ->orderByDesc('course_id')
            ->get()
            ->groupBy(fn (self $details) => trim((string) $details->clickmeeting_event_id))
            ->map(fn (Collection $group) => $group->first())
            ->filter();
    }
}
