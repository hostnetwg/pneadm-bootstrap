<?php

namespace Tests\Feature;

use App\Models\Analytics\AnalyticsDailyCampaignStat;
use App\Models\Analytics\AnalyticsDailyCourseStat;
use App\Models\Analytics\AnalyticsEvent;
use App\Models\FormOrder;
use App\Services\Analytics\AnalyticsDailyAggregationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsDailyAggregationTest extends TestCase
{
    private AnalyticsDailyAggregationService $aggregationService;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.aggregation.timezone', 'Europe/Warsaw');
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createAnalyticsTables();

        $this->aggregationService = app(AnalyticsDailyAggregationService::class);
    }

    public function test_course_aggregation_maps_description_views(): void
    {
        $this->createEvent([
            'event_name' => 'course_description_viewed',
            'course_id' => 101,
            'occurred_at' => '2026-06-24 10:00:00',
        ]);

        $this->aggregationService->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseStat::query()->first();
        $this->assertNotNull($stat);
        $this->assertSame(1, $stat->views_course_description);
        $this->assertSame(0, $stat->views_order_form);
    }

    public function test_course_aggregation_maps_form_views_submit_attempts_validation_errors_and_orders(): void
    {
        $base = [
            'course_id' => 202,
            'occurred_at' => '2026-06-24 12:00:00',
        ];

        $this->createEvent(array_merge($base, ['event_name' => 'order_form_viewed']));
        $this->createEvent(array_merge($base, ['event_name' => 'order_form_submit_attempted']));
        $this->createEvent(array_merge($base, ['event_name' => 'order_form_validation_failed']));
        $this->createEvent(array_merge($base, [
            'event_name' => 'form_order_created',
            'metadata' => ['amount_gross' => 199.50],
        ]));

        $this->aggregationService->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCourseStat::query()->where('course_id', 202)->firstOrFail();
        $this->assertSame(1, $stat->views_order_form);
        $this->assertSame(1, $stat->submit_attempts);
        $this->assertSame(1, $stat->validation_failures);
        $this->assertSame(1, $stat->orders_created);
        $this->assertSame('199.50', $stat->revenue_snapshot);
    }

    public function test_campaign_aggregation_counts_short_link_visits_and_orders_with_campaign_code_only(): void
    {
        $this->createEvent([
            'event_name' => 'campaign_short_link_visit',
            'campaign_code' => 'spring-sale',
            'occurred_at' => '2026-06-24 09:00:00',
        ]);
        $this->createEvent([
            'event_name' => 'form_order_created',
            'campaign_code' => 'spring-sale',
            'course_id' => 303,
            'occurred_at' => '2026-06-24 11:00:00',
            'metadata' => ['amount_gross' => 50],
        ]);
        $this->createEvent([
            'event_name' => 'campaign_short_link_visit',
            'campaign_code' => null,
            'occurred_at' => '2026-06-24 09:30:00',
        ]);

        $this->aggregationService->aggregateForDate('2026-06-24');

        $this->assertSame(1, AnalyticsDailyCampaignStat::query()->count());
        $stat = AnalyticsDailyCampaignStat::query()->firstOrFail();
        $this->assertSame('spring-sale', $stat->campaign_code);
        $this->assertSame(1, $stat->link_entries);
        $this->assertSame(1, $stat->orders_created);
        $this->assertSame('50.00', $stat->revenue_snapshot);
    }

    public function test_aggregation_is_idempotent_for_same_date(): void
    {
        $this->createEvent([
            'event_name' => 'order_form_submit_attempted',
            'course_id' => 404,
            'occurred_at' => '2026-06-24 08:00:00',
        ]);

        $this->aggregationService->aggregateForDate('2026-06-24');
        $this->aggregationService->aggregateForDate('2026-06-24');

        $this->assertSame(1, AnalyticsDailyCourseStat::query()->count());
        $this->assertSame(1, AnalyticsDailyCourseStat::query()->value('submit_attempts'));
    }

    public function test_reaggregation_updates_counts_after_new_event_is_added(): void
    {
        $this->createEvent([
            'event_name' => 'course_description_viewed',
            'course_id' => 505,
            'occurred_at' => '2026-06-24 08:00:00',
        ]);

        $this->aggregationService->aggregateForDate('2026-06-24');
        $this->assertSame(1, AnalyticsDailyCourseStat::query()->value('views_course_description'));

        $this->createEvent([
            'event_name' => 'course_description_viewed',
            'course_id' => 505,
            'occurred_at' => '2026-06-24 09:00:00',
        ]);

        $this->aggregationService->aggregateForDate('2026-06-24');
        $this->assertSame(2, AnalyticsDailyCourseStat::query()->value('views_course_description'));
    }

    public function test_date_option_aggregates_only_selected_day_using_warsaw_timezone(): void
    {
        $this->createEvent([
            'event_name' => 'course_description_viewed',
            'course_id' => 606,
            'occurred_at' => Carbon::parse('2026-06-24 00:30:00', 'Europe/Warsaw')->utc(),
        ]);
        $this->createEvent([
            'event_name' => 'course_description_viewed',
            'course_id' => 606,
            'occurred_at' => Carbon::parse('2026-06-25 00:30:00', 'Europe/Warsaw')->utc(),
        ]);

        $this->aggregationService->aggregateForDate('2026-06-24');

        $this->assertSame(1, AnalyticsDailyCourseStat::query()->value('views_course_description'));
    }

    public function test_date_range_aggregates_each_day_and_skips_out_of_range_events(): void
    {
        $this->createEvent([
            'event_name' => 'order_form_viewed',
            'course_id' => 707,
            'occurred_at' => '2026-06-01 12:00:00',
        ]);
        $this->createEvent([
            'event_name' => 'order_form_viewed',
            'course_id' => 707,
            'occurred_at' => '2026-06-02 12:00:00',
        ]);
        $this->createEvent([
            'event_name' => 'order_form_viewed',
            'course_id' => 707,
            'occurred_at' => '2026-05-31 12:00:00',
        ]);

        $result = $this->aggregationService->aggregateForDateRange('2026-06-01', '2026-06-02');

        $this->assertSame(['2026-06-01', '2026-06-02'], $result['dates']);
        $this->assertSame(1, AnalyticsDailyCourseStat::query()->whereDate('stat_date', '2026-06-01')->value('views_order_form'));
        $this->assertSame(1, AnalyticsDailyCourseStat::query()->whereDate('stat_date', '2026-06-02')->value('views_order_form'));
        $this->assertNull(AnalyticsDailyCourseStat::query()->whereDate('stat_date', '2026-05-31')->first());
    }

    public function test_aggregates_do_not_store_metadata_json_or_pii(): void
    {
        $this->createEvent([
            'event_name' => 'form_order_created',
            'course_id' => 808,
            'campaign_code' => 'privacy-test',
            'course_title_snapshot' => 'Szkolenie testowe',
            'occurred_at' => '2026-06-24 10:00:00',
            'metadata' => [
                'amount_gross' => 120,
                'buyer_type' => 'person',
                'secret_email' => 'secret@example.com',
            ],
        ]);

        $this->aggregationService->aggregateForDate('2026-06-24');

        $courseRow = AnalyticsDailyCourseStat::query()->firstOrFail()->toArray();
        $campaignRow = AnalyticsDailyCampaignStat::query()->firstOrFail()->toArray();
        $encoded = json_encode([$courseRow, $campaignRow], JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('metadata', $courseRow);
        $this->assertArrayNotHasKey('metadata_json', $courseRow);
        $this->assertStringNotContainsString('secret@example.com', $encoded);
        $this->assertStringNotContainsString('metadata_json', $encoded);
    }

    public function test_aggregation_does_not_query_form_orders_table(): void
    {
        if (! Schema::hasTable('form_orders')) {
            $this->markTestSkipped('Brak tabeli form_orders w testowej bazie adm.');
        }

        $before = FormOrder::query()->count();

        $this->createEvent([
            'event_name' => 'form_order_created',
            'course_id' => 909,
            'occurred_at' => '2026-06-24 10:00:00',
        ]);

        $this->aggregationService->aggregateForDate('2026-06-24');

        $this->assertSame($before, FormOrder::query()->count());
    }

    public function test_aggregation_does_not_touch_legacy_daily_aggregate_tables(): void
    {
        if (! Schema::hasTable('marketing_campaign_stats_daily')
            || ! Schema::hasTable('course_page_stats_daily')) {
            $this->markTestSkipped('Brak starych tabel agregatów w testowej bazie adm.');
        }

        $campaignBefore = DB::table('marketing_campaign_stats_daily')->count();
        $courseBefore = DB::table('course_page_stats_daily')->count();

        $this->createEvent([
            'event_name' => 'campaign_short_link_visit',
            'campaign_code' => 'legacy-check',
            'occurred_at' => '2026-06-24 10:00:00',
        ]);

        $this->aggregationService->aggregateForDate('2026-06-24');

        $this->assertSame($campaignBefore, DB::table('marketing_campaign_stats_daily')->count());
        $this->assertSame($courseBefore, DB::table('course_page_stats_daily')->count());
    }

    public function test_artisan_command_runs_for_single_date(): void
    {
        $this->createEvent([
            'event_name' => 'order_form_submit_attempted',
            'course_id' => 1001,
            'occurred_at' => '2026-06-24 10:00:00',
        ]);

        $this->artisan('analytics:aggregate-daily', ['--date' => '2026-06-24'])
            ->assertSuccessful();

        $this->assertSame(1, AnalyticsDailyCourseStat::query()->value('submit_attempts'));
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
            'event_name' => 'course_description_viewed',
            'event_category' => 'landing',
            'occurred_at' => $occurredAt,
            'app_source' => 'pnedu',
            'analytics_session_id' => (string) Str::uuid(),
            'order_form_session_id' => null,
            'course_id' => null,
            'course_title_snapshot' => null,
            'campaign_id' => null,
            'campaign_code' => null,
            'campaign_channel' => null,
            'campaign_content_depth' => null,
            'cta_type' => null,
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

    private function createAnalyticsTables(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_daily_campaign_stats');
        Schema::connection('analytics')->dropIfExists('analytics_daily_course_stats');
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
            $table->string('campaign_content_depth', 50)->nullable();
            $table->string('cta_type', 50)->nullable();
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
}
