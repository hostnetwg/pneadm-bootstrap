<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Services\GuestLiveLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestLiveLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pnedu_frontend_url' => 'http://localhost:8081']);
    }

    public function test_closed_embed_gets_stable_public_url(): void
    {
        $course = $this->makeCourse('closed');
        $this->attachDetails($course, embed: true);

        $first = app(GuestLiveLinkService::class)->panelState($course->fresh('onlineDetails'));
        $this->assertTrue($first['eligible']);
        $this->assertNotNull($first['url']);
        $this->assertStringStartsWith('http://localhost:8081/live/', $first['url']);
        $this->assertStringContainsString('Maila z ADM jeszcze nie wysyłamy', $first['hint']);
        $this->assertStringContainsString('imię, nazwisko i e-mail', $first['hint']);

        $token = $course->fresh()->onlineDetails?->guest_live_token;
        $this->assertNotNull($token);
        $this->assertSame(64, strlen((string) $token));

        $second = app(GuestLiveLinkService::class)->panelState($course->fresh('onlineDetails'));
        $this->assertSame($first['url'], $second['url']);
    }

    public function test_open_course_does_not_get_a_token(): void
    {
        $course = $this->makeCourse('open');
        $this->attachDetails($course, embed: true);

        $state = app(GuestLiveLinkService::class)->panelState($course->fresh('onlineDetails'));
        $this->assertFalse($state['eligible']);
        $this->assertNull($state['url']);
        $this->assertNull($course->fresh()->onlineDetails?->guest_live_token);
    }

    private function makeCourse(string $category): Course
    {
        return Course::query()->create([
            'title' => 'Guest link test',
            'description' => 'Test',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addHours(3),
            'is_paid' => true,
            'type' => 'online',
            'category' => $category,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);
    }

    private function attachDetails(Course $course, bool $embed): CourseOnlineDetails
    {
        return CourseOnlineDetails::query()->create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10164812',
            'meeting_link' => 'https://pnedu.clickmeeting.com/test',
            'embed_on_pnedu' => $embed,
        ]);
    }
}
