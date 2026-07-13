<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Services\ClickMeetingService;
use App\Services\PneduProvisionEmailContextBuilder;
use Carbon\Carbon;
use Tests\TestCase;

class PneduProvisionEmailContextBuilderTest extends TestCase
{
    public function test_skips_live_section_for_ended_course(): void
    {
        $course = $this->makeOnlineCourse([
            'start_date' => Carbon::parse('2026-01-01 10:00:00'),
            'end_date' => Carbon::parse('2026-01-01 12:00:00'),
        ], [
            'platform' => 'clickmeeting',
            'meeting_link' => 'https://pnedu.clickmeeting.com/wydarzenie-testowe',
            'clickmeeting_event_id' => '10088701',
        ]);

        $context = app(PneduProvisionEmailContextBuilder::class)->build($course, [
            'status' => 'success',
            'room_url' => 'https://pnedu.clickmeeting.com/wydarzenie-testowe',
            'token' => 'MCHK7N',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
        ]);

        $this->assertFalse($context->showLiveSection);
        $this->assertTrue($context->showPostEventSection);
    }

    public function test_builds_clickmeeting_token_link_for_upcoming_live_course(): void
    {
        $course = $this->makeOnlineCourse([
            'start_date' => Carbon::now()->addDays(2),
            'end_date' => Carbon::now()->addDays(2)->addHours(2),
        ], [
            'platform' => 'clickmeeting',
            'meeting_link' => 'https://pnedu.clickmeeting.com/wydarzenie-testowe',
            'clickmeeting_event_id' => '10088701',
        ]);

        $context = app(PneduProvisionEmailContextBuilder::class)->build($course, [
            'status' => 'success',
            'room_url' => 'https://pnedu.clickmeeting.com/wydarzenie-testowe',
            'token' => 'MCHK7N',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
        ]);

        $this->assertTrue($context->showLiveSection);
        $this->assertSame('https://pnedu.clickmeeting.com/wydarzenie-testowe/MCHK7N', $context->joinUrl);
        $this->assertSame('MCHK7N', $context->token);
        $this->assertTrue($context->showSpamNote);
    }

    public function test_skips_clickmeeting_section_when_integration_failed(): void
    {
        $course = $this->makeOnlineCourse([
            'start_date' => Carbon::now()->addDays(2),
            'end_date' => Carbon::now()->addDays(2)->addHours(2),
        ], [
            'platform' => 'clickmeeting',
            'meeting_link' => 'https://pnedu.clickmeeting.com/wydarzenie-testowe',
            'clickmeeting_event_id' => '10088701',
        ]);

        $context = app(PneduProvisionEmailContextBuilder::class)->build($course, [
            'status' => 'failed',
        ]);

        $this->assertFalse($context->showLiveSection);
    }

    public function test_uses_meeting_link_for_non_clickmeeting_platform(): void
    {
        $course = $this->makeOnlineCourse([
            'start_date' => Carbon::now()->addDays(2),
            'end_date' => Carbon::now()->addDays(2)->addHours(2),
        ], [
            'platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
            'meeting_password' => 'sekret123',
        ]);

        $context = app(PneduProvisionEmailContextBuilder::class)->build($course);

        $this->assertTrue($context->showLiveSection);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $context->joinUrl);
        $this->assertSame('sekret123', $context->password);
        $this->assertSame('Google Meet', $context->platformLabel);
        $this->assertFalse($context->showSpamNote);
    }

    public function test_includes_password_for_password_protected_clickmeeting(): void
    {
        $course = $this->makeOnlineCourse([
            'start_date' => Carbon::now()->addDays(2),
            'end_date' => Carbon::now()->addDays(2)->addHours(2),
        ], [
            'platform' => 'clickmeeting',
            'meeting_link' => 'https://pnedu.clickmeeting.com/haslo-test',
            'meeting_password' => 'room-pass',
            'clickmeeting_event_id' => '10088701',
        ]);

        $context = app(PneduProvisionEmailContextBuilder::class)->build($course, [
            'status' => 'success',
            'room_url' => 'https://pnedu.clickmeeting.com/haslo-test',
            'access_type' => ClickMeetingService::ACCESS_TYPE_PASSWORD,
        ]);

        $this->assertTrue($context->showLiveSection);
        $this->assertSame('room-pass', $context->password);
        $this->assertNull($context->token);
    }

    public function test_skips_clickmeeting_section_when_token_missing_for_token_event(): void
    {
        $course = $this->makeOnlineCourse([
            'start_date' => Carbon::now()->addDays(2),
            'end_date' => Carbon::now()->addDays(2)->addHours(2),
        ], [
            'platform' => 'clickmeeting',
            'meeting_link' => 'https://pnedu.clickmeeting.com/wydarzenie-testowe',
            'clickmeeting_event_id' => '10088701',
        ]);

        $context = app(PneduProvisionEmailContextBuilder::class)->build($course, [
            'status' => 'success',
            'room_url' => 'https://pnedu.clickmeeting.com/wydarzenie-testowe',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
        ]);

        $this->assertFalse($context->showLiveSection);
    }

    /**
     * @param  array<string, mixed>  $courseAttrs
     * @param  array<string, mixed>  $onlineAttrs
     */
    private function makeOnlineCourse(array $courseAttrs, array $onlineAttrs): Course
    {
        $course = new Course(array_merge([
            'type' => 'online',
            'title' => 'Test course',
        ], $courseAttrs));

        $course->setRelation('onlineDetails', new CourseOnlineDetails($onlineAttrs));

        return $course;
    }
}
