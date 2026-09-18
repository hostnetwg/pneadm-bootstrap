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

    public static function courseForEventId(string $eventId): ?Course
    {
        $eventId = trim($eventId);
        if ($eventId === '') {
            return null;
        }

        $details = static::query()
            ->where('clickmeeting_event_id', $eventId)
            ->whereHas('course')
            ->with('course:id,title,start_date')
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
            ->with('course:id,title,start_date')
            ->orderByDesc('course_id')
            ->get()
            ->groupBy(fn (self $details) => trim((string) $details->clickmeeting_event_id))
            ->map(fn (Collection $group) => $group->first())
            ->filter();
    }
}
