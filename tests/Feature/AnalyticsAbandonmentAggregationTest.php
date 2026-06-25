<?php

namespace Tests\Feature;

use App\Models\Analytics\AnalyticsDailyCampaignAbandonmentStat;
use App\Models\Analytics\AnalyticsDailyFormAbandonmentStat;
use App\Models\Analytics\AnalyticsEvent;
use App\Services\Analytics\AnalyticsAbandonmentAggregationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsAbandonmentAggregationTest extends TestCase
{
    private AnalyticsAbandonmentAggregationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.abandonment.timezone', 'Europe/Warsaw');
        config()->set('analytics.abandonment.aggregation_lag_days', 2);
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createAnalyticsTables();

        $this->service = app(AnalyticsAbandonmentAggregationService::class);
    }

    public function test_viewed_not_started_bucket(): void
    {
        $this->createSession('s-1', 201, ['order_form_viewed'], '2026-06-24 10:00:00');

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyFormAbandonmentStat::query()->where('course_id', 201)->firstOrFail();
        $this->assertSame(1, $stat->sessions_total);
        $this->assertSame(1, $stat->viewed_not_started);
        $this->assertSame(1, $stat->reached_viewed);
        $this->assertSame(0, $stat->reached_started);
        $this->assertSame(0, $stat->converted);
    }

    public function test_started_not_submit_clicked_bucket(): void
    {
        $this->createSession('s-2', 202, ['order_form_viewed', 'order_form_started'], '2026-06-24 10:00:00');

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyFormAbandonmentStat::query()->where('course_id', 202)->firstOrFail();
        $this->assertSame(1, $stat->started_not_submit_clicked);
        $this->assertSame(1, $stat->reached_started);
        $this->assertSame(0, $stat->viewed_not_started);
    }

    public function test_submit_clicked_not_attempted_bucket(): void
    {
        $this->createSession('s-3', 203, ['order_form_viewed', 'order_form_started', 'order_form_submit_clicked'], '2026-06-24 10:00:00');

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyFormAbandonmentStat::query()->where('course_id', 203)->firstOrFail();
        $this->assertSame(1, $stat->submit_clicked_not_attempted);
        $this->assertSame(1, $stat->reached_submit_clicked);
    }

    public function test_submit_attempted_not_created_bucket(): void
    {
        $this->createSession('s-4', 204, ['order_form_viewed', 'order_form_submit_attempted'], '2026-06-24 10:00:00');

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyFormAbandonmentStat::query()->where('course_id', 204)->firstOrFail();
        $this->assertSame(1, $stat->submit_attempted_not_created);
        $this->assertSame(1, $stat->reached_submit_attempted);
    }

    public function test_converted_session_is_not_an_abandonment(): void
    {
        $this->createSession('s-5', 205, [
            'order_form_viewed',
            'order_form_started',
            'order_form_submit_clicked',
            'order_form_submit_attempted',
            'form_order_created',
        ], '2026-06-24 10:00:00');

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyFormAbandonmentStat::query()->where('course_id', 205)->firstOrFail();
        $this->assertSame(1, $stat->converted);
        $this->assertSame(1, $stat->sessions_total);
        $this->assertSame(0, $stat->viewed_not_started);
        $this->assertSame(0, $stat->submit_attempted_not_created);
    }

    public function test_buckets_sum_to_sessions_total(): void
    {
        $this->createSession('a', 300, ['order_form_viewed'], '2026-06-24 09:00:00');
        $this->createSession('b', 300, ['order_form_viewed', 'order_form_started'], '2026-06-24 09:10:00');
        $this->createSession('c', 300, ['order_form_viewed', 'order_form_submit_attempted', 'form_order_created'], '2026-06-24 09:20:00');

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyFormAbandonmentStat::query()->where('course_id', 300)->firstOrFail();
        $this->assertSame(3, $stat->sessions_total);
        $sum = $stat->viewed_not_started
            + $stat->started_not_submit_clicked
            + $stat->submit_clicked_not_attempted
            + $stat->submit_attempted_not_created
            + $stat->converted;
        $this->assertSame(3, $sum);
    }

    public function test_session_attributed_to_first_event_day(): void
    {
        // occurred_at w UTC. Pierwszy event: 2026-06-24 21:30 UTC = 23:30 Warszawa (dzień 24).
        // Kontynuacja: 2026-06-25 04:00 UTC = 06:00 Warszawa (dzień 25). Sesja liczona tylko w dniu 24.
        $this->createSession('cross', 401, ['order_form_viewed'], '2026-06-24 21:30:00');
        $this->createEvent([
            'event_name' => 'order_form_started',
            'order_form_session_id' => 'cross',
            'course_id' => 401,
            'occurred_at' => '2026-06-25 04:00:00',
        ]);

        $this->service->aggregateForDate('2026-06-25');
        $this->assertSame(0, AnalyticsDailyFormAbandonmentStat::query()->whereDate('stat_date', '2026-06-25')->count());

        $this->service->aggregateForDate('2026-06-24');
        $stat = AnalyticsDailyFormAbandonmentStat::query()->whereDate('stat_date', '2026-06-24')->firstOrFail();
        $this->assertSame(1, $stat->sessions_total);
        $this->assertSame(1, $stat->started_not_submit_clicked);
    }

    public function test_campaign_level_aggregation(): void
    {
        $this->createSession('camp-1', 501, ['order_form_viewed'], '2026-06-24 10:00:00', [
            'campaign_code' => 'promo-x',
            'campaign_id' => 777,
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCampaignAbandonmentStat::query()->where('campaign_code', 'promo-x')->firstOrFail();
        $this->assertSame(1, $stat->sessions_total);
        $this->assertSame(1, $stat->viewed_not_started);
        $this->assertSame(777, (int) $stat->campaign_id);
    }

    public function test_campaign_attribution_is_first_touch_not_dominant(): void
    {
        // Sesja: pierwszy event kampania A (111), potem dwa eventy kampania B (222).
        // First-touch => A wygrywa, mimo że B występuje częściej (dominanta dałaby B).
        $this->createEvent([
            'event_name' => 'order_form_viewed',
            'order_form_session_id' => 'ft',
            'course_id' => 511,
            'campaign_code' => 'camp-a',
            'campaign_id' => 111,
            'occurred_at' => '2026-06-24 10:00:00',
        ]);
        $this->createEvent([
            'event_name' => 'order_form_started',
            'order_form_session_id' => 'ft',
            'course_id' => 511,
            'campaign_code' => 'camp-b',
            'campaign_id' => 222,
            'occurred_at' => '2026-06-24 10:05:00',
        ]);
        $this->createEvent([
            'event_name' => 'order_form_submit_clicked',
            'order_form_session_id' => 'ft',
            'course_id' => 511,
            'campaign_code' => 'camp-b',
            'campaign_id' => 222,
            'occurred_at' => '2026-06-24 10:06:00',
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $this->assertSame(1, AnalyticsDailyCampaignAbandonmentStat::query()->count());
        $stat = AnalyticsDailyCampaignAbandonmentStat::query()->firstOrFail();
        $this->assertSame('camp-a', $stat->campaign_code);
        $this->assertSame(111, (int) $stat->campaign_id);
        $this->assertSame(1, $stat->sessions_total);
    }

    public function test_campaign_first_touch_skips_leading_events_without_campaign(): void
    {
        // Pierwszy event bez kampanii, dopiero kolejny ma kampanię — first-touch = pierwsza NIEPUSTA.
        $this->createEvent([
            'event_name' => 'order_form_viewed',
            'order_form_session_id' => 'ft2',
            'course_id' => 512,
            'campaign_code' => null,
            'occurred_at' => '2026-06-24 09:00:00',
        ]);
        $this->createEvent([
            'event_name' => 'order_form_started',
            'order_form_session_id' => 'ft2',
            'course_id' => 512,
            'campaign_code' => 'late-camp',
            'campaign_id' => 333,
            'occurred_at' => '2026-06-24 09:05:00',
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $stat = AnalyticsDailyCampaignAbandonmentStat::query()->firstOrFail();
        $this->assertSame('late-camp', $stat->campaign_code);
        $this->assertSame(333, (int) $stat->campaign_id);
    }

    public function test_session_without_campaign_is_skipped_in_campaign_table(): void
    {
        $this->createSession('no-camp', 601, ['order_form_viewed'], '2026-06-24 10:00:00');

        $this->service->aggregateForDate('2026-06-24');

        $this->assertSame(0, AnalyticsDailyCampaignAbandonmentStat::query()->count());
        $this->assertSame(1, AnalyticsDailyFormAbandonmentStat::query()->count());
    }

    public function test_aggregation_is_idempotent(): void
    {
        $this->createSession('idem', 701, ['order_form_viewed', 'order_form_started'], '2026-06-24 10:00:00');

        $this->service->aggregateForDate('2026-06-24');
        $this->service->aggregateForDate('2026-06-24');

        $this->assertSame(1, AnalyticsDailyFormAbandonmentStat::query()->count());
        $this->assertSame(1, AnalyticsDailyFormAbandonmentStat::query()->value('started_not_submit_clicked'));
    }

    public function test_default_stat_date_uses_lag(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 03:15:00', 'Europe/Warsaw'));

        $this->assertSame('2026-06-24', $this->service->defaultStatDate()->toDateString());

        Carbon::setTestNow();
    }

    public function test_command_runs_for_single_date(): void
    {
        $this->createSession('cmd', 801, ['order_form_viewed'], '2026-06-24 10:00:00');

        $this->artisan('analytics:aggregate-abandonments', ['--date' => '2026-06-24'])
            ->assertSuccessful();

        $this->assertSame(1, AnalyticsDailyFormAbandonmentStat::query()->value('viewed_not_started'));
    }

    public function test_aggregates_do_not_store_metadata_or_pii(): void
    {
        $this->createSession('pii', 901, ['order_form_viewed'], '2026-06-24 10:00:00', [
            'campaign_code' => 'pii-camp',
            'metadata' => ['secret_email' => 'secret@example.com', 'buyer_type' => 'person'],
        ]);

        $this->service->aggregateForDate('2026-06-24');

        $courseRow = AnalyticsDailyFormAbandonmentStat::query()->firstOrFail()->toArray();
        $campaignRow = AnalyticsDailyCampaignAbandonmentStat::query()->firstOrFail()->toArray();
        $encoded = json_encode([$courseRow, $campaignRow], JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('metadata', $courseRow);
        $this->assertStringNotContainsString('secret@example.com', $encoded);
    }

    /**
     * @param  list<string>  $eventNames
     * @param  array<string, mixed>  $overrides
     */
    private function createSession(string $sessionId, int $courseId, array $eventNames, string $occurredAt, array $overrides = []): void
    {
        foreach ($eventNames as $eventName) {
            $this->createEvent(array_merge([
                'event_name' => $eventName,
                'order_form_session_id' => $sessionId,
                'course_id' => $courseId,
                'occurred_at' => $occurredAt,
            ], $overrides));
        }
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
            'event_name' => 'order_form_viewed',
            'event_category' => 'order_form',
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
            'route_name' => 'courses.order-form',
            'path' => '/courses/1/order',
            'referrer_domain' => null,
            'device_type' => 'desktop',
            'browser_family' => 'chrome',
            'metadata' => [],
            'created_at' => now(),
        ], $overrides));
    }

    private function createAnalyticsTables(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_daily_campaign_abandonment_stats');
        Schema::connection('analytics')->dropIfExists('analytics_daily_form_abandonment_stats');
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
            $table->unsignedBigInteger('campaign_id')->nullable();
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
    }
}
