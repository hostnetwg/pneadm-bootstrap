<?php

namespace Tests\Feature;

use App\Models\Analytics\AnalyticsEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsDebugEventPanelTest extends TestCase
{
    private array $createdUserIds = [];

    private array $createdRoleIds = [];

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputBufferLevel = ob_get_level();

        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Brak tabel users/roles w testowej bazie adm.');
        }

        config()->set('analytics.debug_panel.enabled', true);
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createAnalyticsEventsTable();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        if ($this->createdUserIds !== []) {
            User::query()
                ->withTrashed()
                ->whereIn('id', $this->createdUserIds)
                ->forceDelete();
        }

        if ($this->createdRoleIds !== []) {
            Role::query()
                ->whereIn('id', $this->createdRoleIds)
                ->delete();
        }

        parent::tearDown();
    }

    public function test_guest_cannot_access_debug_panel(): void
    {
        $this->get(route('analytics.debug-events.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_admin_role_cannot_access_debug_panel(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->get(route('analytics.debug-events.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_debug_panel_and_it_reads_events_from_analytics_connection(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEvent([
            'event_name' => 'course_description_viewed',
            'campaign_code' => 'debug-campaign',
            'course_id' => 123,
            'course_title_snapshot' => 'Debug Course',
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index'))
            ->assertOk()
            ->assertSee('course_description_viewed')
            ->assertSee('debug-campaign')
            ->assertSee('Panel techniczny')
            ->assertSee('Wątki sesji');

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index', ['layout' => 'flat']))
            ->assertOk()
            ->assertSee('Debug Course');
    }

    public function test_debug_panel_threads_view_groups_events_by_session_and_shows_journey(): void
    {
        $admin = $this->userWithRole('admin');
        $sessionId = (string) Str::uuid();

        $this->createAnalyticsEvent([
            'event_name' => 'campaign_short_link_visit',
            'analytics_session_id' => $sessionId,
            'referrer_domain' => 'facebook.com',
            'campaign_code' => 'thread-campaign',
            'path' => '/c/thread',
            'occurred_at' => now('UTC')->subMinutes(3),
        ]);
        $this->createAnalyticsEvent([
            'event_name' => 'course_description_viewed',
            'analytics_session_id' => $sessionId,
            'path' => '/courses/10',
            'occurred_at' => now('UTC')->subMinutes(2),
        ]);
        $this->createAnalyticsEvent([
            'event_name' => 'order_form_viewed',
            'analytics_session_id' => $sessionId,
            'path' => '/courses/10/order',
            'occurred_at' => now('UTC')->subMinute(),
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index', ['layout' => 'threads']))
            ->assertOk()
            ->assertSee('Wątki sesji')
            ->assertSee('Skąd wszedł')
            ->assertSee('facebook.com')
            ->assertSee('thread-campaign')
            ->assertSee('Link kampanii')
            ->assertSee('Opis szkolenia')
            ->assertSee('Formularz zamówienia')
            ->assertSee('Tylko ta sesja');
    }

    public function test_event_name_campaign_code_and_course_id_filters_work(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEvent([
            'event_name' => 'course_description_viewed',
            'campaign_code' => 'keep-me',
            'course_id' => 10,
        ]);
        $this->createAnalyticsEvent([
            'event_name' => 'order_form_viewed',
            'campaign_code' => 'skip-me',
            'course_id' => 20,
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index', [
                'event_name' => 'course_description_viewed',
                'campaign_code' => 'keep-me',
                'course_id' => 10,
            ]))
            ->assertOk()
            ->assertSee('keep-me')
            ->assertSee('course_description_viewed')
            ->assertDontSee('skip-me');
    }

    public function test_session_filters_work(): void
    {
        $admin = $this->userWithRole('admin');
        $analyticsSessionId = (string) Str::uuid();
        $orderFormSessionId = (string) Str::uuid();

        $this->createAnalyticsEvent([
            'event_name' => 'order_form_viewed',
            'analytics_session_id' => $analyticsSessionId,
            'order_form_session_id' => $orderFormSessionId,
            'campaign_code' => 'matching-session',
        ]);
        $this->createAnalyticsEvent([
            'event_name' => 'order_form_viewed',
            'analytics_session_id' => (string) Str::uuid(),
            'order_form_session_id' => (string) Str::uuid(),
            'campaign_code' => 'other-session',
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index', [
                'analytics_session_id' => $analyticsSessionId,
                'order_form_session_id' => $orderFormSessionId,
            ]))
            ->assertOk()
            ->assertSee('matching-session')
            ->assertDontSee('other-session');
    }

    public function test_panel_redacts_raw_url_and_referrer_and_warns_about_forbidden_metadata_keys(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEvent([
            'event_name' => 'course_description_viewed',
            'metadata' => [
                'raw_url' => 'https://pnedu.pl/courses/1?email=secret@example.com',
                'raw_referrer' => 'https://facebook.com/post?fbclid=secret',
                'safe_flag' => 'ok',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index', ['layout' => 'flat']))
            ->assertOk()
            ->assertSee('Niedozwolone klucze')
            ->assertSee('raw_url')
            ->assertSee('raw_referrer')
            ->assertSee('safe_flag')
            ->assertDontSee('https://pnedu.pl/courses/1?email=secret@example.com', false)
            ->assertDontSee('https://facebook.com/post?fbclid=secret', false)
            ->assertDontSee('secret@example.com', false);
    }

    public function test_debug_panel_disabled_returns_not_found(): void
    {
        config()->set('analytics.debug_panel.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index'))
            ->assertNotFound();
    }

    public function test_flat_layout_still_shows_event_table(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEvent([
            'event_name' => 'order_form_viewed',
            'campaign_code' => 'flat-layout',
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index', ['layout' => 'flat']))
            ->assertOk()
            ->assertSee('Lista płaska')
            ->assertSee('order_form_viewed')
            ->assertSee('flat-layout');
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->first();
        if (! $role) {
            $role = Role::query()->create([
                'name' => $roleName,
                'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
                'is_system' => true,
                'level' => $roleName === 'admin' ? 3 : 2,
            ]);
            $this->createdRoleIds[] = $role->id;
        }

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createAnalyticsEvent(array $overrides = []): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create(array_merge([
            'event_uuid' => (string) Str::uuid(),
            'event_name' => 'campaign_short_link_visit',
            'event_category' => 'campaign',
            'occurred_at' => now(),
            'app_source' => 'pnedu',
            'analytics_session_id' => (string) Str::uuid(),
            'order_form_session_id' => null,
            'course_id' => null,
            'course_title_snapshot' => null,
            'campaign_code' => null,
            'landing_target' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'route_name' => 'courses.show',
            'path' => '/courses/1',
            'referrer_domain' => 'facebook.com',
            'device_type' => 'desktop',
            'browser_family' => 'chrome',
            'metadata' => [],
            'created_at' => now(),
        ], $overrides));
    }

    private function createAnalyticsEventsTable(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_events');
        Schema::connection('analytics')->create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_name', 100)->index();
            $table->string('event_category', 50)->index();
            $table->timestamp('occurred_at')->index();
            $table->string('app_source', 32)->index();
            $table->uuid('analytics_session_id')->nullable()->index();
            $table->uuid('order_form_session_id')->nullable()->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->string('course_title_snapshot', 255)->nullable();
            $table->string('campaign_code', 100)->nullable()->index();
            $table->string('landing_target', 50)->nullable()->index();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('route_name', 150)->nullable()->index();
            $table->string('path', 500)->nullable();
            $table->string('referrer_domain', 255)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('browser_family', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }
}
