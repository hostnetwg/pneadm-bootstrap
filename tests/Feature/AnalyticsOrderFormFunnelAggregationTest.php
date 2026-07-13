<?php

namespace Tests\Feature;

use App\Models\Analytics\AnalyticsDailyCampaignFunnel;
use App\Models\Analytics\AnalyticsDailyChannelFunnel;
use App\Models\Analytics\AnalyticsDailyDataQuality;
use App\Models\Analytics\AnalyticsDailyGusChannelFunnel;
use App\Models\Analytics\AnalyticsEvent;
use App\Services\Analytics\AnalyticsAbandonmentAggregationService;
use App\Services\Analytics\OrderFormFunnelAggregationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsOrderFormFunnelAggregationTest extends TestCase
{
    private OrderFormFunnelAggregationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.order_form_funnel.timezone', 'Europe/Warsaw');
        config()->set('analytics.order_form_funnel.aggregation_lag_days', 2);
        config()->set('analytics.order_form_funnel.attribution_deployed_at', '2020-01-01');

        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createAnalyticsTables();

        $this->service = app(OrderFormFunnelAggregationService::class);
    }

    public function test_command_creates_daily_channel_rows(): void
    {
        $this->seedNewsletterSession('nl-1', 101);

        $this->artisan('analytics:aggregate-order-forms', ['--date' => '2026-07-01'])->assertSuccessful();

        $row = AnalyticsDailyChannelFunnel::query()->where('traffic_channel', 'newsletter')->first();
        $this->assertNotNull($row);
        $this->assertSame(1, $row->sessions_total);
        $this->assertSame(1, $row->order_created);
    }

    public function test_aggregation_is_idempotent(): void
    {
        $this->seedNewsletterSession('nl-2', 102);
        $this->service->aggregateForDate('2026-07-01');
        $this->service->aggregateForDate('2026-07-01');

        $this->assertSame(1, AnalyticsDailyChannelFunnel::query()->count());
        $this->assertSame(1, AnalyticsDailyChannelFunnel::query()->value('sessions_total'));
    }

    public function test_counts_unique_form_session_ids(): void
    {
        $session = (string) Str::uuid();
        $this->createEvent(['order_form_session_id' => $session, 'event_name' => 'order_form_viewed', 'course_id' => 1]);
        $this->createEvent(['order_form_session_id' => $session, 'event_name' => 'form_visible', 'course_id' => 1]);
        $this->createEvent(['order_form_session_id' => $session, 'event_name' => 'form_order_created', 'course_id' => 1]);

        $this->service->aggregateForDate('2026-07-01');

        $this->assertSame(1, AnalyticsDailyChannelFunnel::query()->value('sessions_total'));
        $this->assertSame(1, AnalyticsDailyChannelFunnel::query()->value('form_visible'));
    }

    public function test_newsletter_session_maps_to_newsletter_channel(): void
    {
        $this->seedAttributedSession('s-nl', 201, 'newsletter', 'sendy', 'email', 'nl-camp');

        $this->service->aggregateForDate('2026-07-01');

        $this->assertSame('newsletter', AnalyticsDailyChannelFunnel::query()->value('traffic_channel'));
        $this->assertSame('newsletter', AnalyticsDailyChannelFunnel::query()->value('conversion_reporting_channel'));
    }

    public function test_paid_social_session_maps_correctly(): void
    {
        $this->seedAttributedSession('s-fb', 202, 'paid_social', 'facebook', 'cpc', 'fb-ads');

        $this->service->aggregateForDate('2026-07-01');

        $this->assertSame('paid_social', AnalyticsDailyChannelFunnel::query()->value('traffic_channel'));
    }

    public function test_organic_search_session_maps_correctly(): void
    {
        $this->seedAttributedSession('s-org', 203, 'organic_search', 'google', 'organic', null);

        $this->service->aggregateForDate('2026-07-01');

        $this->assertSame('organic_search', AnalyticsDailyChannelFunnel::query()->value('traffic_channel'));
    }

    public function test_session_without_attribution_maps_to_unknown(): void
    {
        $this->createSession('s-unk', 204, ['order_form_viewed']);

        $this->service->aggregateForDate('2026-07-01');

        $this->assertSame('unknown', AnalyticsDailyChannelFunnel::query()->value('traffic_channel'));
    }

    public function test_internal_site_does_not_override_conversion_reporting_channel(): void
    {
        $session = (string) Str::uuid();
        $this->createAttribution($session, [
            'traffic_channel' => 'internal_site',
            'conversion_reporting_channel' => 'newsletter',
            'traffic_source' => 'sendy',
            'traffic_medium' => 'email',
            'first_touch_channel' => 'newsletter',
            'last_external_touch_channel' => 'newsletter',
            'internal_promo_touched' => true,
            'internal_promo_placement' => 'dashboard_sidebar',
        ]);
        $this->createSession($session, 205, ['order_form_viewed', 'form_order_created']);

        $this->service->aggregateForDate('2026-07-01');

        $row = AnalyticsDailyChannelFunnel::query()->firstOrFail();
        $this->assertSame('newsletter', $row->conversion_reporting_channel);
        $this->assertSame('internal_site', $row->traffic_channel);
    }

    public function test_server_only_conversion_is_counted(): void
    {
        $session = (string) Str::uuid();
        $this->createSession($session, 206, ['order_form_viewed', 'form_order_created']);

        $this->service->aggregateForDate('2026-07-01');

        $this->assertSame(1, AnalyticsDailyChannelFunnel::query()->value('server_only_conversions'));
        $quality = AnalyticsDailyDataQuality::query()->firstOrFail();
        $this->assertSame(1, $quality->server_only_conversions);
    }

    public function test_frontend_only_abandonment_is_counted(): void
    {
        $session = (string) Str::uuid();
        $this->createSession($session, 207, ['order_form_viewed', 'form_visible', 'form_first_interaction']);

        $this->service->aggregateForDate('2026-07-01');

        $this->assertSame(1, AnalyticsDailyChannelFunnel::query()->value('frontend_only_abandonments'));
    }

    public function test_gus_success_with_order_created(): void
    {
        $session = (string) Str::uuid();
        $this->createSession($session, 208, [
            'order_form_viewed',
            'gus_lookup_clicked',
            'gus_lookup_success',
            'form_order_created',
        ], metadata: ['target' => 'buyer', 'latency_ms' => 120]);

        $this->service->aggregateForDate('2026-07-01');

        $gus = AnalyticsDailyGusChannelFunnel::query()->where('target', 'buyer')->firstOrFail();
        $this->assertSame(1, $gus->orders_after_gus_success);
        $this->assertSame(120, $gus->avg_gus_latency_ms);
    }

    public function test_gus_error_without_order_is_abandonment_after_error(): void
    {
        $session = (string) Str::uuid();
        $this->createSession($session, 209, ['order_form_viewed', 'gus_lookup_error'], metadata: ['target' => 'buyer']);

        $this->service->aggregateForDate('2026-07-01');

        $gus = AnalyticsDailyGusChannelFunnel::query()->where('target', 'buyer')->firstOrFail();
        $this->assertSame(1, $gus->abandonment_after_gus_error);
        $this->assertSame(0, $gus->orders_after_gus_success);
    }

    public function test_gus_error_then_success_then_order_is_recovered(): void
    {
        $session = (string) Str::uuid();
        $this->createEvent([
            'order_form_session_id' => $session,
            'course_id' => 210,
            'event_name' => 'order_form_viewed',
            'occurred_at' => '2026-07-01 10:00:00',
        ]);
        $this->createEvent([
            'order_form_session_id' => $session,
            'course_id' => 210,
            'event_name' => 'gus_lookup_error',
            'occurred_at' => '2026-07-01 10:01:00',
            'metadata' => ['target' => 'buyer'],
        ]);
        $this->createEvent([
            'order_form_session_id' => $session,
            'course_id' => 210,
            'event_name' => 'gus_lookup_success',
            'occurred_at' => '2026-07-01 10:02:00',
            'metadata' => ['target' => 'buyer'],
        ]);
        $this->createEvent([
            'order_form_session_id' => $session,
            'course_id' => 210,
            'event_name' => 'form_order_created',
            'occurred_at' => '2026-07-01 10:05:00',
        ]);

        $this->service->aggregateForDate('2026-07-01');

        $gus = AnalyticsDailyGusChannelFunnel::query()->where('target', 'buyer')->firstOrFail();
        $this->assertSame(1, $gus->recovered_after_gus_error);
        $this->assertSame(1, $gus->orders_after_gus_success);
        $this->assertSame(0, $gus->abandonment_after_gus_error);
    }

    public function test_campaign_report_includes_unknown_campaign_bucket(): void
    {
        $this->createSession('s-camp', 211, ['order_form_viewed']);

        $this->service->aggregateForDate('2026-07-01');

        $row = AnalyticsDailyCampaignFunnel::query()->firstOrFail();
        $this->assertNull($row->campaign_code);
        $this->assertSame(1, $row->sessions_without_campaign_metadata);
    }

    public function test_data_quality_counts_sessions_without_attribution(): void
    {
        $this->createSession('s-no-attr', 212, ['order_form_viewed', 'form_visible']);

        $this->service->aggregateForDate('2026-07-01');

        $quality = AnalyticsDailyDataQuality::query()->firstOrFail();
        $this->assertSame(1, $quality->sessions_without_attribution);
        $this->assertSame(1, $quality->sessions_without_traffic_channel);
        $this->assertSame(0.0, (float) $quality->traffic_channel_coverage_rate);
        $this->assertSame(0.0, (float) $quality->attribution_coverage_rate);
    }

    public function test_legacy_b3_abandonments_still_works(): void
    {
        $this->createSession('b3-1', 301, ['order_form_viewed', 'order_form_started'], occurredAt: '2026-07-01 09:00:00');

        app(AnalyticsAbandonmentAggregationService::class)->aggregateForDate('2026-07-01');

        $this->assertDatabaseHas('analytics_daily_form_abandonment_stats', [
            'course_id' => 301,
            'sessions_total' => 1,
            'started_not_submit_clicked' => 1,
        ], 'analytics');
    }

    public function test_recent_submit_without_backend_is_pending_not_abandoned(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 14:00:00', 'Europe/Warsaw'));

        try {
            $submitAt = Carbon::now('Europe/Warsaw')->subMinutes(5)->utc();
            $today = '2026-07-10';
            $this->createSession('pending-1', 401, [
                'order_form_viewed',
                'form_submit_clicked',
            ], occurredAt: $submitAt->format('Y-m-d H:i:s'));

            $this->service->aggregateForDate($today);

            $row = AnalyticsDailyChannelFunnel::query()->firstOrFail();
            $this->assertSame(1, $row->pending_after_submit_clicked);
            $this->assertSame(0, $row->abandoned_after_submit_clicked);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_mature_submit_without_backend_is_abandoned(): void
    {
        $this->createSession('abandon-1', 402, [
            'order_form_viewed',
            'form_submit_clicked',
        ], occurredAt: '2026-07-01 10:00:00');

        $this->service->aggregateForDate('2026-07-01');

        $row = AnalyticsDailyChannelFunnel::query()->firstOrFail();
        $this->assertSame(0, $row->pending_after_submit_clicked);
        $this->assertSame(1, $row->abandoned_after_submit_clicked);
    }

    public function test_client_validation_failed_is_validation_abandonment(): void
    {
        $this->createSession('val-1', 403, [
            'order_form_viewed',
            'form_submit_clicked',
            'client_validation_failed',
        ], occurredAt: '2026-07-01 10:00:00');

        $this->service->aggregateForDate('2026-07-01');

        $row = AnalyticsDailyChannelFunnel::query()->firstOrFail();
        $this->assertSame(1, $row->validation_abandonment);
        $this->assertSame(0, $row->abandoned_after_submit_clicked);
    }

    public function test_data_quality_low_volume_status_for_small_day(): void
    {
        $this->createSession('dq-1', 404, ['order_form_viewed'], occurredAt: '2026-06-15 10:00:00');

        $this->service->aggregateForDate('2026-06-15');

        $quality = AnalyticsDailyDataQuality::query()->firstOrFail();
        $this->assertSame('low_volume', $quality->tracking_data_quality_status);
        $this->assertContains('low_volume', $quality->tracking_data_quality_flags ?? []);
    }

    private function seedNewsletterSession(string $suffix, int $courseId): void
    {
        $session = (string) Str::uuid();
        $this->seedAttributedSession($session, $courseId, 'newsletter', 'sendy', 'email', 'nl-'.$suffix);
        $this->createSession($session, $courseId, ['order_form_viewed', 'form_visible', 'form_first_interaction', 'form_order_created']);
    }

    private function seedAttributedSession(
        string $session,
        int $courseId,
        string $trafficChannel,
        ?string $source,
        ?string $medium,
        ?string $campaign,
    ): void {
        $this->createAttribution($session, [
            'course_id' => $courseId,
            'traffic_channel' => $trafficChannel,
            'conversion_reporting_channel' => $trafficChannel,
            'traffic_source' => $source,
            'traffic_medium' => $medium,
            'traffic_campaign' => $campaign,
            'first_touch_channel' => $trafficChannel,
            'last_external_touch_channel' => $trafficChannel,
        ]);
        $this->createSession($session, $courseId, ['order_form_viewed']);
    }

    /**
     * @param  list<string>  $eventNames
     * @param  array<string, mixed>  $metadata
     */
    private function createSession(
        string $sessionId,
        int $courseId,
        array $eventNames,
        string $occurredAt = '2026-07-01 10:00:00',
        array $metadata = [],
    ): void {
        foreach ($eventNames as $eventName) {
            $this->createEvent([
                'event_name' => $eventName,
                'order_form_session_id' => $sessionId,
                'course_id' => $courseId,
                'occurred_at' => $occurredAt,
                'metadata' => $metadata,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEvent(array $overrides = []): AnalyticsEvent
    {
        $occurredAt = $overrides['occurred_at'] ?? '2026-07-01 10:00:00';
        if ($occurredAt instanceof Carbon) {
            $occurredAt = $occurredAt->format('Y-m-d H:i:s');
        }
        unset($overrides['occurred_at']);

        return AnalyticsEvent::query()->create(array_merge([
            'event_uuid' => (string) Str::uuid(),
            'event_name' => 'order_form_viewed',
            'event_category' => 'order_form',
            'occurred_at' => $occurredAt,
            'app_source' => 'pnedu',
            'analytics_session_id' => (string) Str::uuid(),
            'order_form_session_id' => null,
            'course_id' => null,
            'metadata' => [],
            'created_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAttribution(string $sessionId, array $attributes): void
    {
        DB::connection('analytics')->table('order_form_attributions')->insert(array_merge([
            'form_session_id' => $sessionId,
            'tracking_schema_version' => 2,
            'internal_promo_touched' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function createAnalyticsTables(): void
    {
        Schema::connection('analytics')->dropAllTables();

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
            $table->string('utm_campaign', 150)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('analytics')->create('order_form_attributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('form_session_id')->unique();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('traffic_channel', 50)->nullable();
            $table->string('traffic_source', 100)->nullable();
            $table->string('traffic_medium', 50)->nullable();
            $table->string('traffic_campaign', 255)->nullable();
            $table->string('conversion_reporting_channel', 50)->nullable();
            $table->string('first_touch_channel', 50)->nullable();
            $table->string('last_external_touch_channel', 50)->nullable();
            $table->boolean('internal_promo_touched')->default(false);
            $table->string('internal_promo_placement', 100)->nullable();
            $table->unsignedSmallInteger('tracking_schema_version')->default(2);
            $table->timestamps();
        });

        Schema::connection('analytics')->create('analytics_daily_channel_funnels', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('traffic_channel', 50);
            $table->string('traffic_source', 100)->nullable();
            $table->string('traffic_medium', 50)->nullable();
            $table->string('traffic_campaign', 255)->nullable();
            $table->string('conversion_reporting_channel', 50);
            $table->unsignedSmallInteger('tracking_schema_version')->default(2);
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
            $table->unsignedInteger('pending_after_submit_clicked')->default(0);
            $table->unsignedInteger('validation_abandonment')->default(0);
            $table->unsignedInteger('server_validation_abandonment')->default(0);
            $table->unsignedInteger('backend_result_missing')->default(0);
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
            $table->boolean('internal_promo_touched')->default(false);
            $table->string('internal_promo_placement', 100)->nullable();
            $table->string('internal_promo_context', 100)->nullable();
            $table->timestamps();
        });

        Schema::connection('analytics')->create('analytics_daily_course_channel_funnels', function (Blueprint $table) {
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
            $table->unsignedInteger('pending_after_submit_clicked')->default(0);
            $table->unsignedInteger('validation_abandonment')->default(0);
            $table->unsignedInteger('server_validation_abandonment')->default(0);
            $table->unsignedInteger('backend_result_missing')->default(0);
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
            $table->boolean('internal_promo_touched')->default(false);
            $table->string('internal_promo_placement', 100)->nullable();
            $table->string('internal_promo_context', 100)->nullable();
            $table->timestamps();
        });

        Schema::connection('analytics')->create('analytics_daily_campaign_funnels', function (Blueprint $table) {
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
        });

        Schema::connection('analytics')->create('analytics_daily_gus_channel_funnels', function (Blueprint $table) {
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
        });

        Schema::connection('analytics')->create('analytics_daily_data_quality', function (Blueprint $table) {
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
            $table->json('tracking_data_quality_flags')->nullable();
            $table->unsignedTinyInteger('tracking_data_quality_score')->default(0);
            $table->timestamps();
        });

        Schema::connection('analytics')->create('analytics_daily_form_abandonment_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedBigInteger('course_id');
            $table->string('course_title_snapshot', 255)->nullable();
            $table->unsignedInteger('sessions_total')->default(0);
            $table->unsignedInteger('reached_viewed')->default(0);
            $table->unsignedInteger('reached_started')->default(0);
            $table->unsignedInteger('reached_submit_clicked')->default(0);
            $table->unsignedInteger('reached_submit_attempted')->default(0);
            $table->unsignedInteger('reached_created')->default(0);
            $table->unsignedInteger('viewed_not_started')->default(0);
            $table->unsignedInteger('started_not_submit_clicked')->default(0);
            $table->unsignedInteger('submit_clicked_not_attempted')->default(0);
            $table->unsignedInteger('submit_attempted_not_created')->default(0);
            $table->unsignedInteger('converted')->default(0);
            $table->timestamps();
        });

        Schema::connection('analytics')->create('analytics_daily_campaign_abandonment_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('campaign_code', 100);
            $table->unsignedInteger('sessions_total')->default(0);
            $table->timestamps();
        });
    }
}
