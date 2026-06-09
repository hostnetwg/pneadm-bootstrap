<?php

namespace App\Support;

use App\Models\Course;
use Carbon\Carbon;

class CourseCalendarEventBuilder
{
    public function __construct(
        private readonly Course $course
    ) {}

    public static function for(Course $course): self
    {
        return new self($course);
    }

    public function title(): string
    {
        $title = $this->plainTitle() ?: 'Szkolenie';

        if ($this->course->instructor) {
            $instructorName = trim($this->course->instructor->getFullTitleNameAttribute());
            if ($instructorName !== '') {
                $title .= ' ['.$instructorName.']';
            }
        }

        return $title;
    }

    public function description(): string
    {
        return implode("\n", $this->descriptionLines());
    }

    /**
     * @return list<string>
     */
    public function descriptionLines(): array
    {
        $lines = [];

        if ($this->course->instructor) {
            $lines[] = 'Instruktor: '.$this->course->instructor->getFullTitleNameAttribute();
        }

        $lines[] = route('courses.show', $this->course->id);

        if ($this->course->type === 'online' && $this->course->onlineDetails) {
            $meetingLink = trim((string) ($this->course->onlineDetails->meeting_link ?? ''));
            $platform = mb_strtolower(trim((string) ($this->course->onlineDetails->platform ?? '')));

            if ($meetingLink !== '') {
                if ($this->isClickMeetingLink($meetingLink, $platform)) {
                    $lines[] = 'ClickMeeting: '.$meetingLink;
                }

                if ($this->isYouTubeLink($meetingLink, $platform)) {
                    $lines[] = 'YouTube: '.$meetingLink;
                }
            }
        }

        return $lines;
    }

    public function location(): ?string
    {
        if ($this->course->type !== 'offline' || ! $this->course->location) {
            return null;
        }

        $location = trim(implode(', ', array_filter([
            $this->course->location->location_name,
            $this->course->location->address,
            trim(($this->course->location->postal_code ?? '').' '.($this->course->location->post_office ?? '')),
            $this->course->location->country,
        ])));

        return $location !== '' ? $location : null;
    }

    public function start(): Carbon
    {
        return $this->course->start_date->copy();
    }

    public function end(): Carbon
    {
        if ($this->course->end_date) {
            $end = $this->course->end_date->copy();
        } else {
            $end = $this->course->start_date->copy()->addHours(2);
        }

        if ($end->lessThanOrEqualTo($this->course->start_date)) {
            $end = $this->course->start_date->copy()->addHours(2);
        }

        return $end;
    }

    public function templateDatesString(): string
    {
        $timezone = $this->timezone();
        $start = $this->start()->copy()->timezone($timezone);
        $end = $this->end()->copy()->timezone($timezone);

        return $start->format('Ymd\THis').'/'.$end->format('Ymd\THis');
    }

    public function colorId(): string
    {
        if (! $this->course->is_paid) {
            return (string) config('services.google_calendar.color_id_free', '10');
        }

        if ($this->course->type === 'online') {
            return (string) config('services.google_calendar.color_id_online', '9');
        }

        return (string) config('services.google_calendar.color_id_offline', '5');
    }

    public function reminderMinutesBefore(): int
    {
        return (int) config('services.google_calendar.reminder_minutes', 60);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiEventArray(): array
    {
        $timezone = $this->timezone();
        $start = $this->start()->copy()->timezone($timezone);
        $end = $this->end()->copy()->timezone($timezone);

        $event = [
            'summary' => $this->title(),
            'description' => $this->description(),
            'start' => [
                'dateTime' => $start->format('Y-m-d\TH:i:s'),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $end->format('Y-m-d\TH:i:s'),
                'timeZone' => $timezone,
            ],
            'colorId' => $this->colorId(),
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    [
                        'method' => 'popup',
                        'minutes' => $this->reminderMinutesBefore(),
                    ],
                ],
            ],
            'extendedProperties' => [
                'private' => [
                    'course_id' => (string) $this->course->id,
                    'source' => 'pneadm',
                ],
            ],
        ];

        $location = $this->location();
        if ($location !== null) {
            $event['location'] = $location;
        }

        return $event;
    }

    private function timezone(): string
    {
        return (string) config('services.google_calendar.timezone', 'Europe/Warsaw');
    }

    private function plainTitle(): string
    {
        $title = strip_tags(html_entity_decode((string) ($this->course->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return trim(preg_replace('/\s+/u', ' ', $title));
    }

    private function isClickMeetingLink(string $url, string $platform): bool
    {
        if (str_contains($platform, 'clickmeeting')) {
            return true;
        }

        return str_contains(mb_strtolower($url), 'clickmeeting');
    }

    private function isYouTubeLink(string $url, string $platform): bool
    {
        if (str_contains($platform, 'youtube')) {
            return true;
        }

        return (bool) preg_match('/(?:youtube\.com|youtu\.be|youtube-nocookie\.com)/i', $url);
    }
}
