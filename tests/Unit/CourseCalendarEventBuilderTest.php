<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseLocation;
use App\Models\CourseOnlineDetails;
use App\Models\Instructor;
use App\Services\CourseGoogleCalendarSyncService;
use App\Services\GoogleCalendarClientFactory;
use App\Support\CourseCalendarEventBuilder;
use Carbon\Carbon;
use Tests\TestCase;

class CourseCalendarEventBuilderTest extends TestCase
{
    public function test_api_payload_includes_title_location_reminder_and_color(): void
    {
        config([
            'services.google_calendar.timezone' => 'Europe/Warsaw',
            'services.google_calendar.color_id_online' => '9',
            'services.google_calendar.color_id_offline' => '5',
            'services.google_calendar.color_id_free' => '10',
            'services.google_calendar.reminder_minutes' => 60,
        ]);

        $course = $this->makeCourse('offline');
        $course->is_paid = true;
        $course->setRelation('location', new CourseLocation([
            'location_name' => 'Sala A',
            'address' => 'ul. Test 1',
            'postal_code' => '00-001',
            'post_office' => 'Warszawa',
            'country' => 'Polska',
        ]));

        $payload = CourseCalendarEventBuilder::for($course)->toApiEventArray();

        $this->assertSame('Szkolenie testowe [dr Jan Kowalski]', $payload['summary']);
        $this->assertSame('Sala A, ul. Test 1, 00-001 Warszawa, Polska', $payload['location']);
        $this->assertSame('5', $payload['colorId']);
        $this->assertFalse($payload['reminders']['useDefault']);
        $this->assertSame(60, $payload['reminders']['overrides'][0]['minutes']);
        $this->assertSame('popup', $payload['reminders']['overrides'][0]['method']);
    }

    public function test_description_includes_youtube_link_for_online_course(): void
    {
        $course = $this->makeCourse('online');
        $course->setRelation('onlineDetails', new CourseOnlineDetails([
            'platform' => 'YouTube',
            'meeting_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]));

        $description = CourseCalendarEventBuilder::for($course)->description();

        $this->assertStringContainsString('Instruktor: dr Jan Kowalski', $description);
        $this->assertStringContainsString(route('courses.show', 516), $description);
        $this->assertStringContainsString('YouTube: https://www.youtube.com/watch?v=dQw4w9WgXcQ', $description);
    }

    public function test_free_course_uses_free_color_regardless_of_type(): void
    {
        config([
            'services.google_calendar.color_id_free' => '10',
            'services.google_calendar.color_id_online' => '9',
            'services.google_calendar.color_id_offline' => '5',
        ]);

        $this->assertSame('10', CourseCalendarEventBuilder::for($this->makeCourse('online'))->colorId());
        $this->assertSame('10', CourseCalendarEventBuilder::for($this->makeCourse('offline'))->colorId());
    }

    public function test_paid_online_course_uses_online_color(): void
    {
        config(['services.google_calendar.color_id_online' => '9']);

        $course = $this->makeCourse('online');
        $course->is_paid = true;

        $this->assertSame('9', CourseCalendarEventBuilder::for($course)->colorId());
    }

    public function test_should_sync_requires_active_course_with_dates(): void
    {
        config(['services.google_calendar.enabled' => false]);

        $factory = new GoogleCalendarClientFactory;
        $service = new CourseGoogleCalendarSyncService($factory);

        $course = $this->makeCourse('online');
        $this->assertTrue($service->shouldSync($course));

        $course->is_active = false;
        $this->assertFalse($service->shouldSync($course));

        $course->is_active = true;
        $course->end_date = null;
        $this->assertFalse($service->shouldSync($course));
    }

    private function makeCourse(string $type): Course
    {
        $course = new Course([
            'title' => 'Szkolenie testowe',
            'start_date' => Carbon::parse('2026-06-10 09:00:00'),
            'end_date' => Carbon::parse('2026-06-10 11:00:00'),
            'type' => $type,
            'category' => 'open',
            'is_paid' => false,
            'is_active' => true,
        ]);
        $course->id = 516;

        $instructor = new Instructor([
            'title' => 'dr',
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $course->setRelation('instructor', $instructor);
        $course->setRelation('location', null);
        $course->setRelation('onlineDetails', null);

        return $course;
    }
}
