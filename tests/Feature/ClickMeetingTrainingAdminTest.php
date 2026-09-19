<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\Participant;
use App\Models\ParticipantLiveAccess;
use App\Models\User;
use App\Services\FormOrderPneduProvisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClickMeetingTrainingAdminTest extends TestCase
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

    public function test_trainings_list_marks_existing_course_and_hides_add_button(): void
    {
        $user = User::factory()->create();
        $linkedCourse = $this->createOnlineCourse('Szkolenie już w ADM', '2026-10-02 10:00:00');
        CourseOnlineDetails::create([
            'course_id' => $linkedCourse->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088702',
            'meeting_link' => 'https://pnedu.clickmeeting.com/istniejace',
        ]);

        Http::fake(function ($request) {
            $url = rtrim($request->url(), '/');
            if ($url === 'https://api.clickmeeting.com/v1/conferences') {
                return Http::response([
                    'active_conferences' => [],
                    'scheduled_conferences' => [
                        [
                            'id' => 10088701,
                            'name' => 'Nowe szkolenie CM',
                            'starts_at' => '2026-10-01T10:00:00+02:00',
                            'room_pin' => 111,
                            'room_type' => 'webinar',
                            'status' => 'active',
                        ],
                        [
                            'id' => 10088702,
                            'name' => 'Istniejące szkolenie CM',
                            'starts_at' => '2026-10-02T10:00:00+02:00',
                            'room_pin' => 222,
                            'room_type' => 'webinar',
                            'status' => 'active',
                            'room_url' => 'https://pnedu.clickmeeting.com/istniejace/',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $response = $this->actingAs($user)->get(route('clickmeeting.trainings.index'));

        $response->assertOk()
            ->assertSee('Nowe szkolenie CM')
            ->assertSee('Istniejące szkolenie CM')
            ->assertSee('W courses')
            ->assertSee('Szkolenie już w ADM')
            ->assertSee(route('clickmeeting.trainings.create-course', 10088701), false)
            ->assertDontSee(route('clickmeeting.trainings.create-course', 10088702), false)
            ->assertSee(route('courses.edit', $linkedCourse->id), false)
            ->assertDontSee('Aktualizuj link z ClickMeeting')
            ->assertDontSee('Link nieaktualny')
            ->assertDontSee('Termin się różni')
            ->assertSee('Start w courses:');
    }

    public function test_trainings_list_shows_access_type_and_warns_when_closed_is_not_open(): void
    {
        $user = User::factory()->create();
        $closedCourse = $this->createOnlineCourse('Szkolenie zamknięte CM', '2026-10-02 10:00:00', 'closed');
        CourseOnlineDetails::create([
            'course_id' => $closedCourse->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088702',
            'meeting_link' => 'https://pnedu.clickmeeting.com/zamkniete',
        ]);
        $openCourse = $this->createOnlineCourse('Szkolenie otwarte CM', '2026-10-01 10:00:00', 'open');
        CourseOnlineDetails::create([
            'course_id' => $openCourse->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088701',
            'meeting_link' => 'https://pnedu.clickmeeting.com/otwarte',
        ]);

        Http::fake(function ($request) {
            $url = rtrim($request->url(), '/');
            if ($url === 'https://api.clickmeeting.com/v1/conferences') {
                return Http::response([
                    'active_conferences' => [],
                    'scheduled_conferences' => [
                        [
                            'id' => 10088701,
                            'name' => 'Otwarte CM',
                            'starts_at' => '2026-10-01T10:00:00+02:00',
                            'room_pin' => 111,
                            'room_type' => 'webinar',
                            'status' => 'active',
                            'access_type' => 1,
                            'room_url' => 'https://pnedu.clickmeeting.com/otwarte',
                        ],
                        [
                            'id' => 10088702,
                            'name' => 'Zamknięte CM',
                            'starts_at' => '2026-10-02T10:00:00+02:00',
                            'room_pin' => 222,
                            'room_type' => 'webinar',
                            'status' => 'active',
                            'access_type' => 3,
                            'room_url' => 'https://pnedu.clickmeeting.com/zamkniete',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->get(route('clickmeeting.trainings.index'))
            ->assertOk()
            ->assertSee('Dostęp CM')
            ->assertSee('Dla wszystkich')
            ->assertSee('Tokeny')
            ->assertSee('Zamknięte ≠ dla wszystkich')
            ->assertSee('Szkolenie zamknięte wymaga w ClickMeeting dostępu „Dla wszystkich”')
            ->assertSee('Dostęp CM do zmiany');
    }

    public function test_trainings_list_reconciles_live_access_type_in_background(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse('Snapshot access type', '2026-10-02 10:00:00');
        CourseOnlineDetails::create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088702',
            'meeting_link' => 'https://pnedu.clickmeeting.com/istniejace',
        ]);
        $participant = Participant::query()->create([
            'course_id' => $course->id,
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna.access-type@example.test',
            'order' => 1,
        ]);
        ParticipantLiveAccess::query()->create([
            'participant_id' => $participant->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10088702',
            'room_url' => 'https://pnedu.clickmeeting.com/istniejace',
            'token' => 'OLDTOK',
            'access_type' => 3,
            'status' => 'success',
            'synced_at' => now()->subDay(),
        ]);

        Http::fake(function ($request) {
            $url = rtrim($request->url(), '/');
            if ($url === 'https://api.clickmeeting.com/v1/conferences') {
                return Http::response([
                    'active_conferences' => [],
                    'scheduled_conferences' => [
                        [
                            'id' => 10088702,
                            'name' => 'Istniejące szkolenie CM',
                            'starts_at' => '2026-10-02T10:00:00+02:00',
                            'room_pin' => 222,
                            'room_type' => 'webinar',
                            'status' => 'active',
                            'access_type' => 1,
                            'room_url' => 'https://pnedu.clickmeeting.com/istniejace/',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->get(route('clickmeeting.trainings.index'))
            ->assertOk()
            ->assertSee('Dla wszystkich');

        $this->assertDatabaseHas('participant_live_access', [
            'participant_id' => $participant->id,
            'access_type' => 1,
        ]);
    }

    public function test_trainings_list_marks_stale_meeting_link(): void
    {
        $user = User::factory()->create();
        $linkedCourse = $this->createOnlineCourse('Szkolenie ze starym linkiem');
        CourseOnlineDetails::create([
            'course_id' => $linkedCourse->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088702',
            'meeting_link' => 'https://pnedu.clickmeeting.com/stary-slug',
        ]);

        Http::fake(function ($request) {
            $url = rtrim($request->url(), '/');
            if ($url === 'https://api.clickmeeting.com/v1/conferences') {
                return Http::response([
                    'active_conferences' => [],
                    'scheduled_conferences' => [
                        [
                            'id' => 10088702,
                            'name' => 'Istniejące szkolenie CM',
                            'starts_at' => '2026-10-02T10:00:00+02:00',
                            'room_url' => 'https://pnedu.clickmeeting.com/nowy-slug',
                            'status' => 'active',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->get(route('clickmeeting.trainings.index'))
            ->assertOk()
            ->assertSee('Link nieaktualny')
            ->assertSee('Aktualizuj link z ClickMeeting');
    }

    public function test_trainings_list_warns_when_course_start_differs_from_clickmeeting(): void
    {
        $user = User::factory()->create();
        $linkedCourse = $this->createOnlineCourse('Szkolenie z innym terminem', '2026-10-03 09:00:00');
        CourseOnlineDetails::create([
            'course_id' => $linkedCourse->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088702',
            'meeting_link' => 'https://pnedu.clickmeeting.com/istniejace',
        ]);

        Http::fake(function ($request) {
            $url = rtrim($request->url(), '/');
            if ($url === 'https://api.clickmeeting.com/v1/conferences') {
                return Http::response([
                    'active_conferences' => [],
                    'scheduled_conferences' => [
                        [
                            'id' => 10088702,
                            'name' => 'Istniejące szkolenie CM',
                            'starts_at' => '2026-10-02T10:00:00+02:00',
                            'room_url' => 'https://pnedu.clickmeeting.com/istniejace/',
                            'status' => 'active',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->get(route('clickmeeting.trainings.index'))
            ->assertOk()
            ->assertSee('Termin się różni')
            ->assertSee('Start w courses:')
            ->assertSee('03.10.2026');
    }

    public function test_sync_room_url_updates_course_and_live_access(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse('Do sync');
        CourseOnlineDetails::create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088709',
            'meeting_link' => 'https://pnedu.clickmeeting.com/stary',
        ]);

        $participant = \App\Models\Participant::query()->create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna.sync@example.com',
        ]);

        \App\Models\ParticipantLiveAccess::query()->create([
            'participant_id' => $participant->id,
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10088709',
            'room_url' => 'https://pnedu.clickmeeting.com/stary',
            'status' => 'success',
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.clickmeeting.com/v1/conferences/10088709') {
                return Http::response([
                    'conference' => [
                        'id' => 10088709,
                        'room_url' => 'https://pnedu.clickmeeting.com/nowy/',
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->post(route('clickmeeting.trainings.sync-room-url', 10088709))
            ->assertRedirect(route('clickmeeting.trainings.index'));

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $course->id,
            'meeting_link' => 'https://pnedu.clickmeeting.com/nowy',
        ]);
        $this->assertDatabaseHas('participant_live_access', [
            'participant_id' => $participant->id,
            'room_url' => 'https://pnedu.clickmeeting.com/nowy',
        ]);
    }

    public function test_course_edit_sync_route_updates_meeting_link(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse('Edycja sync');
        CourseOnlineDetails::create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088710',
            'meeting_link' => 'https://pnedu.clickmeeting.com/przed',
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.clickmeeting.com/v1/conferences/10088710') {
                return Http::response([
                    'conference' => [
                        'id' => 10088710,
                        'room_url' => 'https://pnedu.clickmeeting.com/po',
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->from(route('courses.edit', $course->id))
            ->post(route('courses.sync-clickmeeting-room-url', $course->id))
            ->assertRedirect(route('courses.edit', $course->id));

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $course->id,
            'meeting_link' => 'https://pnedu.clickmeeting.com/po',
        ]);
    }

    public function test_course_edit_shows_sync_button_only_when_meeting_link_is_stale(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse('Edycja rozjazd');
        CourseOnlineDetails::create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088711',
            'meeting_link' => 'https://pnedu.clickmeeting.com/stary-edit',
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.clickmeeting.com/v1/conferences/10088711') {
                return Http::response([
                    'conference' => [
                        'id' => 10088711,
                        'room_url' => 'https://pnedu.clickmeeting.com/nowy-edit',
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->get(route('courses.edit', $course->id))
            ->assertOk()
            ->assertSee('Link nieaktualny')
            ->assertSee('Aktualizuj link z ClickMeeting');
    }

    public function test_course_edit_hides_sync_button_when_meeting_link_matches(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse('Edycja zgodna');
        CourseOnlineDetails::create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088712',
            'meeting_link' => 'https://pnedu.clickmeeting.com/zgodny',
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.clickmeeting.com/v1/conferences/10088712') {
                return Http::response([
                    'conference' => [
                        'id' => 10088712,
                        'room_url' => 'https://pnedu.clickmeeting.com/zgodny/',
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->get(route('courses.edit', $course->id))
            ->assertOk()
            ->assertDontSee('Aktualizuj link z ClickMeeting')
            ->assertDontSee('Link nieaktualny');
    }

    public function test_course_edit_warns_when_closed_course_is_not_open_in_clickmeeting(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse('Edycja zamknięte', category: 'closed');
        CourseOnlineDetails::create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088714',
            'meeting_link' => 'https://pnedu.clickmeeting.com/zamkniete-edit',
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.clickmeeting.com/v1/conferences/10088714') {
                return Http::response([
                    'conference' => [
                        'id' => 10088714,
                        'access_type' => 3,
                        'room_url' => 'https://pnedu.clickmeeting.com/zamkniete-edit',
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->get(route('courses.edit', $course->id))
            ->assertOk()
            ->assertSee('Dostęp w ClickMeeting:')
            ->assertSee('Tokeny')
            ->assertSee('Szkolenie zamknięte: w ClickMeeting ustaw dostęp „Dla wszystkich”');
    }

    public function test_create_course_form_is_prefilled_from_clickmeeting(): void
    {
        $user = User::factory()->create();

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.clickmeeting.com/v1/conferences/10088701') {
                return Http::response([
                    'conference' => [
                        'id' => 10088701,
                        'name' => 'Prefill z ClickMeeting',
                        'starts_at' => '2026-10-01T08:00:00+00:00',
                        'ends_at' => '2026-10-01T11:00:00+00:00',
                        'room_url' => 'https://pnedu.clickmeeting.com/prefill-test',
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $response = $this->actingAs($user)
            ->get(route('clickmeeting.trainings.create-course', 10088701));

        $response->assertOk()
            ->assertSee('Dodaj szkolenie z ClickMeeting')
            ->assertSee('Tworzenie szkolenia z ClickMeeting')
            ->assertSee('Prefill z ClickMeeting')
            ->assertSee('value="2026-10-01T10:00"', false)
            ->assertSee('value="2026-10-01T13:00"', false)
            ->assertSee('value="ClickMeeting"', false)
            ->assertSee('value="10088701"', false)
            ->assertSee('https://pnedu.clickmeeting.com/prefill-test')
            ->assertSee('id="live_room_mode_embed"', false)
            ->assertSee('Płatne')
            ->assertSee('Otwarte');

        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/id="live_room_mode_embed"[^>]*checked|checked[^>]*id="live_room_mode_embed"/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="show_on_pnedu"[^>]*\bchecked\b/s',
            $html
        );
    }

    public function test_create_course_form_leaves_end_date_empty_when_api_has_no_end(): void
    {
        $user = User::factory()->create();

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.clickmeeting.com/v1/conferences/10088703') {
                return Http::response([
                    'conference' => [
                        'id' => 10088703,
                        'name' => 'Bez daty końca',
                        'starts_at' => '2026-11-01T09:00:00+01:00',
                        'room_url' => 'https://pnedu.clickmeeting.com/bez-konca',
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $response = $this->actingAs($user)
            ->get(route('clickmeeting.trainings.create-course', 10088703));

        $response->assertOk()
            ->assertSee('Bez daty końca')
            ->assertSee('value="2026-11-01T09:00"', false);

        $this->assertMatchesRegularExpression(
            '/id="end_date"[^>]*value=""/s',
            $response->getContent()
        );
    }

    public function test_create_course_redirects_to_existing_course_when_event_already_linked(): void
    {
        $user = User::factory()->create();
        $course = $this->createOnlineCourse('Powiązane szkolenie');
        CourseOnlineDetails::create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088704',
        ]);

        $this->actingAs($user)
            ->get(route('clickmeeting.trainings.create-course', 10088704))
            ->assertRedirect(route('courses.edit', $course->id));
    }

    public function test_storing_prefilled_clickmeeting_course_keeps_event_data(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.clickmeeting.com/v1/conferences/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->actingAs($user)->post(route('courses.store'), [
            'title' => 'Szkolenie z ClickMeeting',
            'description' => '',
            'start_date' => '2026-10-01T10:00',
            'end_date' => '2026-10-01T13:00',
            'is_paid' => '1',
            'type' => 'online',
            'category' => 'open',
            'show_on_pnedu' => '0',
            'platform' => 'ClickMeeting',
            'meeting_link' => 'https://pnedu.clickmeeting.com/prefill-test',
            'clickmeeting_event_id' => '10088701',
            'live_room_mode' => 'embed_pnedu',
            'embed_email_link_enabled' => '1',
            'save_action' => 'close',
        ]);

        $response->assertRedirect(route('courses.index'));

        $course = Course::query()->where('title', 'Szkolenie z ClickMeeting')->first();
        $this->assertNotNull($course);
        $this->assertSame(1, (int) $course->is_paid);
        $this->assertSame('open', $course->category);
        $this->assertSame('online', $course->type);
        $this->assertFalse((bool) $course->show_on_pnedu);

        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'meeting_link' => 'https://pnedu.clickmeeting.com/prefill-test',
            'clickmeeting_event_id' => '10088701',
            'embed_on_pnedu' => 1,
            'clickmeeting_join_enabled' => 0,
        ]);
    }

    public function test_provision_access_email_preview_refreshes_stale_room_url(): void
    {
        $course = $this->createOnlineCourse('Provision preview sync');
        CourseOnlineDetails::create([
            'course_id' => $course->id,
            'platform' => 'ClickMeeting',
            'clickmeeting_event_id' => '10088713',
            'meeting_link' => 'https://pnedu.clickmeeting.com/stary-provision',
            'embed_on_pnedu' => true,
            'embed_email_link_enabled' => true,
        ]);

        $participant = Participant::query()->create([
            'course_id' => $course->id,
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna.provision-sync@example.test',
            'order' => 1,
        ]);

        $order = FormOrder::query()->create([
            'product_id' => $course->id,
            'product_name' => $course->title,
            'status_completed' => 0,
            'orderer_email' => $participant->email,
            'pnedu_provisioned_at' => now(),
        ]);

        FormOrderParticipant::query()->create([
            'form_order_id' => $order->id,
            'participant_firstname' => $participant->first_name,
            'participant_lastname' => $participant->last_name,
            'participant_email' => $participant->email,
            'is_primary' => true,
            'participant_id' => $participant->id,
        ]);

        ParticipantLiveAccess::query()->create([
            'participant_id' => $participant->id,
            'course_id' => $course->id,
            'form_order_id' => $order->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10088713',
            'access_type' => 1,
            'room_url' => 'https://pnedu.clickmeeting.com/stary-provision',
            'status' => 'success',
            'synced_at' => now(),
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.clickmeeting.com/v1/conferences/10088713') {
                return Http::response([
                    'conference' => [
                        'id' => 10088713,
                        'room_url' => 'https://pnedu.clickmeeting.com/nowy-provision',
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$request->url()], 500);
        });

        $preview = app(FormOrderPneduProvisionService::class)
            ->previewProvisionAccessEmail($order->id);

        $this->assertTrue($preview['success'] ?? false, $preview['error'] ?? 'preview failed');
        $this->assertStringContainsString(
            'https://pnedu.clickmeeting.com/nowy-provision',
            (string) ($preview['body_html'] ?? $preview['body'] ?? '')
        );
        $this->assertDatabaseHas('course_online_details', [
            'course_id' => $course->id,
            'meeting_link' => 'https://pnedu.clickmeeting.com/nowy-provision',
        ]);
        $this->assertDatabaseHas('participant_live_access', [
            'participant_id' => $participant->id,
            'room_url' => 'https://pnedu.clickmeeting.com/nowy-provision',
        ]);
    }

    private function createOnlineCourse(string $title, ?string $startDate = null, string $category = 'open'): Course
    {
        $start = $startDate ? \Carbon\Carbon::parse($startDate) : now()->addDays(7);

        return Course::create([
            'title' => $title,
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(3),
            'is_paid' => true,
            'type' => 'online',
            'category' => $category,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);
    }
}
