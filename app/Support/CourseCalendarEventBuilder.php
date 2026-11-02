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
        $start = $this->zonedStart();
        $end = $this->zonedEnd();

        return $start->format('Ymd\THis').'/'.$end->format('Ymd\THis');
    }

    /**
     * Link Google z maila sprawy. Opis jest dla szkoły i prowadzącego:
     * przy stacjonarnym jest adres, przy online nie ma linku dla uczestników.
     */
    public function caseEmailGoogleCalendarUrl(): ?string
    {
        if (! $this->course->start_date) {
            return null;
        }

        $params = [
            'action' => 'TEMPLATE',
            'text' => $this->title(),
            'dates' => $this->templateDatesString(),
            'details' => $this->caseEmailDescription(),
            'ctz' => $this->timezone(),
        ];

        $place = $this->placeWithoutCountry();
        if ($place !== null && strtolower((string) $this->course->type) === 'offline') {
            $params['location'] = $place;
        }

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function caseEmailDescription(): string
    {
        $lines = [
            'Szkolenie: '.($this->plainTitle() ?: 'Szkolenie'),
            'Termin: '.$this->scheduleLabel(),
        ];

        $institution = $this->institutionName();
        if ($institution !== null) {
            $lines[] = 'Placówka: '.$institution;
        }

        if (strtolower((string) $this->course->type) === 'offline') {
            $place = $this->placeWithoutCountry();
            if ($place !== null) {
                $lines[] = 'Miejsce: '.$place;
            }
        }

        $contact = $this->contactLines();
        if ($contact !== []) {
            $lines[] = '';
            $lines[] = 'Kontakt:';
            array_push($lines, ...$contact);
        }

        return implode("\n", $lines);
    }

    /**
     * Plik kalendarza dla Apple, Outlooka i innych programów, które otwierają .ics.
     */
    public function toIcs(): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//PNE//Szkolenie//PL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:course-'.$this->course->id.'@pnedu.pl',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$this->zonedStart()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$this->zonedEnd()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->icsEscape($this->title()),
        ];

        $description = $this->caseEmailDescription();
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->icsEscape($description);
        }

        $place = $this->placeWithoutCountry();
        if ($place !== null && strtolower((string) $this->course->type) === 'offline') {
            $lines[] = 'LOCATION:'.$this->icsEscape($place);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->foldIcsLine(...), $lines))."\r\n";
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
        $start = $this->zonedStart();
        $end = $this->zonedEnd();
        $timezone = $this->timezone();

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

    private function zonedStart(): Carbon
    {
        return $this->start()->copy()->timezone($this->timezone());
    }

    private function zonedEnd(): Carbon
    {
        return $this->end()->copy()->timezone($this->timezone());
    }

    private function scheduleLabel(): string
    {
        if (! $this->course->start_date) {
            return 'do uzupełnienia';
        }

        $start = $this->course->start_date->copy()->timezone('Europe/Warsaw');
        $label = $start->format('d.m.Y').' r., godz. '.$start->format('H:i');

        if ($this->course->end_date) {
            $end = $this->course->end_date->copy()->timezone('Europe/Warsaw');
            if ($start->isSameDay($end)) {
                if ($end->format('H:i') !== $start->format('H:i')) {
                    $label .= '–'.$end->format('H:i');
                }
            } else {
                $label .= ' – '.$end->format('d.m.Y').' r., godz. '.$end->format('H:i');
            }
        }

        return $label;
    }

    private function institutionName(): ?string
    {
        $this->course->loadMissing(['closedTrainingCase', 'location']);
        $fromCase = trim((string) ($this->course->closedTrainingCase?->institution ?? ''));
        if ($fromCase !== '') {
            return $fromCase;
        }

        $fromLocation = trim((string) ($this->course->location?->location_name ?? ''));

        return $fromLocation !== '' ? $fromLocation : null;
    }

    /**
     * @return list<string>
     */
    private function contactLines(): array
    {
        $this->course->loadMissing('closedTrainingCase');
        $case = $this->course->closedTrainingCase;
        if ($case === null) {
            return [];
        }

        $lines = [];
        $name = trim((string) $case->contact_name);
        if ($name !== '') {
            $lines[] = 'Osoba: '.$name;
        }
        $phone = trim((string) $case->contact_phone);
        if ($phone !== '') {
            $lines[] = 'Telefon: '.$phone;
        }
        $emails = $case->contactEmailList();
        if ($emails !== []) {
            $lines[] = 'E-mail: '.implode(', ', $emails);
        }

        return $lines;
    }

    private function placeWithoutCountry(): ?string
    {
        $this->course->loadMissing('location');
        $location = $this->course->location;
        if ($location === null) {
            return null;
        }

        $parts = array_values(array_filter([
            trim((string) ($location->location_name ?? '')),
            trim((string) ($location->address ?? '')),
            trim(trim((string) ($location->postal_code ?? '')).' '.trim((string) ($location->post_office ?? ''))),
        ], static fn (string $part): bool => $part !== ''));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function icsEscape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\\n', '\\n', '', '\\;', '\\,'],
            $value
        );
    }

    private function foldIcsLine(string $line): string
    {
        $limit = 75;
        if (strlen($line) <= $limit) {
            return $line;
        }

        $parts = [];
        $offset = 0;
        $length = strlen($line);
        $first = true;

        while ($offset < $length) {
            $chunkLimit = $first ? $limit : $limit - 1;
            $take = min($chunkLimit, $length - $offset);
            if ($offset + $take < $length) {
                while ($take > 1 && (ord($line[$offset + $take]) & 0xC0) === 0x80) {
                    $take--;
                }
            }

            $chunk = substr($line, $offset, $take);
            $parts[] = $first ? $chunk : ' '.$chunk;
            $offset += $take;
            $first = false;
        }

        return implode("\r\n", $parts);
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
