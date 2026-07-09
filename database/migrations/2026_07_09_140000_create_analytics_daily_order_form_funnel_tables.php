<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function analyticsConnection(): string
    {
        return (string) config('database.analytics_connection', 'analytics');
    }

    public function up(): void
    {
        $connection = $this->analyticsConnection();

        Schema::connection($connection)->create('analytics_daily_channel_funnels', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('traffic_channel', 50);
            $table->string('traffic_source', 100)->nullable();
            $table->string('traffic_medium', 50)->nullable();
            $table->string('traffic_campaign', 255)->nullable();
            $table->string('conversion_reporting_channel', 50);
            $table->unsignedSmallInteger('tracking_schema_version')->default(2);
            $this->funnelMetricColumns($table);
            $table->boolean('internal_promo_touched')->default(false);
            $table->string('internal_promo_placement', 100)->nullable();
            $table->string('internal_promo_context', 100)->nullable();
            $table->timestamps();

            $table->unique([
                'stat_date',
                'traffic_channel',
                'conversion_reporting_channel',
                'traffic_source',
                'traffic_medium',
                'traffic_campaign',
                'tracking_schema_version',
            ], 'channel_funnels_unique');
            $table->index(['stat_date', 'traffic_channel'], 'channel_funnels_idx');
        });

        Schema::connection($connection)->create('analytics_daily_course_channel_funnels', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('price_variant_id')->nullable();
            $table->string('course_title_snapshot', 255)->nullable();
            $table->string('traffic_channel', 50);
            $table->string('traffic_source', 100)->nullable();
            $table->string('traffic_medium', 50)->nullable();
            $table->string('traffic_campaign', 255)->nullable();
            $table->string('conversion_reporting_channel', 50);
            $table->unsignedSmallInteger('tracking_schema_version')->default(2);
            $this->funnelMetricColumns($table);
            $table->boolean('internal_promo_touched')->default(false);
            $table->string('internal_promo_placement', 100)->nullable();
            $table->string('internal_promo_context', 100)->nullable();
            $table->timestamps();

            $table->unique([
                'stat_date',
                'course_id',
                'price_variant_id',
                'traffic_channel',
                'conversion_reporting_channel',
                'traffic_source',
                'traffic_medium',
                'traffic_campaign',
            ], 'course_channel_funnels_uq');
            $table->index(['stat_date', 'course_id', 'traffic_channel'], 'course_channel_funnels_idx');
        });

        Schema::connection($connection)->create('analytics_daily_campaign_funnels', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('campaign_code', 100)->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('campaign_name', 255)->nullable();
            $table->string('traffic_channel', 50);
            $table->string('traffic_source', 100)->nullable();
            $table->string('traffic_medium', 50)->nullable();
            $table->string('traffic_campaign', 255)->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('price_variant_id')->nullable();
            $table->unsignedInteger('sessions_total')->default(0);
            $table->unsignedInteger('order_created')->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->unsignedInteger('first_interaction')->default(0);
            $table->unsignedInteger('reached_submit_clicked')->default(0);
            $table->unsignedInteger('server_submit_attempted')->default(0);
            $table->unsignedInteger('server_validation_failed')->default(0);
            $table->unsignedInteger('abandonment_before_first_interaction')->default(0);
            $table->unsignedInteger('gus_success_sessions')->default(0);
            $table->unsignedInteger('gus_error_sessions')->default(0);
            $table->unsignedInteger('server_only_conversions')->default(0);
            $table->unsignedInteger('sessions_without_campaign_metadata')->default(0);
            $table->unsignedInteger('suspicious_campaign_name_count')->default(0);
            $table->unsignedInteger('campaign_course_mismatch_count')->default(0);
            $table->boolean('internal_promo_touched')->default(false);
            $table->string('internal_promo_placement', 100)->nullable();
            $table->timestamps();

            $table->index(['stat_date', 'campaign_code', 'traffic_channel'], 'campaign_funnels_idx');
            $table->index(['stat_date', 'traffic_channel'], 'campaign_funnels_channel_idx');
        });

        Schema::connection($connection)->create('analytics_daily_gus_channel_funnels', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('price_variant_id')->nullable();
            $table->string('traffic_channel', 50);
            $table->string('target', 20);
            $table->unsignedInteger('sessions_total')->default(0);
            $table->unsignedInteger('sessions_with_gus_lookup')->default(0);
            $table->unsignedInteger('sessions_without_gus_lookup')->default(0);
            $table->unsignedInteger('gus_lookup_clicked')->default(0);
            $table->unsignedInteger('gus_lookup_started')->default(0);
            $table->unsignedInteger('gus_lookup_success')->default(0);
            $table->unsignedInteger('gus_lookup_error')->default(0);
            $table->decimal('gus_success_rate', 8, 4)->default(0);
            $table->decimal('gus_error_rate', 8, 4)->default(0);
            $table->unsignedInteger('gus_data_applied')->default(0);
            $table->unsignedInteger('form_field_edited_after_gus')->default(0);
            $table->unsignedInteger('gus_manual_fallback_started')->default(0);
            $table->unsignedInteger('orders_after_gus_success')->default(0);
            $table->unsignedInteger('orders_after_gus_error')->default(0);
            $table->unsignedInteger('orders_without_gus')->default(0);
            $table->decimal('conversion_rate_with_gus', 8, 4)->default(0);
            $table->decimal('conversion_rate_without_gus', 8, 4)->default(0);
            $table->decimal('conversion_rate_after_gus_success', 8, 4)->default(0);
            $table->decimal('conversion_rate_after_gus_error', 8, 4)->default(0);
            $table->decimal('gus_conversion_delta', 8, 4)->default(0);
            $table->unsignedInteger('abandonment_after_gus_success')->default(0);
            $table->unsignedInteger('abandonment_after_gus_error')->default(0);
            $table->unsignedInteger('recovered_after_gus_error')->default(0);
            $table->unsignedInteger('sessions_with_gus_error_then_success')->default(0);
            $table->unsignedInteger('avg_gus_latency_ms')->nullable();
            $table->timestamps();

            $table->unique(['stat_date', 'course_id', 'price_variant_id', 'traffic_channel', 'target'], 'gus_channel_funnels_uq');
            $table->index(['stat_date', 'traffic_channel'], 'gus_channel_funnels_idx');
        });

        Schema::connection($connection)->create('analytics_daily_data_quality', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->unique();
            $table->unsignedInteger('sessions_total')->default(0);
            $table->unsignedInteger('sessions_with_frontend_events')->default(0);
            $table->unsignedInteger('sessions_backend_only')->default(0);
            $table->unsignedInteger('sessions_with_attribution')->default(0);
            $table->unsignedInteger('sessions_without_attribution')->default(0);
            $table->unsignedInteger('sessions_with_traffic_channel')->default(0);
            $table->unsignedInteger('sessions_without_traffic_channel')->default(0);
            $table->unsignedInteger('sessions_with_campaign')->default(0);
            $table->unsignedInteger('sessions_without_campaign')->default(0);
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
            $table->string('tracking_data_quality_status', 50)->default('unknown');
            $table->timestamps();
        });
    }

    private function funnelMetricColumns(Blueprint $table): void
    {
        $table->unsignedInteger('sessions_total')->default(0);
        $table->unsignedInteger('form_visible')->default(0);
        $table->unsignedInteger('first_interaction')->default(0);
        $table->unsignedInteger('reached_started')->default(0);
        $table->unsignedInteger('reached_submit_clicked')->default(0);
        $table->unsignedInteger('server_submit_attempted')->default(0);
        $table->unsignedInteger('server_validation_failed')->default(0);
        $table->unsignedInteger('order_create_failed')->default(0);
        $table->unsignedInteger('order_created')->default(0);
        $table->decimal('conversion_rate', 8, 4)->default(0);
        $table->decimal('visible_to_first_interaction_rate', 8, 4)->default(0);
        $table->decimal('started_to_created_rate', 8, 4)->default(0);
        $table->decimal('submit_to_created_rate', 8, 4)->default(0);
        $table->unsignedInteger('abandonment_before_first_interaction')->default(0);
        $table->unsignedInteger('abandonment_after_first_interaction')->default(0);
        $table->unsignedInteger('abandoned_after_submit_clicked')->default(0);
        $table->unsignedInteger('abandoned_after_server_validation_failed')->default(0);
        $table->unsignedInteger('server_only_conversions')->default(0);
        $table->unsignedInteger('frontend_only_abandonments')->default(0);
        $table->unsignedInteger('gus_buyer_lookup_clicked')->default(0);
        $table->unsignedInteger('gus_buyer_success')->default(0);
        $table->unsignedInteger('gus_buyer_error')->default(0);
        $table->unsignedInteger('gus_recipient_lookup_clicked')->default(0);
        $table->unsignedInteger('gus_recipient_success')->default(0);
        $table->unsignedInteger('gus_recipient_error')->default(0);
        $table->unsignedInteger('gus_success_sessions')->default(0);
        $table->unsignedInteger('gus_error_sessions')->default(0);
        $table->unsignedInteger('gus_data_applied_sessions')->default(0);
        $table->unsignedInteger('edited_after_gus_sessions')->default(0);
        $table->unsignedInteger('gus_manual_fallback_sessions')->default(0);
        $table->unsignedInteger('payment_deferred_selected')->default(0);
        $table->unsignedInteger('payment_online_selected')->default(0);
        $table->unsignedInteger('sessions_with_attribution')->default(0);
        $table->unsignedInteger('sessions_without_attribution')->default(0);
        $table->unsignedInteger('sessions_with_frontend_events')->default(0);
        $table->unsignedInteger('sessions_backend_only')->default(0);
        $table->unsignedInteger('orders_with_attribution')->default(0);
        $table->unsignedInteger('orders_without_attribution')->default(0);
    }

    public function down(): void
    {
        $connection = $this->analyticsConnection();

        Schema::connection($connection)->dropIfExists('analytics_daily_data_quality');
        Schema::connection($connection)->dropIfExists('analytics_daily_gus_channel_funnels');
        Schema::connection($connection)->dropIfExists('analytics_daily_campaign_funnels');
        Schema::connection($connection)->dropIfExists('analytics_daily_course_channel_funnels');
        Schema::connection($connection)->dropIfExists('analytics_daily_channel_funnels');
    }
};
