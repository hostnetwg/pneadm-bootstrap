<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Models\Participant;
use App\Models\ParticipantLiveAccess;
use App\Services\ClickMeetingService;
use App\Services\ParticipantLiveAccessService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class ParticipantLiveAccessServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_skips_when_platform_is_not_clickmeeting(): void
    {
        $course = new Course(['title' => 'Test']);
        $course->setRelation('onlineDetails', new CourseOnlineDetails([
            'platform' => 'zoom',
            'meeting_link' => 'https://zoom.us/j/1',
        ]));
        $participant = new Participant([
            'email' => 'test@example.com',
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $result = app(ParticipantLiveAccessService::class)->provisionClickMeetingForParticipant(
            $participant,
            $course
        );

        $this->assertSame('skipped_not_clickmeeting', $result['status']);
    }

    public function test_builds_email_payload_from_live_access_record(): void
    {
        $access = new ParticipantLiveAccess([
            'status' => 'success',
            'room_url' => 'https://pnedu.clickmeeting.com/room',
            'token' => 'ABC123',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
        ]);

        $payload = app(ParticipantLiveAccessService::class)->toEmailClickMeetingPayload($access);

        $this->assertSame([
            'status' => 'success',
            'room_url' => 'https://pnedu.clickmeeting.com/room',
            'token' => 'ABC123',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
        ], $payload);
    }

    public function test_resolve_course_end_expiry_prefers_end_date(): void
    {
        $end = Carbon::parse('2026-08-01 12:00:00');
        $start = Carbon::parse('2026-07-01 10:00:00');
        $course = new Course([
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $resolved = app(ParticipantLiveAccessService::class)->resolveCourseEndExpiry($course);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->equalTo($end));
    }

    public function test_registers_in_clickmeeting_and_returns_token_payload(): void
    {
        $course = new Course(['title' => 'Live course', 'id' => 1]);
        $course->setRelation('onlineDetails', new CourseOnlineDetails([
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10088701',
            'meeting_link' => 'https://pnedu.clickmeeting.com/test-room',
        ]));

        $participant = new Participant([
            'id' => 1,
            'course_id' => 1,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.com',
        ]);

        $mock = Mockery::mock(ClickMeetingService::class);
        $mock->shouldReceive('registerParticipant')
            ->once()
            ->andReturn(['success' => true]);
        $mock->shouldReceive('getConference')
            ->once()
            ->andReturn([
                'success' => true,
                'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
                'conference' => ['room_url' => 'https://pnedu.clickmeeting.com/test-room'],
            ]);
        $mock->shouldReceive('extractRoomUrl')
            ->once()
            ->andReturn('https://pnedu.clickmeeting.com/test-room');
        $mock->shouldReceive('getAccessTokenForEmail')
            ->once()
            ->andReturn(['success' => true, 'token' => 'ABC123']);

        $this->app->instance(ClickMeetingService::class, $mock);

        $service = Mockery::mock(ParticipantLiveAccessService::class)->makePartial();
        $service->shouldReceive('persistLiveAccess')
            ->once()
            ->with($participant, $course, Mockery::on(function (array $result): bool {
                return ($result['status'] ?? '') === 'success'
                    && ($result['token'] ?? '') === 'ABC123';
            }), null)
            ->andReturn(new ParticipantLiveAccess(['token' => 'ABC123', 'status' => 'success']));

        $this->app->instance(ParticipantLiveAccessService::class, $service);

        $result = $service->provisionClickMeetingForParticipant($participant, $course);

        $this->assertSame('success', $result['status']);
        $this->assertSame('ABC123', $result['token']);
    }
}
