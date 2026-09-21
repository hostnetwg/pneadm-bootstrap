<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Models\Participant;
use App\Models\ParticipantLiveAccess;
use App\Models\User;
use App\Services\ClickMeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParticipantManualClickMeetingTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();

        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
            'services.google_calendar.enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_creating_participant_on_token_room_registers_and_stores_token(): void
    {
        $this->fakeClickMeeting(accessType: ClickMeetingService::ACCESS_TYPE_TOKEN);
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();

        $this->actingAs($user)
            ->post(route('participants.store', $course), [
                'first_name' => 'Anna',
                'last_name' => 'Nowak',
                'email' => 'anna.token@example.test',
            ])
            ->assertRedirect(route('participants.index', $course))
            ->assertSessionHas('success');

        $participant = Participant::query()->where('course_id', $course->id)->first();
        $this->assertNotNull($participant);
        $this->assertDatabaseHas('participant_live_access', [
            'participant_id' => $participant->id,
            'course_id' => $course->id,
            'token' => 'TOK123',
            'status' => 'success',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/invitation/email/'));
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/token'));
    }

    public function test_creating_participant_on_open_room_does_not_register_in_clickmeeting(): void
    {
        $this->fakeClickMeeting(accessType: ClickMeetingService::ACCESS_TYPE_OPEN);
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();

        $this->actingAs($user)
            ->post(route('participants.store', $course), [
                'first_name' => 'Ewa',
                'last_name' => 'Kowalska',
                'email' => 'ewa.open@example.test',
            ])
            ->assertRedirect(route('participants.index', $course))
            ->assertSessionHas('success', 'Uczestnik dodany.');

        $participant = Participant::query()->where('course_id', $course->id)->first();
        $this->assertNotNull($participant);
        $this->assertDatabaseMissing('participant_live_access', [
            'participant_id' => $participant->id,
        ]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/invitation/email/'));
    }

    public function test_deleting_participant_invalidates_clickmeeting_token_first(): void
    {
        $this->fakeClickMeeting(accessType: ClickMeetingService::ACCESS_TYPE_TOKEN);
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $participant = Participant::query()->create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Jan',
            'last_name' => 'Usuwany',
            'email' => 'jan.delete@example.test',
        ]);
        ParticipantLiveAccess::query()->create([
            'participant_id' => $participant->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10229999',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
            'token' => 'KILLME',
            'status' => 'success',
            'synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('participants.destroy', [$course, $participant]))
            ->assertRedirect(route('participants.index', $course))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('participants', ['id' => $participant->id]);
        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/conferences/10229999/tokens')
                && data_get($request->data(), 'tokens.0') === 'KILLME';
        });
        $this->assertNull(ParticipantLiveAccess::query()->where('participant_id', $participant->id)->value('token'));
    }

    public function test_delete_proceeds_when_clickmeeting_token_invalidation_fails(): void
    {
        Http::fake([
            'api.clickmeeting.com/v1/conferences/10229999/tokens' => Http::response(['error' => 'not found'], 404),
        ]);

        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $participant = Participant::query()->create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Ola',
            'last_name' => 'Dawna',
            'email' => 'ola.old@example.test',
        ]);
        ParticipantLiveAccess::query()->create([
            'participant_id' => $participant->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10229999',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
            'token' => 'GONE',
            'status' => 'success',
            'synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('participants.destroy', [$course, $participant]))
            ->assertRedirect(route('participants.index', $course))
            ->assertSessionHas('success', 'Uczestnik usunięty.')
            ->assertSessionHas('info');

        $this->assertSoftDeleted('participants', ['id' => $participant->id]);
        $this->assertSame('GONE', ParticipantLiveAccess::query()->where('participant_id', $participant->id)->value('token'));
    }

    private function fakeClickMeeting(int $accessType): void
    {
        Http::fake(function ($request) use ($accessType) {
            $url = $request->url();
            $method = $request->method();

            if ($method === 'GET' && str_ends_with($url, '/conferences/10229999')) {
                return Http::response([
                    'conference' => [
                        'id' => 10229999,
                        'access_type' => $accessType,
                        'room_url' => 'https://pnedu.clickmeeting.com/test-token-room',
                    ],
                ], 200);
            }

            if (str_contains($url, '/invitation/email/')) {
                return Http::response(['status' => 'OK'], 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/token')) {
                return Http::response(['TOK123'], 200);
            }

            if ($method === 'DELETE' && str_contains($url, '/tokens')) {
                return Http::response(['status' => 'deleted'], 200);
            }

            return Http::response(['unexpected' => $url], 500);
        });
    }

    private function createOnlineCourse(): Course
    {
        $start = now()->addDays(2);
        $course = Course::query()->create([
            'title' => 'Token room course',
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(3),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ]);

        CourseOnlineDetails::query()->create([
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10229999',
            'meeting_link' => 'https://pnedu.clickmeeting.com/test-token-room',
        ]);

        return $course->fresh(['onlineDetails']);
    }
}
