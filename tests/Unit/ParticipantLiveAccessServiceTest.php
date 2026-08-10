<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Models\Participant;
use App\Models\ParticipantLiveAccess;
use App\Services\ClickMeetingService;
use App\Services\ParticipantLiveAccessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ParticipantLiveAccessServiceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_invalidate_clickmeeting_token_calls_api_and_clears_local_token(): void
    {
        if (! Schema::hasTable('participant_live_access')
            || ! Schema::hasTable('courses')
            || ! Schema::hasTable('participants')) {
            $this->markTestSkipped('Brak wymaganych tabel w środowisku testowym.');
        }

        $course = Course::query()->create([
            'title' => 'Live invalidate',
            'description' => 'Opis',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ]);

        CourseOnlineDetails::query()->create([
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10088701',
            'meeting_link' => 'https://pnedu.clickmeeting.com/test-room',
        ]);

        $participant = Participant::query()->create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan.invalidate@example.com',
        ]);

        $access = ParticipantLiveAccess::query()->create([
            'participant_id' => $participant->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10088701',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
            'room_url' => 'https://pnedu.clickmeeting.com/test-room',
            'token' => 'XF34TY',
            'status' => 'success',
            'synced_at' => now(),
        ]);

        $mock = Mockery::mock(ClickMeetingService::class);
        $mock->shouldReceive('deactivateTokens')
            ->once()
            ->with('10088701', ['XF34TY'])
            ->andReturn(['success' => true, 'data' => ['status' => 'deleted']]);
        $this->app->instance(ClickMeetingService::class, $mock);

        $result = app(ParticipantLiveAccessService::class)->invalidateClickMeetingToken(
            $participant->fresh(['liveAccess']),
            $course->fresh(['onlineDetails'])
        );

        $this->assertTrue($result['success']);
        $this->assertSame('invalidated', $result['status']);
        $this->assertNull($access->fresh()->token);
        $this->assertSame('success', $access->fresh()->status);
        $this->assertStringContainsString('unieważniony', (string) $access->fresh()->message);
    }

    public function test_invalidate_clickmeeting_token_fails_without_saved_token(): void
    {
        $course = new Course(['title' => 'Live']);
        $participant = new Participant(['email' => 'a@example.com']);
        $participant->setRelation('liveAccess', new ParticipantLiveAccess([
            'status' => 'success',
            'token' => null,
            'clickmeeting_event_id' => '10088701',
        ]));

        $result = app(ParticipantLiveAccessService::class)->invalidateClickMeetingToken(
            $participant,
            $course
        );

        $this->assertFalse($result['success']);
        $this->assertSame('missing_token', $result['status']);
    }
}
