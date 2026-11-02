<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CourseCalendarIcsTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_link_downloads_an_ics_file_without_login(): void
    {
        $start = Carbon::parse('2026-12-02 15:00:00', 'Europe/Warsaw');
        $course = Course::query()->create([
            'title' => 'Szkolenie rady w Przemyślu',
            'description' => 'Opis',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(3),
            'is_paid' => true,
            'type' => 'offline',
            'category' => 'closed',
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ]);
        $course->location()->create([
            'location_name' => 'Sala konferencyjna',
            'address' => 'ul. Szkolna 1',
            'postal_code' => '37-700',
            'post_office' => 'Przemyśl',
            'country' => 'PL',
        ]);

        $this->get('/kalendarz/szkolenie/'.$course->id.'.ics')
            ->assertForbidden();

        $response = $this->get(URL::signedRoute('courses.calendar-ics', ['course' => $course]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/calendar; charset=utf-8; method=PUBLISH');
        $body = $this->unfoldIcs((string) $response->getContent());
        $course->refresh();
        $wallStart = $course->start_date->copy()->timezone('Europe/Warsaw');
        $wallEnd = $course->end_date->copy()->timezone('Europe/Warsaw');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('DTSTART:'.$wallStart->copy()->utc()->format('Ymd\THis\Z'), $body);
        $this->assertStringContainsString('DTEND:'.$wallEnd->copy()->utc()->format('Ymd\THis\Z'), $body);
        $this->assertStringContainsString('Szkolenie rady w Przemyślu', $body);
        $this->assertStringContainsString('Sala konferencyjna', $body);
        $this->assertStringNotContainsString(route('courses.show', $course), $body);
        $this->assertStringContainsString('filename="szkolenie-'.$course->id.'.ics"', (string) $response->headers->get('content-disposition'));
    }

    public function test_online_ics_contains_the_meeting_link_and_not_the_admin_card(): void
    {
        $start = Carbon::parse('2026-06-10 09:00:00', 'Europe/Warsaw');
        $course = Course::query()->create([
            'title' => 'Szkolenie online rady',
            'description' => 'Opis',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'closed',
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ]);
        $course->onlineDetails()->create([
            'meeting_link' => 'https://pnedu.clickmeeting.com/rada-test',
        ]);

        $response = $this->get(URL::signedRoute('courses.calendar-ics', ['course' => $course]));

        $response->assertOk();
        $body = $this->unfoldIcs((string) $response->getContent());
        $course->refresh();
        $wallStart = $course->start_date->copy()->timezone('Europe/Warsaw');
        $this->assertStringNotContainsString('pnedu.clickmeeting.com', $body);
        $this->assertStringContainsString('Szkolenie: Szkolenie online rady', $body);
        $this->assertStringNotContainsString('Miejsce:', $body);
        $this->assertStringNotContainsString('LOCATION:', $body);
        $this->assertStringContainsString('DTSTART:'.$wallStart->copy()->utc()->format('Ymd\THis\Z'), $body);
        $this->assertStringNotContainsString('/courses/'.$course->id, $body);
    }

    private function unfoldIcs(string $ics): string
    {
        return preg_replace("/\r\n[ \t]/", '', $ics) ?? $ics;
    }
}
