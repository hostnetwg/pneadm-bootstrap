<?php

namespace Tests\Feature;

use App\Models\Analytics\AnalyticsDailyCampaignStat;
use App\Models\Analytics\AnalyticsDailyCourseStat;
use App\Models\Analytics\AnalyticsEvent;
use App\Models\FormOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\Analytics\AnalyticsSalesFunnelDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsSalesFunnelDashboardTest extends TestCase
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

        config()->set('analytics.sales_funnel_dashboard.enabled', true);
        config()->set('analytics.debug_panel.enabled', true);
        config()->set('analytics.sales_funnel_dashboard.timezone', 'Europe/Warsaw');
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createAnalyticsDailyTables();
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

    public function test_guest_cannot_access_sales_funnel_dashboard(): void
    {
        $this->get(route('analytics.sales-funnel.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_admin_role_cannot_access_sales_funnel_dashboard(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->get(route('analytics.sales-funnel.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_dashboard_and_it_reads_analytics_daily_stats(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedDashboardData();

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk()
            ->assertSee('Lejek sprzedaży')
            ->assertSee('KEEP-CAMP')
            ->assertSee('Szkolenie testowe')
            ->assertSee('50')
            ->assertSee('10');
    }

    public function test_date_filter_limits_visible_aggregates(): void
    {
        $admin = $this->userWithRole('admin');

        AnalyticsDailyCourseStat::query()->create([
            'stat_date' => '2026-06-01',
            'course_id' => 100,
            'course_title_snapshot' => 'W zakresie',
            'views_course_description' => 5,
            'views_order_form' => 0,
            'submit_attempts' => 0,
            'validation_failures' => 0,
            'orders_created' => 0,
            'revenue_snapshot' => 0,
        ]);
        AnalyticsDailyCourseStat::query()->create([
            'stat_date' => '2026-05-01',
            'course_id' => 100,
            'course_title_snapshot' => 'Poza zakresem',
            'views_course_description' => 999,
            'views_order_form' => 0,
            'submit_attempts' => 0,
            'validation_failures' => 0,
            'orders_created' => 0,
            'revenue_snapshot' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-02',
            ]))
            ->assertOk()
            ->assertSee('W zakresie')
            ->assertDontSee('Poza zakresem');
    }

    public function test_campaign_code_and_course_id_filters_work(): void
    {
        $admin = $this->userWithRole('admin');

        AnalyticsDailyCampaignStat::query()->create([
            'stat_date' => '2026-06-10',
            'campaign_code' => 'KEEP-CAMP',
            'link_entries' => 3,
            'orders_created' => 1,
        ]);
        AnalyticsDailyCampaignStat::query()->create([
            'stat_date' => '2026-06-10',
            'campaign_code' => 'SKIP-CAMP',
            'link_entries' => 99,
            'orders_created' => 9,
        ]);
        AnalyticsDailyCourseStat::query()->create([
            'stat_date' => '2026-06-10',
            'course_id' => 200,
            'course_title_snapshot' => 'Kurs 200',
            'views_order_form' => 4,
            'orders_created' => 2,
        ]);
        AnalyticsDailyCourseStat::query()->create([
            'stat_date' => '2026-06-10',
            'course_id' => 300,
            'course_title_snapshot' => 'Kurs 300',
            'views_order_form' => 88,
            'orders_created' => 8,
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'campaign_code' => 'KEEP-CAMP',
                'course_id' => 200,
            ]))
            ->assertOk()
            ->assertSee('KEEP-CAMP')
            ->assertDontSee('SKIP-CAMP')
            ->assertSee('Kurs 200')
            ->assertDontSee('Kurs 300');
    }

    public function test_conversion_rates_are_calculated_and_zero_division_is_safe(): void
    {
        $service = app(AnalyticsSalesFunnelDashboardService::class);

        AnalyticsDailyCourseStat::query()->create([
            'stat_date' => '2026-06-10',
            'course_id' => 400,
            'views_course_description' => 100,
            'views_order_form' => 50,
            'submit_attempts' => 20,
            'validation_failures' => 5,
            'orders_created' => 10,
            'revenue_snapshot' => 1000,
        ]);

        $data = $service->build([
            'date_from' => '2026-06-10',
            'date_to' => '2026-06-10',
        ]);

        $this->assertSame(50.0, $data['rates']['description_to_form']);
        $this->assertSame(40.0, $data['rates']['form_to_submit']);
        $this->assertSame(50.0, $data['rates']['submit_to_order']);
        $this->assertSame(20.0, $data['rates']['form_to_order']);
        $this->assertSame(25.0, $data['rates']['validation_errors_per_submit']);

        $empty = $service->build([
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-02',
        ]);

        $this->assertNull($empty['rates']['description_to_form']);
        $this->assertNull($empty['rates']['form_to_order']);
    }

    public function test_dashboard_does_not_expose_form_order_id_or_pii(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedDashboardData();

        $response = $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ]));

        $response->assertOk();
        $content = $response->getContent() ?: '';

        $this->assertStringNotContainsString('form_order_id', $content);
        $this->assertStringNotContainsString('secret@example.com', $content);
        $this->assertStringNotContainsString('501654274', $content);
    }

    public function test_dashboard_does_not_query_form_orders_table(): void
    {
        if (! Schema::hasTable('form_orders')) {
            $this->markTestSkipped('Brak tabeli form_orders w testowej bazie adm.');
        }

        $before = FormOrder::query()->count();
        $admin = $this->userWithRole('admin');
        $this->seedDashboardData();

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk();

        $this->assertSame($before, FormOrder::query()->count());
    }

    public function test_dashboard_does_not_touch_legacy_aggregate_tables(): void
    {
        if (! Schema::hasTable('marketing_campaign_stats_daily')
            || ! Schema::hasTable('course_page_stats_daily')) {
            $this->markTestSkipped('Brak starych tabel agregatów w testowej bazie adm.');
        }

        $campaignBefore = DB::table('marketing_campaign_stats_daily')->count();
        $courseBefore = DB::table('course_page_stats_daily')->count();
        $admin = $this->userWithRole('admin');
        $this->seedDashboardData();

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk();

        $this->assertSame($campaignBefore, DB::table('marketing_campaign_stats_daily')->count());
        $this->assertSame($courseBefore, DB::table('course_page_stats_daily')->count());
    }

    public function test_debug_panel_still_works_after_sales_funnel_dashboard(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index'))
            ->assertOk()
            ->assertSee('Debug eventów');
    }

    public function test_dashboard_is_disabled_when_feature_flag_is_off(): void
    {
        config()->set('analytics.sales_funnel_dashboard.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertNotFound();
    }

    public function test_guest_cannot_recompute_aggregates(): void
    {
        $this->post(route('analytics.sales-funnel.recompute'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_recompute_aggregates(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->post(route('analytics.sales-funnel.recompute'))
            ->assertForbidden();
    }

    public function test_recompute_is_not_available_when_feature_flag_is_off(): void
    {
        config()->set('analytics.sales_funnel_dashboard.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.sales-funnel.recompute'))
            ->assertNotFound();
    }

    public function test_admin_can_recompute_selected_range_and_it_builds_daily_stats(): void
    {
        config()->set('analytics.aggregation.timezone', 'Europe/Warsaw');
        $admin = $this->userWithRole('admin');

        $this->createEvent([
            'event_name' => 'course_description_viewed',
            'event_category' => 'landing',
            'course_id' => 777,
            'course_title_snapshot' => 'Kurs do przeliczenia',
            'occurred_at' => '2026-06-20 10:00:00',
        ]);

        $this->assertSame(0, AnalyticsDailyCourseStat::query()->count());

        $this->actingAs($admin)
            ->post(route('analytics.sales-funnel.recompute'), [
                'date_from' => '2026-06-20',
                'date_to' => '2026-06-20',
            ])
            ->assertRedirect(route('analytics.sales-funnel.index', [
                'date_from' => '2026-06-20',
                'date_to' => '2026-06-20',
            ]))
            ->assertSessionHas('recompute_status');

        $this->assertSame(1, AnalyticsDailyCourseStat::query()->where('course_id', 777)->count());
        $this->assertSame(1, (int) AnalyticsDailyCourseStat::query()->where('course_id', 777)->value('views_course_description'));
    }

    public function test_recompute_is_idempotent_and_does_not_duplicate(): void
    {
        $admin = $this->userWithRole('admin');

        $this->createEvent([
            'event_name' => 'order_form_viewed',
            'event_category' => 'order_form',
            'course_id' => 888,
            'occurred_at' => '2026-06-21 12:00:00',
        ]);

        $payload = ['date_from' => '2026-06-21', 'date_to' => '2026-06-21'];

        $this->actingAs($admin)->post(route('analytics.sales-funnel.recompute'), $payload);
        $this->actingAs($admin)->post(route('analytics.sales-funnel.recompute'), $payload);

        $this->assertSame(1, AnalyticsDailyCourseStat::query()->where('course_id', 888)->count());
    }

    public function test_recompute_rejects_range_above_configured_limit(): void
    {
        config()->set('analytics.sales_funnel_dashboard.recompute_max_days', 31);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.sales-funnel.recompute'), [
                'date_from' => '2026-01-01',
                'date_to' => '2026-03-31',
            ])
            ->assertSessionHas('recompute_error');
    }

    public function test_recompute_allows_range_within_configured_limit(): void
    {
        config()->set('analytics.sales_funnel_dashboard.recompute_max_days', 366);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.sales-funnel.recompute'), [
                'date_from' => '2025-07-01',
                'date_to' => '2026-06-30',
            ])
            ->assertSessionHas('recompute_status')
            ->assertSessionMissing('recompute_error');
    }

    private function seedDashboardData(): void
    {
        AnalyticsDailyCourseStat::query()->create([
            'stat_date' => '2026-06-15',
            'course_id' => 100,
            'course_title_snapshot' => 'Szkolenie testowe',
            'views_course_description' => 50,
            'views_order_form' => 20,
            'submit_attempts' => 10,
            'validation_failures' => 2,
            'orders_created' => 4,
            'revenue_snapshot' => 796,
        ]);

        AnalyticsDailyCampaignStat::query()->create([
            'stat_date' => '2026-06-15',
            'campaign_code' => 'KEEP-CAMP',
            'campaign_channel' => 'facebook',
            'link_entries' => 120,
            'course_description_views' => 30,
            'order_form_views' => 15,
            'submit_attempts' => 8,
            'validation_failures' => 1,
            'orders_created' => 3,
            'revenue_snapshot' => 597,
        ]);
    }

    private function createEvent(array $overrides = []): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create(array_merge([
            'event_uuid' => (string) Str::uuid(),
            'event_name' => 'course_description_viewed',
            'event_category' => 'landing',
            'occurred_at' => '2026-06-20 10:00:00',
            'app_source' => 'pnedu',
            'analytics_session_id' => (string) Str::uuid(),
            'course_id' => null,
            'course_title_snapshot' => null,
            'campaign_code' => null,
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

    private function createAnalyticsDailyTables(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_daily_campaign_stats');
        Schema::connection('analytics')->dropIfExists('analytics_daily_course_stats');

        Schema::connection('analytics')->create('analytics_daily_course_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedBigInteger('course_id');
            $table->string('course_title_snapshot', 255)->nullable();
            $table->unsignedInteger('views_course_description')->default(0);
            $table->unsignedInteger('views_order_form')->default(0);
            $table->unsignedInteger('form_starts')->default(0);
            $table->unsignedInteger('submit_attempts')->default(0);
            $table->unsignedInteger('validation_failures')->default(0);
            $table->unsignedInteger('orders_created')->default(0);
            $table->unsignedInteger('payment_orders_created')->default(0);
            $table->unsignedInteger('paid_orders')->default(0);
            $table->unsignedInteger('invoiced_orders')->default(0);
            $table->decimal('revenue_snapshot', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::connection('analytics')->create('analytics_daily_campaign_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('campaign_code', 100);
            $table->string('campaign_name_snapshot', 255)->nullable();
            $table->string('campaign_channel', 50)->nullable();
            $table->string('campaign_content_depth', 50)->nullable();
            $table->string('landing_target', 50)->nullable();
            $table->string('cta_type', 50)->nullable();
            $table->unsignedInteger('link_entries')->default(0);
            $table->unsignedInteger('course_description_views')->default(0);
            $table->unsignedInteger('order_form_views')->default(0);
            $table->unsignedInteger('form_starts')->default(0);
            $table->unsignedInteger('submit_attempts')->default(0);
            $table->unsignedInteger('validation_failures')->default(0);
            $table->unsignedInteger('orders_created')->default(0);
            $table->unsignedInteger('paid_orders')->default(0);
            $table->unsignedInteger('invoiced_orders')->default(0);
            $table->decimal('revenue_snapshot', 12, 2)->default(0);
            $table->timestamps();
        });
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
            $table->string('campaign_code', 100)->nullable();
            $table->string('landing_target', 50)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('route_name', 150)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('referrer_domain', 255)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('browser_family', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
