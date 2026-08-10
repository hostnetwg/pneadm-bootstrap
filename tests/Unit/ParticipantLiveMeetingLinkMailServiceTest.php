<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Models\Participant;
use App\Models\ParticipantLiveAccess;
use App\Services\ClickMeetingService;
use App\Services\ParticipantLiveMeetingLinkMailService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParticipantLiveMeetingLinkMailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_room_requires_participant_token(): void
    {
        if (! $this->tablesReady()) {
            $this->markTestSkipped('Brak wymaganych tabel.');
        }

        [$course] = $this->seedCourse(accessType: ClickMeetingService::ACCESS_TYPE_TOKEN, withToken: true);

        $service = app(ParticipantLiveMeetingLinkMailService::class);

        $this->assertTrue($service->courseRequiresAccessToken($course));
        $this->assertSame(1, $service->eligibleParticipantsCount($course));
    }

    public function test_open_room_includes_all_participants_with_email(): void
    {
        if (! $this->tablesReady()) {
            $this->markTestSkipped('Brak wymaganych tabel.');
        }

        [$course] = $this->seedCourse(accessType: 1, withToken: false, extraParticipantsWithoutAccess: 2);

        $service = app(ParticipantLiveMeetingLinkMailService::class);

        $this->assertFalse($service->courseRequiresAccessToken($course));
        $this->assertSame(3, $service->eligibleParticipantsCount($course));
    }

    public function test_resolve_live_context_builds_join_url_without_token_for_open_room(): void
    {
        if (! $this->tablesReady()) {
            $this->markTestSkipped('Brak wymaganych tabel.');
        }

        [$course, $participant] = $this->seedCourse(accessType: 1, withToken: false);

        $context = app(ParticipantLiveMeetingLinkMailService::class)
            ->resolveLiveContext($participant->fresh(['liveAccess']), $course->fresh(['onlineDetails']));

        $this->assertNotNull($context);
        $this->assertTrue($context->showLiveSection);
        $this->assertSame('https://pnedu.clickmeeting.com/open-room', $context->joinUrl);
        $this->assertNull($context->token);
    }

    /**
     * @return array{0: Course, 1: Participant}
     */
    private function seedCourse(int $accessType, bool $withToken, int $extraParticipantsWithoutAccess = 0): array
    {
        $course = Course::query()->create([
            'title' => 'Live mail test',
            'description' => 'Opis',
            'start_date' => Carbon::now()->addDay(),
            'end_date' => Carbon::now()->addDay()->addHours(2),
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
            'meeting_link' => 'https://pnedu.clickmeeting.com/open-room',
        ]);

        $participant = Participant::query()->create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'email' => 'anna.live@example.com',
        ]);

        ParticipantLiveAccess::query()->create([
            'participant_id' => $participant->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10088701',
            'access_type' => $accessType,
            'room_url' => 'https://pnedu.clickmeeting.com/open-room',
            'token' => $withToken ? 'TOK123' : null,
            'status' => 'success',
            'synced_at' => now(),
        ]);

        for ($i = 0; $i < $extraParticipantsWithoutAccess; $i++) {
            Participant::query()->create([
                'course_id' => $course->id,
                'order' => $i + 2,
                'first_name' => 'Extra'.$i,
                'last_name' => 'User',
                'email' => 'extra'.$i.'@example.com',
            ]);
        }

        return [$course->fresh(['onlineDetails']), $participant];
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('courses')
            && Schema::hasTable('participants')
            && Schema::hasTable('participant_live_access')
            && Schema::hasTable('course_online_details');
    }
}
