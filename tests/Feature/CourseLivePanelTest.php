<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseFileLink;
use App\Models\CourseOnlineDetails;
use App\Models\CourseSurveyLink;
use App\Models\ParticipantLiveAccess;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLivePanelTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();

        config([
            'services.pnedu_frontend_url' => 'http://localhost:8081',
        ]);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_live_panel(): void
    {
        $course = $this->createOnlineCourse();

        $this->get(route('courses.live', $course->id))
            ->assertRedirect(route('login'));
    }

    public function test_live_panel_shows_independent_checkboxes_and_shortcuts(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $this->attachOnlineDetails($course, embed: true);

        $html = $this->actingAs($user)
            ->get(route('courses.live', $course->id))
            ->assertOk()
            ->assertSee('Panel live', false)
            ->assertSee('Rejestracja: lista obecności', false)
            ->assertSee('Pobierz zaświadczenie', false)
            ->assertSee('Materiały', false)
            ->assertSee('Ankieta', false)
            ->assertSee('Uczestnik nie widzi żadnego dodatkowego przycisku', false)
            ->assertSee('Oferta kolejnego szkolenia', false)
            ->assertSee('Wyświetl uczestnikom', false)
            ->assertSee('id="live_offer_course_id"', false)
            ->assertSee('id="live_offer_auto_hide"', false)
            ->assertSee('Ukryj ofertę po 2 minutach', false)
            ->assertSee('Pokaż również archiwalne', false)
            ->assertSee('przełącznik jest nieaktywny', false)
            ->assertSee('Na zalogowanym `/transmisja`', false)
            ->assertSee('Teraz na osadzonym live', false)
            ->assertSee('Na czat ClickMeeting', false)
            ->assertSee('Kopiuj na czat', false)
            ->assertSee('Włącz materiały, ankietę, zaświadczenie albo ofertę', false)
            ->assertDontSee('Live bez logowania (gość)', false)
            ->getContent();
        $this->assertSwitchDisabled($html, 'live_bar_attendance_enabled', true);
        $this->assertSwitchDisabled($html, 'live_bar_certificate_enabled', true);
        $this->assertSwitchDisabled($html, 'live_bar_materials_enabled', true);
        $this->assertSwitchDisabled($html, 'live_bar_survey_enabled', true);

        $this->actingAs($user)
            ->get(route('courses.show', $course->id))
            ->assertOk()
            ->assertSee(route('courses.live', $course->id), false)
            ->assertSee('Panel live', false);

        $this->actingAs($user)
            ->get(route('participants.index', $course->id))
            ->assertOk()
            ->assertSee(route('courses.live', $course->id), false);
    }

    public function test_enabling_attendance_without_token_does_not_publish_link(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $this->attachOnlineDetails($course, embed: true);

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => true,
                'live_bar_certificate_enabled' => false,
                'live_bar_materials_enabled' => false,
                'live_bar_survey_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('state.flags.attendance', false)
            ->assertJsonPath('state.resources.attendance.ready', false)
            ->assertJsonPath('state.links', []);

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $course->id,
            'live_bar_attendance_enabled' => 0,
        ]);
    }

    public function test_switches_become_active_only_when_resources_exist(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $course->update([
            'certificate_registration_open' => true,
            'certificate_registration_token' => 'reg-live-token',
            'certificate_download_status' => 'download_enabled',
        ]);
        $this->attachOnlineDetails($course, embed: true);

        CourseFileLink::query()->create([
            'course_id' => $course->id,
            'url' => 'https://drive.google.com/file/test',
            'title' => 'Slajdy',
            'order' => 0,
        ]);

        CourseSurveyLink::query()->create([
            'course_id' => $course->id,
            'url' => 'https://forms.gle/test',
            'title' => 'Ankieta po szkoleniu',
            'is_active' => true,
            'order' => 0,
        ]);

        $html = $this->actingAs($user)
            ->get(route('courses.live', $course->id))
            ->assertOk()
            ->getContent();

        $this->assertSwitchDisabled($html, 'live_bar_attendance_enabled', true);
        $this->assertSwitchDisabled($html, 'live_bar_certificate_enabled', false);
        $this->assertSwitchDisabled($html, 'live_bar_materials_enabled', false);
        $this->assertSwitchDisabled($html, 'live_bar_survey_enabled', false);
    }

    public function test_closed_embed_shows_guest_live_url_and_keeps_attendance_on_the_gate_form(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $course->update([
            'category' => 'closed',
            'certificate_registration_open' => true,
            'certificate_registration_token' => 'reg-live-token',
        ]);
        $this->attachOnlineDetails($course, embed: true);

        $html = $this->actingAs($user)
            ->get(route('courses.live', $course->id))
            ->assertOk()
            ->assertSee('Live bez logowania (gość)', false)
            ->assertSee('/live/', false)
            ->assertSee('Maila z ADM jeszcze nie wysyłamy', false)
            ->assertSee('imię, nazwisko i e-mail', false)
            ->getContent();

        $this->assertSwitchDisabled($html, 'live_bar_attendance_enabled', true);

        $this->actingAs($user)
            ->get(route('courses.show', $course->id))
            ->assertOk()
            ->assertSee('Live bez logowania (gość)', false)
            ->assertSee('/live/', false);

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $course->id,
        ]);
        $this->assertNotNull($course->fresh()->onlineDetails?->guest_live_token);
    }

    public function test_open_embed_does_not_publish_guest_live_url(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $this->attachOnlineDetails($course, embed: true);

        $this->actingAs($user)
            ->get(route('courses.live', $course->id))
            ->assertOk()
            ->assertDontSee('Live bez logowania (gość)', false);

        $this->assertNull($course->fresh()->onlineDetails?->guest_live_token);
    }

    public function test_enabling_survey_without_active_survey_does_not_turn_flag_on(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $this->attachOnlineDetails($course, embed: true);

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => false,
                'live_bar_certificate_enabled' => false,
                'live_bar_materials_enabled' => false,
                'live_bar_survey_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('state.flags.survey', false)
            ->assertJsonPath('state.resources.survey.ready', false);

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $course->id,
            'live_bar_survey_enabled' => 0,
        ]);
    }

    public function test_enabling_certificate_without_download_status_does_not_turn_flag_on(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $this->attachOnlineDetails($course, embed: true);

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => false,
                'live_bar_certificate_enabled' => true,
                'live_bar_materials_enabled' => false,
                'live_bar_survey_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('state.flags.certificate', false)
            ->assertJsonPath('state.resources.certificate.ready', false)
            ->assertJsonPath('state.links', []);

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $course->id,
            'live_bar_certificate_enabled' => 0,
        ]);
    }

    public function test_certificate_link_appears_only_when_flag_and_download_status_are_set(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $course->update(['certificate_download_status' => 'download_enabled']);
        $this->attachOnlineDetails($course, embed: true);

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => false,
                'live_bar_certificate_enabled' => true,
                'live_bar_materials_enabled' => false,
                'live_bar_survey_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('state.links.0.key', 'certificate')
            ->assertJsonPath('state.links.0.label', 'Pobierz zaświadczenie')
            ->assertJsonPath('state.links.0.url', 'http://localhost:8081/dashboard/zaswiadczenia/'.$course->id);
    }

    public function test_attendance_link_stays_hidden_on_authenticated_embed_even_with_token(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $course->update([
            'certificate_registration_open' => true,
            'certificate_registration_token' => 'reg-live-token',
        ]);
        $this->attachOnlineDetails($course, embed: true);

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => true,
                'live_bar_certificate_enabled' => false,
                'live_bar_materials_enabled' => false,
                'live_bar_survey_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('state.flags.attendance', false)
            ->assertJsonPath('state.resources.attendance.ready', false)
            ->assertJsonPath('state.resources.attendance.parked', true)
            ->assertJsonPath('state.links', []);

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $course->id,
            'live_bar_attendance_enabled' => 0,
        ]);
    }

    public function test_materials_and_survey_are_independent_and_uncheck_hides_them(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $this->attachOnlineDetails($course, embed: true);

        CourseFileLink::query()->create([
            'course_id' => $course->id,
            'url' => 'https://drive.google.com/file/test',
            'title' => 'Slajdy',
            'order' => 0,
        ]);

        CourseSurveyLink::query()->create([
            'course_id' => $course->id,
            'url' => 'https://forms.gle/test',
            'title' => 'Ankieta po szkoleniu',
            'is_active' => true,
            'order' => 0,
        ]);

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => false,
                'live_bar_certificate_enabled' => false,
                'live_bar_materials_enabled' => true,
                'live_bar_survey_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('state.flags.materials', true)
            ->assertJsonPath('state.flags.survey', false)
            ->assertJsonPath('state.links.0.label', 'Pobierz materiały')
            ->assertJsonCount(1, 'state.links')
            ->assertJsonPath('state.cm_chat.empty', false)
            ->assertJsonPath('state.cm_chat.lines.0.url', 'https://drive.google.com/file/test')
            ->assertJsonPath('state.cm_chat.lines.0.label', 'MATERIAŁY')
            ->assertJsonPath('state.cm_chat.text', "MATERIAŁY: https://drive.google.com/file/test");

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => false,
                'live_bar_certificate_enabled' => false,
                'live_bar_materials_enabled' => true,
                'live_bar_survey_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonCount(2, 'state.links');

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => false,
                'live_bar_certificate_enabled' => false,
                'live_bar_materials_enabled' => false,
                'live_bar_survey_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('state.links', [])
            ->assertJsonPath('state.cm_chat.empty', true);
    }

    public function test_clickmeeting_chat_copy_includes_on_links_even_when_embed_is_off(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $this->attachOnlineDetails($course, embed: false);

        CourseFileLink::query()->create([
            'course_id' => $course->id,
            'url' => 'https://drive.google.com/file/cm-chat',
            'title' => 'Slajdy',
            'order' => 0,
        ]);

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => false,
                'live_bar_certificate_enabled' => false,
                'live_bar_materials_enabled' => true,
                'live_bar_survey_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('state.embed_on_pnedu', false)
            ->assertJsonPath('state.links', [])
            ->assertJsonPath('state.cm_chat.empty', false)
            ->assertJsonPath('state.cm_chat.lines.0.url', 'https://drive.google.com/file/cm-chat')
            ->assertJsonPath('state.cm_chat.lines.0.label', 'MATERIAŁY')
            ->assertJsonPath('state.cm_chat.text', 'MATERIAŁY: https://drive.google.com/file/cm-chat');

        $html = $this->actingAs($user)
            ->get(route('courses.live', $course->id))
            ->assertOk()
            ->assertSee('Na czat ClickMeeting', false)
            ->assertSee('https://drive.google.com/file/cm-chat', false)
            ->getContent();

        $this->assertStringContainsString('Kopiuj na czat', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="course-live-cm-chat"[^>]*\sdisabled/',
            $html
        );
    }

    public function test_links_stay_hidden_when_embed_is_off(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $course->update([
            'certificate_registration_open' => true,
            'certificate_registration_token' => 'reg-live-token',
        ]);
        $this->attachOnlineDetails($course, embed: false);

        $this->actingAs($user)
            ->patchJson(route('courses.live.update', $course->id), [
                'live_bar_attendance_enabled' => true,
                'live_bar_certificate_enabled' => false,
                'live_bar_materials_enabled' => false,
                'live_bar_survey_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('state.flags.attendance', false)
            ->assertJsonPath('state.resources.attendance.parked', true)
            ->assertJsonPath('state.embed_on_pnedu', false)
            ->assertJsonPath('state.links', []);
    }

    public function test_embed_entry_counts_use_last_entered_timestamp(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $this->attachOnlineDetails($course, embed: true);

        $recent = \App\Models\Participant::query()->create([
            'course_id' => $course->id,
            'first_name' => 'Anna',
            'last_name' => 'Teraz',
            'email' => 'anna.livebar@example.test',
            'order' => 1,
        ]);
        $older = \App\Models\Participant::query()->create([
            'course_id' => $course->id,
            'first_name' => 'Bartek',
            'last_name' => 'Wczoraj',
            'email' => 'bartek.livebar@example.test',
            'order' => 2,
        ]);

        ParticipantLiveAccess::query()->create([
            'participant_id' => $recent->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'embed_last_entered_at' => now()->subMinutes(3),
            'status' => 'success',
        ]);
        ParticipantLiveAccess::query()->create([
            'participant_id' => $older->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'embed_last_entered_at' => now()->subHours(2),
            'status' => 'success',
        ]);

        $this->actingAs($user)
            ->getJson(route('courses.live', $course->id))
            ->assertOk()
            ->assertJsonPath('embed_entries.ever', 2)
            ->assertJsonPath('embed_entries.recent_15min', 1);
    }

    public function test_online_now_lists_viewers_with_recent_heartbeat(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse();
        $this->attachOnlineDetails($course, embed: true);

        $online = Participant::query()->create([
            'course_id' => $course->id,
            'first_name' => 'Anna',
            'last_name' => 'Teraz',
            'email' => 'anna.now@example.test',
            'order' => 1,
        ]);
        $stale = Participant::query()->create([
            'course_id' => $course->id,
            'first_name' => 'Bartek',
            'last_name' => 'Wczoraj',
            'email' => 'bartek.now@example.test',
            'order' => 2,
        ]);

        ParticipantLiveAccess::query()->create([
            'participant_id' => $online->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'embed_last_entered_at' => now()->subMinutes(3),
            'embed_last_seen_at' => now()->subSeconds(20),
            'status' => 'success',
        ]);
        ParticipantLiveAccess::query()->create([
            'participant_id' => $stale->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'embed_last_entered_at' => now()->subMinutes(3),
            'embed_last_seen_at' => now()->subMinutes(10),
            'status' => 'success',
        ]);

        $this->actingAs($user)
            ->getJson(route('courses.live', $course->id))
            ->assertOk()
            ->assertJsonPath('online_now.count', 1)
            ->assertJsonPath('online_now.viewers.0.name', 'Anna Teraz')
            ->assertJsonPath('online_now.viewers.0.email', 'anna.now@example.test');
    }

    public function test_live_course_search_hides_archived_until_toggled_or_typed(): void
    {
        $user = User::factory()->create();
        $upcoming = $this->createOnlineCourse();
        $archivedStart = now()->subDays(10);
        $archived = Course::query()->create([
            'title' => 'Archiwalne live',
            'description' => 'Test',
            'start_date' => $archivedStart,
            'end_date' => $archivedStart->copy()->addHours(3),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);

        $this->actingAs($user)
            ->getJson(route('courses.live.search'))
            ->assertOk()
            ->assertJsonFragment(['id' => $upcoming->id, 'title_text' => 'Live panel test'])
            ->assertJsonMissing(['id' => $archived->id]);

        $this->actingAs($user)
            ->getJson(route('courses.live.search', ['include_archived' => '1']))
            ->assertOk()
            ->assertJsonFragment(['id' => $archived->id]);

        $this->actingAs($user)
            ->getJson(route('courses.live.search', ['q' => (string) $archived->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $archived->id, 'title_text' => 'Archiwalne live']);

        $this->actingAs($user)
            ->getJson(route('courses.live.search', ['exclude_id' => $upcoming->id]))
            ->assertOk()
            ->assertJsonMissing(['id' => $upcoming->id]);
    }

    public function test_live_offer_can_be_toggled_for_another_course_but_not_the_current_one(): void
    {
        $user = User::factory()->create();
        $live = $this->createOnlineCourse();
        $promo = Course::query()->create([
            'title' => 'Kolejne szkolenie oferta',
            'description' => 'Test',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(10)->addHours(3),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);
        $this->attachOnlineDetails($live, embed: true);

        $this->actingAs($user)
            ->patchJson(route('courses.live.offer', $live->id), [
                'live_offer_course_id' => $live->id,
                'live_offer_enabled' => true,
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->patchJson(route('courses.live.offer', $live->id), [
                'live_offer_course_id' => null,
                'live_offer_enabled' => true,
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->patchJson(route('courses.live.offer', $live->id), [
                'live_offer_course_id' => $promo->id,
                'live_offer_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('state.offer.enabled', true)
            ->assertJsonPath('state.offer.course_id', $promo->id)
            ->assertJsonPath('state.offer.course.title_text', 'Kolejne szkolenie oferta')
            ->assertJsonPath('state.cm_chat.empty', false)
            ->assertJsonPath('state.cm_chat.lines.0.label', 'SZKOLENIE')
            ->assertJsonPath('state.cm_chat.lines.0.url', 'http://localhost:8081/courses/'.$promo->id)
            ->assertJsonPath(
                'state.cm_chat.text',
                'SZKOLENIE: Kolejne szkolenie oferta ... http://localhost:8081/courses/'.$promo->id
            );

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $live->id,
            'live_offer_course_id' => $promo->id,
            'live_offer_enabled' => 1,
        ]);

        $this->assertNotNull(
            $live->fresh('onlineDetails')->onlineDetails->live_offer_enabled_at
        );

        $this->actingAs($user)
            ->patchJson(route('courses.live.offer', $live->id), [
                'live_offer_course_id' => $promo->id,
                'live_offer_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('state.offer.enabled', false)
            ->assertJsonPath('state.offer.course_id', $promo->id)
            ->assertJsonPath('state.offer.expires_at', null);

        $this->assertNull(
            $live->fresh('onlineDetails')->onlineDetails->live_offer_enabled_at
        );

        $this->actingAs($user)
            ->get(route('courses.live', $live->id))
            ->assertOk()
            ->assertSee('Kolejne szkolenie oferta', false)
            ->assertSee('Ukryta', false)
            ->assertDontSee('window.location.href = liveUrlFor', false);
    }

    public function test_live_offer_auto_hides_after_two_minutes(): void
    {
        $user = User::factory()->create();
        $live = $this->createOnlineCourse();
        $promo = Course::query()->create([
            'title' => 'Oferta auto hide',
            'description' => 'Test',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(10)->addHours(3),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);
        $this->attachOnlineDetails($live, embed: true);

        $this->actingAs($user)
            ->patchJson(route('courses.live.offer', $live->id), [
                'live_offer_course_id' => $promo->id,
                'live_offer_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('state.offer.enabled', true)
            ->assertJsonPath('state.offer.auto_hide_seconds', 120);

        $this->assertTrue(
            (bool) $live->fresh('onlineDetails')->onlineDetails->live_offer_auto_hide
        );
        $this->assertNotNull(
            $live->fresh('onlineDetails')->onlineDetails->live_offer_enabled_at
        );

        $this->travel(121)->seconds();

        $this->actingAs($user)
            ->getJson(route('courses.live', $live->id))
            ->assertOk()
            ->assertJsonPath('offer.enabled', false)
            ->assertJsonPath('offer.course_id', $promo->id);

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $live->id,
            'live_offer_enabled' => 0,
        ]);
        $this->assertNull($live->fresh('onlineDetails')->onlineDetails->live_offer_enabled_at);
    }

    public function test_live_offer_stays_on_when_auto_hide_is_disabled(): void
    {
        $user = User::factory()->create();
        $live = $this->createOnlineCourse();
        $promo = Course::query()->create([
            'title' => 'Oferta bez auto hide',
            'description' => 'Test',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(10)->addHours(3),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);
        $this->attachOnlineDetails($live, embed: true);

        $this->actingAs($user)
            ->patchJson(route('courses.live.offer', $live->id), [
                'live_offer_course_id' => $promo->id,
                'live_offer_enabled' => true,
                'live_offer_auto_hide' => false,
            ])
            ->assertOk()
            ->assertJsonPath('state.offer.enabled', true)
            ->assertJsonPath('state.offer.auto_hide', false)
            ->assertJsonPath('state.offer.expires_at', null);

        $this->assertFalse(
            (bool) $live->fresh('onlineDetails')->onlineDetails->live_offer_auto_hide
        );
        $this->assertNull($live->fresh('onlineDetails')->onlineDetails->live_offer_enabled_at);

        $this->travel(300)->seconds();

        $this->actingAs($user)
            ->getJson(route('courses.live', $live->id))
            ->assertOk()
            ->assertJsonPath('offer.enabled', true)
            ->assertJsonPath('offer.auto_hide', false);

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $live->id,
            'live_offer_enabled' => 1,
            'live_offer_auto_hide' => 0,
        ]);
    }

    private function createOnlineCourse(): Course
    {
        $start = now()->addDays(2);

        return Course::query()->create([
            'title' => 'Live panel test',
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(3),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);
    }

    private function assertSwitchDisabled(string $html, string $id, bool $disabled): void
    {
        $this->assertSame(
            1,
            preg_match('/<input[^>]*\sid="'.preg_quote($id, '/').'"[^>]*>/s', $html, $matches),
            "Brak przełącznika {$id}."
        );
        $tag = $matches[0];
        $isDisabled = (bool) preg_match('/\sdisabled(\s|=|>)/', $tag);
        $this->assertSame(
            $disabled,
            $isDisabled,
            $disabled
                ? "Przełącznik {$id} powinien być nieaktywny."
                : "Przełącznik {$id} powinien dać się włączyć."
        );
    }

    private function attachOnlineDetails(Course $course, bool $embed): CourseOnlineDetails
    {
        return CourseOnlineDetails::query()->create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10164812',
            'meeting_link' => 'https://pnedu.clickmeeting.com/test',
            'clickmeeting_join_enabled' => ! $embed,
            'embed_on_pnedu' => $embed,
            'embed_email_link_enabled' => true,
        ]);
    }
}
