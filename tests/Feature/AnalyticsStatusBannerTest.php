<?php

namespace Tests\Feature;

use App\Models\AnalyticsSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalyticsStatusBannerTest extends TestCase
{
    private array $createdUserIds = [];

    private array $createdRoleIds = [];

    private int $outputBufferLevel = 0;

    private const BANNER_MARKER = 'Uwaga — stan analityki';

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputBufferLevel = ob_get_level();

        if (! Schema::hasTable('users') || ! Schema::hasTable('roles') || ! Schema::hasTable('analytics_settings')) {
            $this->markTestSkipped('Brak tabel users/roles/analytics_settings w testowej bazie adm.');
        }

        config()->set('analytics.enabled', true);
        config()->set('analytics.default_mode', 'standard');
        config()->set('analytics.sample_rate', 100);
        config()->set('analytics.debug_panel.enabled', true);
        config()->set('analytics.sales_funnel_dashboard.enabled', true);
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createAnalyticsDailyTables();
        $this->createAnalyticsEventsTable();

        $this->resetSettings();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        $this->resetSettings();

        if ($this->createdUserIds !== []) {
            User::query()->withTrashed()->whereIn('id', $this->createdUserIds)->forceDelete();
        }

        if ($this->createdRoleIds !== []) {
            Role::query()->whereIn('id', $this->createdRoleIds)->delete();
        }

        parent::tearDown();
    }

    public function test_sales_funnel_shows_banner_when_hard_kill_switch_enabled(): void
    {
        config()->set('analytics.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk()
            ->assertSee(self::BANNER_MARKER)
            ->assertSee('ANALYTICS_ENABLED=false');
    }

    public function test_debug_events_shows_banner_when_hard_kill_switch_enabled(): void
    {
        config()->set('analytics.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index'))
            ->assertOk()
            ->assertSee(self::BANNER_MARKER)
            ->assertSee('ANALYTICS_ENABLED=false');
    }

    public function test_settings_page_shows_hard_kill_switch_info(): void
    {
        config()->set('analytics.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.settings.index'))
            ->assertOk()
            ->assertSee(self::BANNER_MARKER)
            ->assertSee('ANALYTICS_ENABLED=false');
    }

    public function test_banner_shows_when_runtime_mode_off(): void
    {
        $this->setOverride(null, 'off');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk()
            ->assertSee(self::BANNER_MARKER)
            ->assertSee('wyłączona w ustawieniach runtime');
    }

    public function test_banner_shows_when_runtime_mode_aggregate_only(): void
    {
        $this->setOverride(null, 'aggregate_only');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk()
            ->assertSee(self::BANNER_MARKER)
            ->assertSee('aggregate_only');
    }

    public function test_banner_shows_when_runtime_mode_light(): void
    {
        $this->setOverride(null, 'light');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk()
            ->assertSee(self::BANNER_MARKER)
            ->assertSee('trybie lekkim');
    }

    public function test_no_warning_banner_on_dashboard_for_standard_mode(): void
    {
        $this->setOverride(null, 'standard');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk()
            ->assertDontSee(self::BANNER_MARKER);
    }

    public function test_no_warning_banner_on_dashboard_for_full_mode(): void
    {
        $this->setOverride(null, 'full');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk()
            ->assertDontSee(self::BANNER_MARKER);
    }

    public function test_banner_contains_settings_link_on_dashboard_and_debug(): void
    {
        $this->setOverride(null, 'off');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.sales-funnel.index'))
            ->assertOk()
            ->assertSee('Przejdź do ustawień analityki')
            ->assertSee(route('analytics.settings.index'), false);

        $this->actingAs($admin)
            ->get(route('analytics.debug-events.index'))
            ->assertOk()
            ->assertSee('Przejdź do ustawień analityki')
            ->assertSee(route('analytics.settings.index'), false);
    }

    public function test_non_admin_cannot_access_analytics_pages(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)->get(route('analytics.sales-funnel.index'))->assertForbidden();
        $this->actingAs($user)->get(route('analytics.debug-events.index'))->assertForbidden();
        $this->actingAs($user)->get(route('analytics.settings.index'))->assertForbidden();
    }

    public function test_banner_does_not_expose_secrets(): void
    {
        config()->set('analytics.enabled', false);
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('analytics.sales-funnel.index'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('DB_PASSWORD', $content);
        $this->assertStringNotContainsString(config('app.key'), $content);
    }

    public function test_settings_page_mentions_pnedu_separate_env(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.settings.index'))
            ->assertOk()
            ->assertSee('własną konfigurację')
            ->assertSee('hard kill switch');
    }

    private function resetSettings(): void
    {
        DB::table('analytics_settings')->updateOrInsert(
            ['id' => AnalyticsSetting::SINGLETON_ID],
            ['enabled_override' => null, 'default_mode_override' => null, 'updated_by' => null, 'updated_at' => now()],
        );
        AnalyticsSetting::forgetSettingsCache();
    }

    private function setOverride(?bool $enabled, ?string $mode): void
    {
        DB::table('analytics_settings')->updateOrInsert(
            ['id' => AnalyticsSetting::SINGLETON_ID],
            ['enabled_override' => $enabled, 'default_mode_override' => $mode, 'updated_at' => now()],
        );
        AnalyticsSetting::forgetSettingsCache();
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
