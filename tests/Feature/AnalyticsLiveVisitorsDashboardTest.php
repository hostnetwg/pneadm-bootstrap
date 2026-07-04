<?php

namespace Tests\Feature;

use App\Models\Analytics\AnalyticsEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\Analytics\AnalyticsLiveVisitorsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsLiveVisitorsDashboardTest extends TestCase
{
    private array $createdUserIds = [];

    private array $createdRoleIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Brak tabel users/roles w testowej bazie adm.');
        }

        config()->set('analytics.live_visitors_dashboard.enabled', true);
        config()->set('analytics.live_visitors_dashboard.active_window_minutes', 5);
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

    public function test_guest_cannot_access_live_visitors_api(): void
    {
        $this->getJson(route('api.dashboard.live-visitors'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_live_visitors_snapshot(): void
    {
        $user = $this->userWithRole('manager');
        $sessionId = (string) Str::uuid();

        $this->createAnalyticsEvent([
            'event_name' => 'order_form_viewed',
            'analytics_session_id' => $sessionId,
            'course_title_snapshot' => 'Test Course',
            'occurred_at' => now('UTC'),
        ]);

        $this->actingAs($user)
            ->getJson(route('api.dashboard.live-visitors'))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('active_count', 1)
            ->assertJsonPath('visitors.0.session_short', '…'.substr($sessionId, -4))
            ->assertJsonPath('visitors.0.page_label', 'Formularz zamówienia')
            ->assertJsonPath('visitors.0.course_title', 'Test Course');
    }

    public function test_latest_event_per_session_is_used_for_page_label(): void
    {
        $user = $this->userWithRole('manager');
        $sessionId = (string) Str::uuid();

        $this->createAnalyticsEvent([
            'event_name' => 'course_description_viewed',
            'analytics_session_id' => $sessionId,
            'occurred_at' => now('UTC')->subMinutes(2),
        ]);
        $this->createAnalyticsEvent([
            'event_name' => 'order_form_started',
            'analytics_session_id' => $sessionId,
            'occurred_at' => now('UTC')->subMinute(),
        ]);

        $this->actingAs($user)
            ->getJson(route('api.dashboard.live-visitors'))
            ->assertOk()
            ->assertJsonPath('active_count', 1)
            ->assertJsonPath('visitors.0.page_label', 'Formularz — aktywny');
    }

    public function test_form_order_created_shows_order_submitted_label(): void
    {
        $user = $this->userWithRole('manager');
        $sessionId = (string) Str::uuid();

        $this->createAnalyticsEvent([
            'event_name' => 'form_order_created',
            'analytics_session_id' => $sessionId,
            'form_order_id' => 4242,
            'metadata' => ['order_flow' => 'deferred'],
            'occurred_at' => now('UTC'),
        ]);

        $this->actingAs($user)
            ->getJson(route('api.dashboard.live-visitors'))
            ->assertOk()
            ->assertJsonPath('visitors.0.page_label', 'Złożył zamówienie (odroczone)')
            ->assertJsonPath('visitors.0.form_order_id', 4242)
            ->assertJsonPath('visitors.0.form_order_url', route('form-orders.show', 4242));
    }

    public function test_form_order_created_overrides_earlier_funnel_events_in_session(): void
    {
        $user = $this->userWithRole('manager');
        $sessionId = (string) Str::uuid();

        $this->createAnalyticsEvent([
            'event_name' => 'order_form_submit_clicked',
            'analytics_session_id' => $sessionId,
            'occurred_at' => now('UTC')->subMinute(),
        ]);
        $this->createAnalyticsEvent([
            'event_name' => 'form_order_created',
            'analytics_session_id' => $sessionId,
            'form_order_id' => 99,
            'metadata' => ['order_flow' => 'online'],
            'occurred_at' => now('UTC'),
        ]);

        $this->actingAs($user)
            ->getJson(route('api.dashboard.live-visitors'))
            ->assertOk()
            ->assertJsonPath('visitors.0.page_label', 'Złożył zamówienie (online)');
    }

    public function test_page_label_mapping_for_form_order_created(): void
    {
        $service = app(AnalyticsLiveVisitorsService::class);
        $event = new AnalyticsEvent([
            'event_name' => 'form_order_created',
            'metadata' => ['order_flow' => 'online'],
        ]);

        $this->assertSame('Złożył zamówienie (online)', $service->pageLabel($event));
    }

    public function test_page_label_mapping_for_course_description(): void
    {
        $service = app(AnalyticsLiveVisitorsService::class);
        $event = new AnalyticsEvent([
            'event_name' => 'course_description_viewed',
            'landing_target' => 'course_description',
        ]);

        $this->assertSame('Opis szkolenia', $service->pageLabel($event));
    }

    public function test_live_visitors_api_returns_not_found_when_disabled(): void
    {
        config()->set('analytics.live_visitors_dashboard.enabled', false);
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->getJson(route('api.dashboard.live-visitors'))
            ->assertNotFound();
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
            'event_name' => 'course_description_viewed',
            'event_category' => 'landing',
            'occurred_at' => now('UTC'),
            'app_source' => 'pnedu',
            'analytics_session_id' => (string) Str::uuid(),
            'order_form_session_id' => null,
            'course_id' => 1,
            'course_title_snapshot' => 'Snapshot',
            'campaign_code' => null,
            'landing_target' => 'course_description',
            'route_name' => 'courses.show',
            'path' => '/courses/1',
            'referrer_domain' => null,
            'device_type' => 'desktop',
            'browser_family' => 'chrome',
            'metadata' => [],
            'created_at' => now('UTC'),
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
            $table->unsignedBigInteger('form_order_id')->nullable()->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->string('course_title_snapshot', 255)->nullable();
            $table->string('campaign_code', 100)->nullable()->index();
            $table->string('landing_target', 50)->nullable()->index();
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
