<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseLocation;
use App\Models\CourseOnlineDetails;
use App\Models\Instructor;
use Carbon\Carbon;
use Tests\TestCase;

class CourseGoogleCalendarUrlTest extends TestCase
{
    public function test_url_includes_instructor_in_event_title(): void
    {
        $course = $this->makeCourseWithInstructor('dr', 'Roman', 'Lorens');
        $course->title = 'Nowe zasady oceny pracy nauczyciela';

        $params = $this->calendarUrlParams($course);

        $this->assertSame(
            'Nowe zasady oceny pracy nauczyciela [dr Roman Lorens]',
            $params['text'] ?? null
        );
    }

    public function test_url_includes_offline_location_only_when_location_exists(): void
    {
        $course = $this->makeCourseWithInstructor();
        $course->type = 'offline';
        $course->setRelation('onlineDetails', null);
        $course->setRelation('location', new CourseLocation([
            'location_name' => 'Centrum Szkoleniowe',
            'address' => 'ul. Testowa 1',
            'postal_code' => '00-001',
            'post_office' => 'Warszawa',
            'country' => 'Polska',
        ]));

        $params = $this->calendarUrlParams($course);

        $this->assertSame(
            'Centrum Szkoleniowe, ul. Testowa 1, 00-001 Warszawa, Polska',
            $params['location'] ?? null
        );
    }

    public function test_url_omits_location_for_online_course(): void
    {
        $course = $this->makeCourseWithInstructor();
        $course->type = 'online';
        $course->setRelation('location', null);
        $course->setRelation('onlineDetails', new CourseOnlineDetails([
            'platform' => 'ClickMeeting',
            'meeting_link' => 'https://clickmeeting.example/live',
        ]));

        $params = $this->calendarUrlParams($course);

        $this->assertArrayNotHasKey('location', $params);
    }

    public function test_url_details_include_clickmeeting_and_youtube_links_for_online_course(): void
    {
        $course = $this->makeCourseWithInstructor();
        $course->type = 'online';
        $course->setRelation('location', null);
        $course->setRelation('onlineDetails', new CourseOnlineDetails([
            'platform' => 'YouTube',
            'meeting_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]));

        $params = $this->calendarUrlParams($course);

        $this->assertSame(
            implode("\n", [
                'Instruktor: dr Jan Kowalski',
                route('courses.show', 516),
                'YouTube: https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]),
            $params['details'] ?? null
        );
    }

    /**
     * @return array<string, string>
     */
    private function calendarUrlParams(Course $course): array
    {
        $url = $course->googleCalendarUrl();
        $this->assertNotNull($url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        return $params;
    }

    private function makeCourseWithInstructor(
        string $title = 'dr',
        string $firstName = 'Jan',
        string $lastName = 'Kowalski'
    ): Course {
        $course = new Course([
            'title' => 'Szkolenie testowe',
            'start_date' => Carbon::parse('2026-06-10 09:00:00'),
            'end_date' => Carbon::parse('2026-06-10 11:00:00'),
            'type' => 'online',
            'category' => 'open',
            'is_paid' => false,
            'is_active' => true,
        ]);
        $course->id = 516;

        $instructor = new Instructor([
            'title' => $title,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        $course->setRelation('instructor', $instructor);
        $course->setRelation('onlineDetails', null);
        $course->setRelation('location', null);

        return $course;
    }
}
