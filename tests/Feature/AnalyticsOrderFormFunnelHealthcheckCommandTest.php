<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalyticsOrderFormFunnelHealthcheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.order_form_funnel.timezone', 'Europe/Warsaw');
        config()->set('analytics.order_form_funnel.aggregation_lag_days', 2);
        config()->set('analytics.order_form_funnel.data_quality_min_sessions', 30);
        config()->set('analytics.order_form_funnel.tracking_deployed_at', '2020-01-01');
        config()->set('analytics.order_form_funnel.attribution_deployed_at', '2020-01-01');

        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createTables();
    }

    public function test_healthcheck_passes_for_complete_day(): void
    {
        $this->seedDataQualityRow([
            'stat_date' => '2026-06-20',
            'sessions_total' => 50,
            'sessions_with_frontend_events' => 50,
            'sessions_with_attribution' => 50,
            'sessions_with_traffic_channel' => 50,
            'sessions_with_schema_v2_events' => 50,
            'frontend_tracking_coverage_rate' => 1.0,
            'attribution_coverage_rate' => 1.0,
            'traffic_channel_coverage_rate' => 1.0,
            'schema_v2_event_rate' => 1.0,
            'tracking_data_quality_status' => 'complete',
            'tracking_data_quality_score' => 95,
        ]);

        $this->artisan('analytics:order-form-funnel-healthcheck', ['--from' => '2026-06-15', '--to' => '2026-06-25'])
            ->expectsOutputToContain('WERDYKT: OK')
            ->assertExitCode(0);
    }

    public function test_healthcheck_skips_hard_alerts_for_low_volume(): void
    {
        $this->seedDataQualityRow([
            'stat_date' => '2026-06-20',
            'sessions_total' => 5,
            'sessions_without_attribution' => 5,
            'frontend_tracking_coverage_rate' => 0,
            'attribution_coverage_rate' => 0,
            'traffic_channel_coverage_rate' => 0,
            'schema_v2_event_rate' => 0,
            'tracking_data_quality_status' => 'low_volume',
            'tracking_data_quality_score' => 10,
        ]);

        $this->artisan('analytics:order-form-funnel-healthcheck', ['--from' => '2026-06-15', '--to' => '2026-06-25'])
            ->expectsOutputToContain('pominięto twarde alerty')
            ->assertExitCode(0);
    }

    public function test_healthcheck_fails_on_critical_frontend_coverage(): void
    {
        $this->seedDataQualityRow([
            'stat_date' => '2026-06-20',
            'sessions_total' => 100,
            'sessions_with_frontend_events' => 10,
            'sessions_backend_only' => 90,
            'sessions_with_attribution' => 100,
            'sessions_with_traffic_channel' => 100,
            'frontend_tracking_coverage_rate' => 0.1,
            'attribution_coverage_rate' => 1.0,
            'traffic_channel_coverage_rate' => 1.0,
            'schema_v2_event_rate' => 0.1,
            'tracking_data_quality_status' => 'backend_only',
            'tracking_data_quality_score' => 20,
        ]);

        $this->artisan('analytics:order-form-funnel-healthcheck', ['--from' => '2026-06-15', '--to' => '2026-06-25'])
            ->expectsOutputToContain('CRITICAL')
            ->assertExitCode(1);
    }

    public function test_healthcheck_skips_hard_alerts_for_pre_attribution_historical_days(): void
    {
        config()->set('analytics.order_form_funnel.attribution_deployed_at', '2026-07-09');

        $this->seedDataQualityRow([
            'stat_date' => '2026-06-25',
            'sessions_total' => 100,
            'sessions_with_frontend_events' => 10,
            'sessions_backend_only' => 90,
            'sessions_without_attribution' => 100,
            'orders_total' => 24,
            'orders_without_attribution' => 24,
            'frontend_tracking_coverage_rate' => 0.1,
            'attribution_coverage_rate' => 0.0,
            'traffic_channel_coverage_rate' => 0.0,
            'schema_v2_event_rate' => 0.0,
            'tracking_data_quality_status' => 'backend_only',
            'tracking_data_quality_score' => 0,
        ]);

        $this->artisan('analytics:order-form-funnel-healthcheck', ['--from' => '2026-06-25', '--to' => '2026-06-25'])
            ->expectsOutputToContain('pre_attribution_historical')
            ->expectsOutputToContain('Dane sprzed wdrożenia atrybucji 2F')
            ->expectsOutputToContain('WERDYKT: OK')
            ->assertExitCode(0);
    }

    public function test_healthcheck_fails_when_required_tables_missing(): void
    {
        Schema::connection('analytics')->drop('analytics_daily_data_quality');

        $this->artisan('analytics:order-form-funnel-healthcheck', ['--days' => 3])
            ->expectsOutputToContain('Brak wymaganych tabel')
            ->assertExitCode(1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedDataQualityRow(array $overrides): void
    {
        DB::connection('analytics')->table('analytics_daily_data_quality')->insert(array_merge([
            'stat_date' => '2026-06-20',
            'sessions_total' => 0,
            'sessions_with_frontend_events' => 0,
            'sessions_backend_only' => 0,
            'sessions_with_attribution' => 0,
            'sessions_without_attribution' => 0,
            'sessions_with_traffic_channel' => 0,
            'sessions_without_traffic_channel' => 0,
            'sessions_with_campaign' => 0,
            'sessions_without_campaign' => 0,
            'sessions_with_schema_v2_events' => 0,
            'orders_total' => 0,
            'orders_with_full_funnel' => 0,
            'orders_backend_only' => 0,
            'orders_with_attribution' => 0,
            'orders_without_attribution' => 0,
            'server_only_conversions' => 0,
            'frontend_tracking_coverage_rate' => 0,
            'attribution_coverage_rate' => 0,
            'traffic_channel_coverage_rate' => 0,
            'campaign_coverage_rate' => 0,
            'schema_v2_event_rate' => 0,
            'tracking_data_quality_status' => 'unknown',
            'tracking_data_quality_flags' => json_encode([]),
            'tracking_data_quality_score' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createTables(): void
    {
        Schema::connection('analytics')->create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 100);
            $table->timestamp('occurred_at');
        });

        Schema::connection('analytics')->create('analytics_daily_data_quality', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedInteger('sessions_total')->default(0);
            $table->unsignedInteger('sessions_with_frontend_events')->default(0);
            $table->unsignedInteger('sessions_backend_only')->default(0);
            $table->unsignedInteger('sessions_with_attribution')->default(0);
            $table->unsignedInteger('sessions_without_attribution')->default(0);
            $table->unsignedInteger('sessions_with_traffic_channel')->default(0);
            $table->unsignedInteger('sessions_without_traffic_channel')->default(0);
            $table->unsignedInteger('sessions_with_campaign')->default(0);
            $table->unsignedInteger('sessions_without_campaign')->default(0);
            $table->unsignedInteger('sessions_with_schema_v2_events')->default(0);
            $table->unsignedInteger('orders_total')->default(0);
            $table->unsignedInteger('orders_with_full_funnel')->default(0);
            $table->unsignedInteger('orders_backend_only')->default(0);
            $table->unsignedInteger('orders_with_attribution')->default(0);
            $table->unsignedInteger('orders_without_attribution')->default(0);
            $table->unsignedInteger('server_only_conversions')->default(0);
            $table->decimal('frontend_tracking_coverage_rate', 8, 4)->default(0);
            $table->decimal('attribution_coverage_rate', 8, 4)->default(0);
            $table->decimal('traffic_channel_coverage_rate', 8, 4)->default(0);
            $table->decimal('campaign_coverage_rate', 8, 4)->default(0);
            $table->decimal('schema_v2_event_rate', 8, 4)->default(0);
            $table->string('tracking_data_quality_status', 50)->default('unknown');
            $table->text('tracking_data_quality_flags')->nullable();
            $table->unsignedTinyInteger('tracking_data_quality_score')->default(0);
            $table->timestamps();
        });

        Schema::connection('analytics')->create('analytics_daily_channel_funnels', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
        });

        Schema::connection('analytics')->create('order_form_attributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('form_session_id');
        });
    }
}
