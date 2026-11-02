<?php

namespace Tests\Unit;

use App\Models\ClosedTrainingCase;
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

    public function test_guest_calendar_uses_warsaw_wall_time_and_hides_the_admin_course_url(): void
    {
        config(['services.google_calendar.timezone' => 'Europe/Warsaw']);

        $course = $this->makeCourse('offline');
        $course->start_date = '2026-12-02 14:00:00';
        $course->end_date = '2026-12-02 17:00:00';
        $course->setRelation('location', new CourseLocation([
            'location_name' => 'Sala A',
            'address' => 'ul. Test 1',
            'postal_code' => '00-001',
            'post_office' => 'Warszawa',
            'country' => 'Polska',
        ]));

        $wallStart = $course->start_date->copy()->timezone('Europe/Warsaw');
        $wallEnd = $course->end_date->copy()->timezone('Europe/Warsaw');
        $builder = CourseCalendarEventBuilder::for($course);
        $google = $course->googleCalendarUrl();
        $ics = $this->unfoldIcs($builder->toIcs());

        $this->assertNotNull($google);
        $this->assertStringContainsString(
            'dates='.rawurlencode($wallStart->format('Ymd\THis').'/'.$wallEnd->format('Ymd\THis')),
            $google
        );
        $this->assertStringContainsString(
            'text='.rawurlencode('Szkolenie testowe [dr Jan Kowalski]').'&dates=',
            $google
        );
        $this->assertStringContainsString('ctz=Europe%2FWarsaw', $google);
        $this->assertStringContainsString(rawurlencode(route('courses.show', 516)), $google);
        $this->assertStringContainsString('DTSTART:'.$wallStart->copy()->utc()->format('Ymd\THis\Z'), $ics);
        $this->assertStringContainsString('DTEND:'.$wallEnd->copy()->utc()->format('Ymd\THis\Z'), $ics);
        $this->assertSame('15:00', $wallStart->format('H:i'));
        $this->assertSame('18:00', $wallEnd->format('H:i'));
        $this->assertStringContainsString("SUMMARY:Szkolenie testowe [dr Jan Kowalski]\r\n", $ics);
        $this->assertStringContainsString('Szkolenie: Szkolenie testowe', $ics);
        $this->assertStringContainsString('Termin: 02.12.2026 r.\\, godz. 15:00–18:00', $ics);
        $this->assertStringContainsString('Miejsce: Sala A\\, ul. Test 1\\, 00-001 Warszawa', $ics);
        $this->assertStringContainsString('LOCATION:Sala A\\, ul. Test 1\\, 00-001 Warszawa', $ics);
        $this->assertStringNotContainsString('Polska', $ics);
        $this->assertStringNotContainsString('/courses/516', $ics);
    }

    public function test_google_template_keeps_the_title_when_it_ends_with_a_question_mark(): void
    {
        $course = $this->makeCourse('online');
        $course->title = 'Jak reagować, gdy sprawcą jest uczeń?';

        $url = $course->googleCalendarUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString(
            'text='.rawurlencode('Jak reagować, gdy sprawcą jest uczeń? [dr Jan Kowalski]').'&dates=',
            $url
        );
        $this->assertStringNotContainsString('%3F&dates=', $url);
    }

    public function test_case_email_calendar_adds_the_address_and_skips_the_participant_link(): void
    {
        $offline = $this->makeCourse('offline');
        $offline->setRelation('location', new CourseLocation([
            'location_name' => 'Sala A',
            'address' => 'ul. Test 1',
            'postal_code' => '00-001',
            'post_office' => 'Warszawa',
            'country' => 'Polska',
        ]));
        $offline->start_date = '2026-12-02 14:00:00';
        $offline->end_date = '2026-12-02 17:00:00';
        $offlineUrl = CourseCalendarEventBuilder::for($offline)->caseEmailGoogleCalendarUrl();
        $this->assertNotNull($offlineUrl);
        $this->assertStringContainsString(rawurlencode("Szkolenie: Szkolenie testowe\nTermin: 02.12.2026 r., godz. 15:00–18:00\nPlacówka: Sala A\nMiejsce: Sala A, ul. Test 1, 00-001 Warszawa"), $offlineUrl);
        $this->assertStringNotContainsString('Polska', $offlineUrl);
        $this->assertStringNotContainsString('/courses/516', $offlineUrl);

        $online = $this->makeCourse('online');
        $online->setRelation('onlineDetails', new CourseOnlineDetails([
            'platform' => 'ClickMeeting',
            'meeting_link' => 'https://pnedu.clickmeeting.com/rada-test',
        ]));
        $builder = CourseCalendarEventBuilder::for($online);
        $onlineUrl = $builder->caseEmailGoogleCalendarUrl();
        $ics = $this->unfoldIcs($builder->toIcs());

        $this->assertNotNull($onlineUrl);
        $this->assertStringContainsString(rawurlencode('Szkolenie: Szkolenie testowe'), $onlineUrl);
        $this->assertStringNotContainsString(rawurlencode('Miejsce:'), $onlineUrl);
        $this->assertStringNotContainsString('pnedu.clickmeeting.com', $onlineUrl);
        $this->assertStringNotContainsString('pnedu.clickmeeting.com', $ics);
        $this->assertStringNotContainsString('LOCATION:', $ics);
        $this->assertStringContainsString('Szkolenie: Szkolenie testowe', $ics);
    }

    public function test_case_email_calendar_includes_the_school_contact(): void
    {
        $course = $this->makeCourse('offline');
        $course->setRelation('closedTrainingCase', new ClosedTrainingCase([
            'institution' => 'Zespół Szkolno-Przedszkolny nr 7 w Przemyślu',
            'contact_name' => 'Dorota Strociak-Nuckowska',
            'contact_phone' => '+48 506 542 226, +48 16 670 29 15',
            'contact_email' => 'dorotanuckowska66@gmail.com',
        ]));
        $course->setRelation('location', new CourseLocation([
            'location_name' => 'Zespół Szkolno-Przedszkolny nr 7 w Przemyślu',
            'address' => 'ul. Grunwaldzka 81',
            'postal_code' => '37-700',
            'post_office' => 'Przemyśl',
        ]));

        $description = CourseCalendarEventBuilder::for($course)->caseEmailDescription();

        $this->assertStringContainsString(
            "Kontakt:\nOsoba: Dorota Strociak-Nuckowska\nTelefon: +48 506 542 226, +48 16 670 29 15\nE-mail: dorotanuckowska66@gmail.com",
            $description
        );
        $this->assertStringContainsString('Placówka: Zespół Szkolno-Przedszkolny nr 7 w Przemyślu', $description);
        $this->assertStringContainsString('Miejsce: Zespół Szkolno-Przedszkolny nr 7 w Przemyślu, ul. Grunwaldzka 81, 37-700 Przemyśl', $description);
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

    private function unfoldIcs(string $ics): string
    {
        return preg_replace("/\r\n[ \t]/", '', $ics) ?? $ics;
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
