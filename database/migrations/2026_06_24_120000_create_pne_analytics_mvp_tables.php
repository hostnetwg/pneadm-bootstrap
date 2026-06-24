<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $analyticsConnection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->analyticsConnection)->create('analytics_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('analytics_session_id')->unique();
            $table->timestamp('first_seen_at')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('app_source', 32)->index();
            $table->string('tracking_mode', 32)->default('standard')->index();
            $table->unsignedTinyInteger('sample_rate')->default(100);
            $table->string('utm_source', 100)->nullable()->index();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable()->index();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('campaign_code', 100)->nullable()->index();
            $table->string('landing_target', 50)->nullable()->index();
            $table->string('device_type', 32)->nullable();
            $table->string('browser_family', 64)->nullable();
            $table->string('route_name', 150)->nullable()->index();
            $table->string('path', 500)->nullable();
            $table->string('referrer_domain', 255)->nullable();
            $table->timestamps();
        });

        Schema::connection($this->analyticsConnection)->create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_name', 100)->index();
            $table->string('event_category', 50)->index();
            $table->timestamp('occurred_at')->index();
            $table->string('app_source', 32)->index();
            $table->uuid('analytics_session_id')->nullable()->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->string('course_title_snapshot', 255)->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('campaign_code', 100)->nullable()->index();
            $table->string('landing_target', 50)->nullable()->index();
            $table->string('campaign_content_depth', 50)->nullable()->index();
            $table->string('campaign_channel', 50)->nullable()->index();
            $table->string('cta_type', 50)->nullable()->index();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->uuid('order_form_session_id')->nullable()->index();
            $table->unsignedBigInteger('form_order_id')->nullable()->index();
            $table->unsignedBigInteger('payment_order_id')->nullable()->index();
            $table->unsignedBigInteger('ab_test_id')->nullable()->index();
            $table->unsignedBigInteger('ab_variant_id')->nullable()->index();
            $table->string('route_name', 150)->nullable()->index();
            $table->string('path', 500)->nullable();
            $table->string('referrer_domain', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['event_name', 'occurred_at']);
            $table->index(['campaign_code', 'occurred_at']);
            $table->index(['course_id', 'occurred_at']);
        });

        Schema::connection($this->analyticsConnection)->create('landing_page_views', function (Blueprint $table) {
            $table->id();
            $table->uuid('analytics_session_id')->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->string('course_title_snapshot', 255)->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('campaign_code', 100)->nullable()->index();
            $table->string('landing_target', 50)->nullable()->index();
            $table->string('route_name', 150)->nullable()->index();
            $table->string('path', 500)->nullable();
            $table->string('referrer_domain', 255)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('browser_family', 64)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::connection($this->analyticsConnection)->create('order_form_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_form_session_id')->unique();
            $table->uuid('analytics_session_id')->nullable()->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->string('course_title_snapshot', 255)->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('campaign_code', 100)->nullable()->index();
            $table->string('landing_target', 50)->nullable()->index();
            $table->string('tracking_mode', 32)->default('standard')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('first_interaction_at')->nullable();
            $table->timestamp('last_event_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('abandoned_at')->nullable()->index();
            $table->unsignedBigInteger('form_order_id')->nullable()->index();
            $table->unsignedBigInteger('payment_order_id')->nullable()->index();
            $table->string('buyer_type', 50)->nullable()->index();
            $table->string('payment_type', 50)->nullable()->index();
            $table->string('payment_gateway', 50)->nullable()->index();
            $table->unsignedSmallInteger('participant_count')->nullable();
            $table->boolean('has_recipient')->nullable();
            $table->boolean('gus_lookup_used')->default(false);
            $table->boolean('gus_lookup_success')->nullable();
            $table->string('ksef_option_selected', 50)->nullable();
            $table->string('invoice_path_type', 50)->nullable()->index();
            $table->timestamps();
        });

        Schema::connection($this->analyticsConnection)->create('conversion_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_name', 100)->index();
            $table->timestamp('occurred_at')->index();
            $table->uuid('analytics_session_id')->nullable()->index();
            $table->uuid('order_form_session_id')->nullable()->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('campaign_code', 100)->nullable()->index();
            $table->unsignedBigInteger('form_order_id')->nullable()->index();
            $table->unsignedBigInteger('payment_order_id')->nullable()->index();
            $table->decimal('amount_snapshot', 10, 2)->nullable();
            $table->string('payment_status', 50)->nullable()->index();
            $table->string('payment_type', 50)->nullable()->index();
            $table->string('payment_gateway', 50)->nullable()->index();
            $table->string('invoice_path_type', 50)->nullable()->index();
            $table->boolean('has_recipient')->nullable();
            $table->string('ksef_option_selected', 50)->nullable();
            $table->unsignedBigInteger('ab_test_id')->nullable()->index();
            $table->unsignedBigInteger('ab_variant_id')->nullable()->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::connection($this->analyticsConnection)->create('validation_error_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->uuid('order_form_session_id')->nullable()->index();
            $table->uuid('analytics_session_id')->nullable()->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->unsignedBigInteger('form_order_id')->nullable()->index();
            $table->string('field_key', 100)->index();
            $table->string('section_key', 100)->nullable()->index();
            $table->string('rule_key', 100)->nullable()->index();
            $table->string('error_group', 100)->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::connection($this->analyticsConnection)->create('analytics_daily_course_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->index();
            $table->unsignedBigInteger('course_id')->index();
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

            $table->unique(['stat_date', 'course_id']);
        });

        Schema::connection($this->analyticsConnection)->create('analytics_daily_campaign_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->index();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('campaign_code', 100)->index();
            $table->string('campaign_name_snapshot', 255)->nullable();
            $table->string('campaign_channel', 50)->nullable()->index();
            $table->string('campaign_content_depth', 50)->nullable()->index();
            $table->string('landing_target', 50)->nullable()->index();
            $table->string('cta_type', 50)->nullable()->index();
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

            $table->unique(['stat_date', 'campaign_code', 'landing_target', 'campaign_content_depth', 'cta_type'], 'analytics_daily_campaign_unique');
        });
    }

    public function down(): void
    {
        foreach ([
            'analytics_daily_campaign_stats',
            'analytics_daily_course_stats',
            'validation_error_events',
            'conversion_events',
            'order_form_sessions',
            'landing_page_views',
            'analytics_events',
            'analytics_sessions',
        ] as $table) {
            Schema::connection($this->analyticsConnection)->dropIfExists($table);
        }
    }
};
