<?php

namespace Tests\Feature;

use App\Models\Analytics\AnalyticsDailyCampaignRevenueStat;
use App\Models\Analytics\AnalyticsDailyCourseRevenueStat;
use App\Models\Analytics\AnalyticsEvent;
use App\Services\Analytics\AnalyticsRevenueAggregationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsRevenueAggregationTest extends TestCase
{
    private AnalyticsRevenueAggregationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.revenue.timezone', 'Europe/Warsaw');
        config()->set('analytics.revenue.aggregation_lag_days', 1);
        // Połączenie `mysql` (FormOrder/MarketingCampaign) i `analytics` na SQLite in-memory,
        // żeby agregacja czytała eventy i robiła lookup FormOrder.fb_source przez Eloquent.
        config()->set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('mysql');
        DB::purge('analytics');
        $this->createAnalyticsTables();
        $this->createSupportTables();

        $this->service = app(AnalyticsRevenueAggregationService::class);
    }

    public function test_form_order_created_increments_orders_created(): void
    {
        $this->createEvent([
            'event_name' => 'form_order_created',
            'course_id' => 10,
            'metadata' => ['amount_gross' => 100],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 10)->firstOrFail();
        $this->assertSame(1, $stat->orders_created);
    }

    public function test_form_order_created_sums_ordered_revenue(): void
    {
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 10, 'metadata' => ['amount_gross' => 100.50]]);
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 10, 'metadata' => ['amount_gross' => 49.50]]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 10)->firstOrFail();
        $this->assertSame(2, $stat->orders_created);
        $this->assertSame('150.00', (string) $stat->ordered_revenue_gross);
    }

    public function test_online_paid_increments_online_paid_orders(): void
    {
        $this->createEvent([
            'event_name' => 'payment_status_changed',
            'course_id' => 11,
            'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 200],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 11)->firstOrFail();
        $this->assertSame(1, $stat->online_paid_orders);
    }

    public function test_online_paid_sums_revenue(): void
    {
        $this->createEvent(['event_name' => 'payment_status_changed', 'course_id' => 11, 'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 200]]);
        $this->createEvent(['event_name' => 'payment_status_changed', 'course_id' => 11, 'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 50]]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 11)->firstOrFail();
        $this->assertSame(2, $stat->online_paid_orders);
        $this->assertSame('250.00', (string) $stat->online_paid_revenue_gross);
    }

    public function test_payment_status_other_than_paid_is_ignored(): void
    {
        $this->createEvent([
            'event_name' => 'payment_status_changed',
            'course_id' => 11,
            'metadata' => ['payment_status' => 'pending', 'order_flow' => 'online', 'amount_gross' => 200],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $this->assertSame(0, AnalyticsDailyCourseRevenueStat::query()->count());
    }

    public function test_deferred_invoice_increments_deferred_invoiced_orders(): void
    {
        $this->createEvent([
            'event_name' => 'invoice_created',
            'course_id' => 12,
            'metadata' => ['order_flow' => 'deferred', 'amount_gross' => 300],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 12)->firstOrFail();
        $this->assertSame(1, $stat->deferred_invoiced_orders);
    }

    public function test_deferred_invoice_sums_revenue(): void
    {
        $this->createEvent(['event_name' => 'invoice_created', 'course_id' => 12, 'metadata' => ['order_flow' => 'deferred', 'amount_gross' => 300]]);
        $this->createEvent(['event_name' => 'invoice_created', 'course_id' => 12, 'metadata' => ['order_flow' => 'deferred', 'amount_gross' => 100]]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 12)->firstOrFail();
        $this->assertSame(2, $stat->deferred_invoiced_orders);
        $this->assertSame('400.00', (string) $stat->deferred_invoiced_revenue_gross);
    }

    public function test_online_invoice_does_not_increment_deferred_or_settled(): void
    {
        $this->createEvent([
            'event_name' => 'invoice_created',
            'course_id' => 13,
            'metadata' => ['order_flow' => 'online', 'amount_gross' => 500],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 13)->firstOrFail();
        $this->assertSame(0, $stat->deferred_invoiced_orders);
        $this->assertSame(0, $stat->settled_orders_total);
        $this->assertSame('0.00', (string) $stat->settled_revenue_gross);
    }

    public function test_online_invoice_increments_marker(): void
    {
        $this->createEvent(['event_name' => 'invoice_created', 'course_id' => 13, 'metadata' => ['order_flow' => 'online', 'amount_gross' => 500]]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 13)->firstOrFail();
        $this->assertSame(1, $stat->online_invoiced_marker_orders);
    }

    public function test_settled_orders_total_is_sum_of_online_paid_and_deferred(): void
    {
        $this->createEvent(['event_name' => 'payment_status_changed', 'course_id' => 14, 'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 100]]);
        $this->createEvent(['event_name' => 'invoice_created', 'course_id' => 14, 'metadata' => ['order_flow' => 'deferred', 'amount_gross' => 200]]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 14)->firstOrFail();
        $this->assertSame(2, $stat->settled_orders_total);
    }

    public function test_settled_revenue_is_sum_of_online_paid_and_deferred(): void
    {
        $this->createEvent(['event_name' => 'payment_status_changed', 'course_id' => 14, 'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 100]]);
        $this->createEvent(['event_name' => 'invoice_created', 'course_id' => 14, 'metadata' => ['order_flow' => 'deferred', 'amount_gross' => 200]]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 14)->firstOrFail();
        $this->assertSame('300.00', (string) $stat->settled_revenue_gross);
    }

    public function test_online_paid_plus_online_invoice_does_not_double_count_settled(): void
    {
        // Ten sam kurs: opłata online (wchodzi do settled) + faktura online (tylko marker).
        $this->createEvent(['event_name' => 'payment_status_changed', 'course_id' => 15, 'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 100]]);
        $this->createEvent(['event_name' => 'invoice_created', 'course_id' => 15, 'metadata' => ['order_flow' => 'online', 'amount_gross' => 100]]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 15)->firstOrFail();
        $this->assertSame(1, $stat->online_paid_orders);
        $this->assertSame(1, $stat->online_invoiced_marker_orders);
        $this->assertSame(1, $stat->settled_orders_total);
        $this->assertSame('100.00', (string) $stat->settled_revenue_gross);
    }

    public function test_course_level_aggregation_groups_by_course(): void
    {
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 21, 'metadata' => ['amount_gross' => 10]]);
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 22, 'metadata' => ['amount_gross' => 20]]);

        $this->service->aggregateForDate('2026-06-24');

        $this->assertSame(2, AnalyticsDailyCourseRevenueStat::query()->count());
    }

    public function test_campaign_aggregation_via_event_campaign_code(): void
    {
        $this->createEvent([
            'event_name' => 'form_order_created',
            'course_id' => 30,
            'campaign_code' => 'promo-event',
            'campaign_id' => 901,
            'metadata' => ['amount_gross' => 100],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCampaignRevenueStat::query()->where('campaign_code', 'promo-event')->firstOrFail();
        $this->assertSame(1, $stat->orders_created);
        $this->assertSame(901, (int) $stat->campaign_id);
    }

    public function test_campaign_aggregation_via_form_order_fb_source(): void
    {
        DB::connection('mysql')->table('form_orders')->insert([
            'id' => 5001,
            'product_id' => 40,
            'product_name' => 'Kurs X',
            'fb_source' => 'fb-camp-1',
        ]);
        DB::connection('mysql')->table('marketing_campaigns')->insert([
            'id' => 700,
            'campaign_code' => 'fb-camp-1',
        ]);

        $this->createEvent([
            'event_name' => 'payment_status_changed',
            'course_id' => 40,
            'form_order_id' => 5001,
            'campaign_code' => null,
            'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 250],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCampaignRevenueStat::query()->where('campaign_code', 'fb-camp-1')->firstOrFail();
        $this->assertSame(1, $stat->online_paid_orders);
        $this->assertSame(700, (int) $stat->campaign_id);
    }

    public function test_event_without_campaign_is_skipped_in_campaign_table(): void
    {
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 50, 'campaign_code' => null, 'metadata' => ['amount_gross' => 100]]);

        $this->service->aggregateForDate('2026-06-24');

        $this->assertSame(0, AnalyticsDailyCampaignRevenueStat::query()->count());
        $this->assertSame(1, AnalyticsDailyCourseRevenueStat::query()->count());
    }

    public function test_without_campaign_diagnostic_counters(): void
    {
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 60, 'campaign_code' => null, 'metadata' => ['amount_gross' => 100]]);
        $this->createEvent(['event_name' => 'payment_status_changed', 'course_id' => 60, 'campaign_code' => null, 'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 100]]);
        $this->createEvent(['event_name' => 'invoice_created', 'course_id' => 60, 'campaign_code' => null, 'metadata' => ['order_flow' => 'deferred', 'amount_gross' => 100]]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 60)->firstOrFail();
        $this->assertSame(1, $stat->orders_created_without_campaign);
        $this->assertSame(1, $stat->online_paid_without_campaign);
        $this->assertSame(1, $stat->deferred_invoiced_without_campaign);
    }

    public function test_aggregation_is_idempotent(): void
    {
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 70, 'campaign_code' => 'c1', 'metadata' => ['amount_gross' => 100]]);

        $this->service->aggregateForDate('2026-06-24');
        $this->service->aggregateForDate('2026-06-24');

        $this->assertSame(1, AnalyticsDailyCourseRevenueStat::query()->count());
        $this->assertSame(1, AnalyticsDailyCampaignRevenueStat::query()->count());
        $this->assertSame(1, (int) AnalyticsDailyCourseRevenueStat::query()->value('orders_created'));
    }

    public function test_amounts_null_zero_or_non_numeric_do_not_break_aggregation(): void
    {
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 80, 'metadata' => ['amount_gross' => null]]);
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 80, 'metadata' => ['amount_gross' => 0]]);
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 80, 'metadata' => ['amount_gross' => 'abc']]);
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 80, 'metadata' => []]);
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 80, 'metadata' => ['amount_gross' => '49.99']]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 80)->firstOrFail();
        $this->assertSame(5, $stat->orders_created);
        $this->assertSame('49.99', (string) $stat->ordered_revenue_gross);
    }

    public function test_aggregates_contain_no_pii(): void
    {
        DB::connection('mysql')->table('form_orders')->insert([
            'id' => 5002,
            'product_id' => 90,
            'product_name' => 'Kurs Y',
            'fb_source' => 'pii-camp',
        ]);

        $this->createEvent([
            'event_name' => 'form_order_created',
            'course_id' => 90,
            'form_order_id' => 5002,
            'campaign_code' => 'pii-camp',
            'metadata' => ['amount_gross' => 100, 'buyer_type' => 'person'],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $courseRow = AnalyticsDailyCourseRevenueStat::query()->firstOrFail()->toArray();
        $campaignRow = AnalyticsDailyCampaignRevenueStat::query()->firstOrFail()->toArray();

        foreach (['metadata', 'form_order_id', 'payment_order_id', 'invoice_number', 'order_form_session_id'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $courseRow);
            $this->assertArrayNotHasKey($forbidden, $campaignRow);
        }
    }

    public function test_warsaw_day_boundaries(): void
    {
        // 2026-06-24 22:30 UTC = 2026-06-25 00:30 Europe/Warsaw (CEST, UTC+2).
        $this->createEvent([
            'event_name' => 'form_order_created',
            'course_id' => 100,
            'occurred_at' => '2026-06-24 22:30:00',
            'metadata' => ['amount_gross' => 100],
        ]);

        $this->service->aggregateForDate('2026-06-24');
        $this->assertSame(0, AnalyticsDailyCourseRevenueStat::query()->whereDate('stat_date', '2026-06-24')->count());

        $this->service->aggregateForDate('2026-06-25');
        $this->assertSame(1, AnalyticsDailyCourseRevenueStat::query()->whereDate('stat_date', '2026-06-25')->count());
    }

    public function test_command_runs_for_single_date(): void
    {
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 110, 'metadata' => ['amount_gross' => 100]]);

        $this->artisan('analytics:aggregate-revenue', ['--date' => '2026-06-24'])->assertSuccessful();

        $this->assertSame(1, (int) AnalyticsDailyCourseRevenueStat::query()->value('orders_created'));
    }

    public function test_command_runs_for_date_range(): void
    {
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 120, 'occurred_at' => '2026-06-24 10:00:00', 'metadata' => ['amount_gross' => 100]]);
        $this->createEvent(['event_name' => 'form_order_created', 'course_id' => 120, 'occurred_at' => '2026-06-25 10:00:00', 'metadata' => ['amount_gross' => 100]]);

        $this->artisan('analytics:aggregate-revenue', ['--from' => '2026-06-24', '--to' => '2026-06-25'])->assertSuccessful();

        $this->assertSame(2, AnalyticsDailyCourseRevenueStat::query()->count());
    }

    public function test_event_without_course_id_uses_form_order_product_id(): void
    {
        DB::connection('mysql')->table('form_orders')->insert([
            'id' => 5003,
            'product_id' => 130,
            'product_name' => 'Kurs Z',
            'fb_source' => null,
        ]);

        $this->createEvent([
            'event_name' => 'payment_status_changed',
            'course_id' => null,
            'form_order_id' => 5003,
            'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 100],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseRevenueStat::query()->where('course_id', 130)->firstOrFail();
        $this->assertSame(1, $stat->online_paid_orders);
    }

    public function test_event_without_course_and_without_lookup_is_skipped(): void
    {
        $this->createEvent([
            'event_name' => 'payment_status_changed',
            'course_id' => null,
            'form_order_id' => null,
            'metadata' => ['payment_status' => 'paid', 'order_flow' => 'online', 'amount_gross' => 100],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $this->assertSame(0, AnalyticsDailyCourseRevenueStat::query()->count());
    }

    public function test_default_stat_date_uses_lag_of_one_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 03:30:00', 'Europe/Warsaw'));

        $this->assertSame('2026-06-25', $this->service->defaultStatDate()->toDateString());

        Carbon::setTestNow();
    }

    private function createEvent(array $overrides = []): AnalyticsEvent
    {
        $occurredAt = $overrides['occurred_at'] ?? '2026-06-24 10:00:00';
        if ($occurredAt instanceof Carbon) {
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

    private function createAnalyticsTables(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_daily_campaign_revenue_stats');
        Schema::connection('analytics')->dropIfExists('analytics_daily_course_revenue_stats');
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

    private function createSupportTables(): void
    {
        Schema::connection('mysql')->dropIfExists('form_orders');
        Schema::connection('mysql')->create('form_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('fb_source')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('mysql')->dropIfExists('marketing_campaigns');
        Schema::connection('mysql')->create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code')->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
