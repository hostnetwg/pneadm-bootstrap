<?php

namespace Tests\Feature;

use App\Models\Analytics\AnalyticsDailyCampaignRevenueStat;
use App\Models\Analytics\AnalyticsDailyCourseRevenueStat;
use App\Models\Analytics\AnalyticsEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsRevenueDashboardTest extends TestCase
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

        config()->set('analytics.revenue_dashboard.enabled', true);
        config()->set('analytics.revenue_dashboard.timezone', 'Europe/Warsaw');
        config()->set('analytics.revenue_dashboard.default_days', 14);
        config()->set('analytics.revenue_dashboard.max_days', 366);
        config()->set('analytics.revenue.aggregation_lag_days', 1);
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createRevenueTables();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        if ($this->createdUserIds !== []) {
            User::query()->withTrashed()->whereIn('id', $this->createdUserIds)->forceDelete();
        }

        if ($this->createdRoleIds !== []) {
            Role::query()->whereIn('id', $this->createdRoleIds)->delete();
        }

        parent::tearDown();
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('analytics.revenue.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->get(route('analytics.revenue.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_dashboard_with_empty_state_when_no_data(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.revenue.index'))
            ->assertOk()
            ->assertSee('Rozliczenia')
            ->assertSee('Brak danych')
            ->assertSee('Model dat');
    }

    public function test_admin_sees_summary_kpis_for_test_data(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Szkolenie testowe', [
            'orders_created' => 5,
            'ordered_revenue_gross' => 500.00,
            'online_paid_orders' => 3,
            'online_paid_revenue_gross' => 300.00,
            'deferred_invoiced_orders' => 1,
            'deferred_invoiced_revenue_gross' => 100.00,
            'settled_orders_total' => 4,
            'settled_revenue_gross' => 400.00,
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.revenue.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ]))
            ->assertOk()
            ->assertSee('Zamówione')
            ->assertSee('Opłacone online')
            ->assertSee('Zafakturowane odroczone')
            ->assertSee('Rozliczone łącznie')
            ->assertSee('Szkolenie testowe');
    }

    public function test_course_rows_are_summed_over_date_range(): void
    {
        $this->seedCourse('2026-06-10', 200, 'Kurs 200', [
            'orders_created' => 2,
            'ordered_revenue_gross' => 200.00,
            'settled_orders_total' => 1,
            'settled_revenue_gross' => 100.00,
        ]);
        $this->seedCourse('2026-06-11', 200, 'Kurs 200', [
            'orders_created' => 3,
            'ordered_revenue_gross' => 300.00,
            'settled_orders_total' => 2,
            'settled_revenue_gross' => 200.00,
        ]);

        $service = app(\App\Services\Analytics\AnalyticsRevenueDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-01', 'date_to' => '2026-06-30']);

        $this->assertSame(5, $data['summary']['orders_created']);
        $this->assertSame(500.0, $data['summary']['ordered_revenue_gross']);
        $this->assertCount(1, $data['courses']);
        $this->assertSame(5, $data['courses'][0]['orders_created']);
        $this->assertSame(3, $data['courses'][0]['settled_orders_total']);
    }

    public function test_campaign_rows_are_summed_over_date_range(): void
    {
        $this->seedCampaign('2026-06-10', 'promo-a', [
            'orders_created' => 1,
            'ordered_revenue_gross' => 100.00,
            'campaign_id' => 10,
        ]);
        $this->seedCampaign('2026-06-11', 'promo-a', [
            'orders_created' => 2,
            'ordered_revenue_gross' => 200.00,
            'campaign_id' => 10,
        ]);

        $service = app(\App\Services\Analytics\AnalyticsRevenueDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'campaign_code' => 'promo-a']);

        $this->assertCount(1, $data['campaigns']);
        $this->assertSame(3, $data['campaigns'][0]['orders_created']);
        $this->assertSame(300.0, $data['campaigns'][0]['ordered_revenue_gross']);
    }

    public function test_course_filter_limits_course_table(): void
    {
        $this->seedCourse('2026-06-15', 101, 'Kurs A', ['orders_created' => 1]);
        $this->seedCourse('2026-06-15', 102, 'Kurs B', ['orders_created' => 9]);

        $service = app(\App\Services\Analytics\AnalyticsRevenueDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'course_id' => 101]);

        $this->assertSame(1, $data['summary']['orders_created']);
        $this->assertCount(1, $data['courses']);
        $this->assertSame(101, $data['courses'][0]['course_id']);
    }

    public function test_period_comparison_shows_delta_against_previous_period(): void
    {
        $this->seedCourse('2026-06-03', 100, 'Poprzedni', ['orders_created' => 2, 'settled_revenue_gross' => 200.00]);
        $this->seedCourse('2026-06-10', 100, 'Bieżący', ['orders_created' => 5, 'settled_revenue_gross' => 500.00]);

        $service = app(\App\Services\Analytics\AnalyticsRevenueDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-10', 'date_to' => '2026-06-16']);

        $this->assertSame('2026-06-03', $data['comparison']['previous_period']['date_from']);
        $this->assertSame('2026-06-09', $data['comparison']['previous_period']['date_to']);
        $this->assertSame(5.0, $data['comparison']['metrics']['orders_created']['current']);
        $this->assertSame(2.0, $data['comparison']['metrics']['orders_created']['previous']);
        $this->assertSame(3.0, $data['comparison']['metrics']['orders_created']['delta']);
    }

    public function test_dashboard_contains_no_pii(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Kurs', [
            'orders_created' => 1,
            'orders_created_without_campaign' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('analytics.revenue.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ]))
            ->assertOk();

        $body = $response->getContent();
        $this->assertStringNotContainsString('secret@example.com', (string) $body);
        $this->assertStringNotContainsString('form_order_id', (string) $body);
    }

    public function test_feature_flag_disables_dashboard(): void
    {
        config()->set('analytics.revenue_dashboard.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.revenue.index'))
            ->assertNotFound();
    }

    public function test_navigation_contains_revenue_link_for_admin(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('analytics.revenue.index'), false);
    }

    public function test_date_semantics_description_is_visible(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.revenue.index'))
            ->assertOk()
            ->assertSee('form_order_created')
            ->assertSee('payment_status_changed')
            ->assertSee('invoice_created');
    }

    // ---------------------------------------------------------------------
    // Przycisk "Przelicz rozliczenia" (ręczna agregacja R1 z panelu)
    // ---------------------------------------------------------------------

    public function test_guest_cannot_recompute_revenue(): void
    {
        $this->post(route('analytics.revenue.recompute'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_recompute_revenue(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->post(route('analytics.revenue.recompute'))
            ->assertForbidden();
    }

    public function test_recompute_is_not_available_when_feature_disabled(): void
    {
        config()->set('analytics.revenue_dashboard.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.revenue.recompute'))
            ->assertNotFound();
    }

    public function test_admin_can_recompute_selected_range_and_it_builds_stats(): void
    {
        config()->set('analytics.revenue.timezone', 'Europe/Warsaw');
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEventsTable();

        $this->createRevenueEvent([
            'event_name' => 'form_order_created',
            'course_id' => 555,
            'metadata' => ['amount_gross' => 100],
            'occurred_at' => '2026-06-20 10:00:00',
        ]);

        $this->assertSame(0, AnalyticsDailyCourseRevenueStat::query()->count());

        $this->actingAs($admin)
            ->post(route('analytics.revenue.recompute'), [
                'date_from' => '2026-06-20',
                'date_to' => '2026-06-20',
            ])
            ->assertRedirect(route('analytics.revenue.index', [
                'date_from' => '2026-06-20',
                'date_to' => '2026-06-20',
            ]))
            ->assertSessionHas('recompute_status');

        $this->assertSame(1, AnalyticsDailyCourseRevenueStat::query()->where('course_id', 555)->count());
    }

    public function test_recompute_is_idempotent_and_does_not_duplicate(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEventsTable();

        $this->createRevenueEvent([
            'event_name' => 'form_order_created',
            'course_id' => 666,
            'metadata' => ['amount_gross' => 50],
            'occurred_at' => '2026-06-21 12:00:00',
        ]);

        $payload = ['date_from' => '2026-06-21', 'date_to' => '2026-06-21'];

        $this->actingAs($admin)->post(route('analytics.revenue.recompute'), $payload);
        $this->actingAs($admin)->post(route('analytics.revenue.recompute'), $payload);

        $this->assertSame(1, AnalyticsDailyCourseRevenueStat::query()->where('course_id', 666)->count());
    }

    public function test_recompute_rejects_range_above_configured_limit(): void
    {
        config()->set('analytics.revenue_dashboard.recompute_max_days', 31);
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEventsTable();

        $this->actingAs($admin)
            ->post(route('analytics.revenue.recompute'), [
                'date_from' => '2026-01-01',
                'date_to' => '2026-03-31',
            ])
            ->assertSessionHas('recompute_error');
    }

    public function test_dashboard_shows_recompute_button(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.revenue.index'))
            ->assertOk()
            ->assertSee('Przelicz rozliczenia')
            ->assertSee(route('analytics.revenue.recompute'), false);
    }

    private function seedCourse(string $date, int $courseId, ?string $title, array $overrides = []): void
    {
        AnalyticsDailyCourseRevenueStat::query()->create(array_merge([
            'stat_date' => $date,
            'course_id' => $courseId,
            'course_title_snapshot' => $title,
            'orders_created' => 0,
            'ordered_revenue_gross' => 0,
            'online_paid_orders' => 0,
            'online_paid_revenue_gross' => 0,
            'deferred_invoiced_orders' => 0,
            'deferred_invoiced_revenue_gross' => 0,
            'online_invoiced_marker_orders' => 0,
            'settled_orders_total' => 0,
            'settled_revenue_gross' => 0,
            'orders_created_without_campaign' => 0,
            'online_paid_without_campaign' => 0,
            'deferred_invoiced_without_campaign' => 0,
        ], $overrides));
    }

    private function seedCampaign(string $date, string $code, array $overrides = []): void
    {
        AnalyticsDailyCampaignRevenueStat::query()->create(array_merge([
            'stat_date' => $date,
            'campaign_code' => $code,
            'campaign_id' => null,
            'orders_created' => 0,
            'ordered_revenue_gross' => 0,
            'online_paid_orders' => 0,
            'online_paid_revenue_gross' => 0,
            'deferred_invoiced_orders' => 0,
            'deferred_invoiced_revenue_gross' => 0,
            'online_invoiced_marker_orders' => 0,
            'settled_orders_total' => 0,
            'settled_revenue_gross' => 0,
        ], $overrides));
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

    private function createAnalyticsEventsTable(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_events');
        Schema::connection('analytics')->create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_name', 100);
            $table->string('event_category', 50);
            $table->timestamp('occurred_at');
            $table->string('app_source', 32);
            $table->uuid('analytics_session_id')->nullable();
            $table->uuid('order_form_session_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('course_title_snapshot', 255)->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('campaign_code', 100)->nullable();
            $table->string('campaign_channel', 50)->nullable();
            $table->unsignedBigInteger('form_order_id')->nullable();
            $table->unsignedBigInteger('payment_order_id')->nullable();
            $table->string('route_name', 150)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('browser_family', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    private function createRevenueEvent(array $overrides = []): AnalyticsEvent
    {
        $occurredAt = $overrides['occurred_at'] ?? '2026-06-24 10:00:00';
        if ($occurredAt instanceof \Carbon\Carbon) {
            $occurredAt = $occurredAt->format('Y-m-d H:i:s');
        }
        unset($overrides['occurred_at']);

        return AnalyticsEvent::query()->create(array_merge([
            'event_uuid' => (string) Str::uuid(),
            'event_name' => 'form_order_created',
            'event_category' => 'conversion',
            'occurred_at' => $occurredAt,
            'app_source' => 'pnedu',
            'analytics_session_id' => (string) Str::uuid(),
            'order_form_session_id' => null,
            'course_id' => null,
            'course_title_snapshot' => null,
            'campaign_id' => null,
            'campaign_code' => null,
            'campaign_channel' => null,
            'form_order_id' => null,
            'payment_order_id' => null,
            'route_name' => null,
            'path' => null,
            'device_type' => 'desktop',
            'browser_family' => 'chrome',
            'metadata' => [],
            'created_at' => now(),
        ], $overrides));
    }

    private function createRevenueTables(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_daily_campaign_revenue_stats');
        Schema::connection('analytics')->dropIfExists('analytics_daily_course_revenue_stats');

        Schema::connection('analytics')->create('analytics_daily_course_revenue_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedBigInteger('course_id');
            $table->string('course_title_snapshot', 255)->nullable();
            $table->unsignedInteger('orders_created')->default(0);
            $table->decimal('ordered_revenue_gross', 12, 2)->default(0);
            $table->unsignedInteger('online_paid_orders')->default(0);
            $table->decimal('online_paid_revenue_gross', 12, 2)->default(0);
            $table->unsignedInteger('deferred_invoiced_orders')->default(0);
            $table->decimal('deferred_invoiced_revenue_gross', 12, 2)->default(0);
            $table->unsignedInteger('online_invoiced_marker_orders')->default(0);
            $table->unsignedInteger('settled_orders_total')->default(0);
            $table->decimal('settled_revenue_gross', 12, 2)->default(0);
            $table->unsignedInteger('orders_created_without_campaign')->default(0);
            $table->unsignedInteger('online_paid_without_campaign')->default(0);
            $table->unsignedInteger('deferred_invoiced_without_campaign')->default(0);
            $table->timestamps();
        });

        Schema::connection('analytics')->create('analytics_daily_campaign_revenue_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('campaign_code', 100);
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedInteger('orders_created')->default(0);
            $table->decimal('ordered_revenue_gross', 12, 2)->default(0);
            $table->unsignedInteger('online_paid_orders')->default(0);
            $table->decimal('online_paid_revenue_gross', 12, 2)->default(0);
            $table->unsignedInteger('deferred_invoiced_orders')->default(0);
            $table->decimal('deferred_invoiced_revenue_gross', 12, 2)->default(0);
            $table->unsignedInteger('online_invoiced_marker_orders')->default(0);
            $table->unsignedInteger('settled_orders_total')->default(0);
            $table->decimal('settled_revenue_gross', 12, 2)->default(0);
            $table->timestamps();
        });
    }
}
