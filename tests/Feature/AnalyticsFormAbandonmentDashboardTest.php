<?php

namespace Tests\Feature;

use App\Models\Analytics\AnalyticsDailyCampaignAbandonmentStat;
use App\Models\Analytics\AnalyticsDailyFormAbandonmentStat;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalyticsFormAbandonmentDashboardTest extends TestCase
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

        config()->set('analytics.form_abandonment_dashboard.enabled', true);
        config()->set('analytics.form_abandonment_dashboard.timezone', 'Europe/Warsaw');
        config()->set('analytics.form_abandonment_dashboard.default_days', 14);
        config()->set('analytics.form_abandonment_dashboard.max_days', 366);
        config()->set('analytics.abandonment.aggregation_lag_days', 2);
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createAbandonmentTables();
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
        $this->get(route('analytics.form-abandonments.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->get(route('analytics.form-abandonments.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_dashboard_with_empty_state_when_no_data(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index'))
            ->assertOk()
            ->assertSee('Porzucenia formularza')
            ->assertSee('Brak danych');
    }

    public function test_admin_sees_summary_for_test_data(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Szkolenie testowe', [
            'sessions_total' => 10,
            'reached_started' => 6,
            'reached_submit_clicked' => 4,
            'reached_submit_attempted' => 3,
            'reached_created' => 2,
            'viewed_not_started' => 4,
            'started_not_submit_clicked' => 2,
            'submit_clicked_not_attempted' => 1,
            'submit_attempted_not_created' => 1,
            'converted' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ]))
            ->assertOk()
            ->assertSee('Szkolenie testowe')
            ->assertSee('Weszli, ale nie zaczęli formularza')
            ->assertSee('Zakończone zamówieniem');
    }

    public function test_course_rows_are_summed_over_date_range(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-10', 200, 'Kurs 200', ['sessions_total' => 5, 'converted' => 1, 'viewed_not_started' => 4]);
        $this->seedCourse('2026-06-11', 200, 'Kurs 200', ['sessions_total' => 7, 'converted' => 2, 'viewed_not_started' => 5]);

        $service = app(\App\Services\Analytics\AnalyticsFormAbandonmentDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-01', 'date_to' => '2026-06-30']);

        $this->assertSame(12, $data['summary']['sessions_total']);
        $this->assertCount(1, $data['courses']);
        $this->assertSame(12, $data['courses'][0]['sessions_total']);
        $this->assertSame(3, $data['courses'][0]['converted']);
    }

    public function test_campaign_rows_are_summed_over_date_range(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCampaign('2026-06-10', 'PROMO', ['sessions_total' => 4, 'converted' => 1, 'viewed_not_started' => 3]);
        $this->seedCampaign('2026-06-12', 'PROMO', ['sessions_total' => 6, 'converted' => 2, 'viewed_not_started' => 4]);

        $service = app(\App\Services\Analytics\AnalyticsFormAbandonmentDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-01', 'date_to' => '2026-06-30']);

        $this->assertCount(1, $data['campaigns']);
        $this->assertSame('PROMO', $data['campaigns'][0]['campaign_code']);
        $this->assertSame(10, $data['campaigns'][0]['sessions_total']);
        $this->assertSame(3, $data['campaigns'][0]['converted']);
    }

    public function test_date_filter_limits_visible_rows(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-05', 100, 'W zakresie', ['sessions_total' => 3, 'viewed_not_started' => 3]);
        $this->seedCourse('2026-05-01', 100, 'Poza zakresem', ['sessions_total' => 99, 'viewed_not_started' => 99]);

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ]))
            ->assertOk()
            ->assertSee('W zakresie')
            ->assertDontSee('99');
    }

    public function test_course_id_filter_works(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-10', 200, 'Kurs 200', ['sessions_total' => 5, 'viewed_not_started' => 5]);
        $this->seedCourse('2026-06-10', 300, 'Kurs 300', ['sessions_total' => 8, 'viewed_not_started' => 8]);

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'course_id' => 200,
            ]))
            ->assertOk()
            ->assertSee('Kurs 200')
            ->assertDontSee('Kurs 300');
    }

    public function test_campaign_code_filter_works(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCampaign('2026-06-10', 'KEEP', ['sessions_total' => 5, 'viewed_not_started' => 5]);
        $this->seedCampaign('2026-06-10', 'SKIP', ['sessions_total' => 8, 'viewed_not_started' => 8]);

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'campaign_code' => 'KEEP',
            ]))
            ->assertOk()
            ->assertSee('KEEP')
            ->assertDontSee('SKIP');
    }

    public function test_percentages_do_not_divide_by_zero(): void
    {
        $admin = $this->userWithRole('admin');

        $service = app(\App\Services\Analytics\AnalyticsFormAbandonmentDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-01', 'date_to' => '2026-06-30']);

        $this->assertSame(0, $data['summary']['sessions_total']);
        $this->assertNull($data['summary']['conversion_rate']);
        foreach ($data['buckets'] as $bucket) {
            $this->assertNull($bucket['percent']);
        }

        // Render nie może się wywalić przy zerze.
        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index'))
            ->assertOk();
    }

    public function test_buckets_sum_to_sessions_total(): void
    {
        $this->seedCourse('2026-06-15', 100, 'Kurs', [
            'sessions_total' => 10,
            'viewed_not_started' => 4,
            'started_not_submit_clicked' => 2,
            'submit_clicked_not_attempted' => 1,
            'submit_attempted_not_created' => 1,
            'converted' => 2,
        ]);

        $service = app(\App\Services\Analytics\AnalyticsFormAbandonmentDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-01', 'date_to' => '2026-06-30']);

        $bucketSum = array_sum(array_column($data['buckets'], 'count'));
        $this->assertSame((int) $data['summary']['sessions_total'], $bucketSum);
    }

    public function test_view_does_not_expose_pii_or_raw_metadata(): void
    {
        $admin = $this->userWithRole('admin');

        // Zasiewamy dane z potencjalnym PII w tytule snapshotu — i tak nie powinno tu trafić nic wrażliwego,
        // bo serwis czyta tylko liczniki + course_title_snapshot/campaign_code (świadomie bez PII).
        $this->seedCourse('2026-06-15', 100, 'Szkolenie RKO', ['sessions_total' => 5, 'viewed_not_started' => 5]);
        $this->seedCampaign('2026-06-15', 'NEWSLETTER-06', ['sessions_total' => 5, 'viewed_not_started' => 5]);

        $service = app(\App\Services\Analytics\AnalyticsFormAbandonmentDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-01', 'date_to' => '2026-06-30']);
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        // Dane przekazywane do widoku to wyłącznie agregaty — żadnych kluczy metadata/PII.
        $this->assertStringNotContainsString('metadata', $encoded);
        $this->assertStringNotContainsString('@', $encoded);
        $this->assertArrayNotHasKey('events', $data);

        // Klucze wierszy kursów ograniczone do liczników + snapshot tytułu/id.
        $allowedCourseKeys = [
            'course_id', 'course_title_snapshot', 'sessions_total', 'reached_viewed',
            'reached_started', 'reached_submit_clicked', 'reached_submit_attempted', 'reached_created',
            'viewed_not_started', 'started_not_submit_clicked', 'submit_clicked_not_attempted',
            'submit_attempted_not_created', 'converted', 'abandoned_total', 'conversion_rate',
        ];
        $this->assertSame([], array_diff(array_keys($data['courses'][0]), $allowedCourseKeys));
    }

    public function test_menu_contains_form_abandonments_link(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index'))
            ->assertOk()
            ->assertSee(route('analytics.form-abandonments.index'), false)
            ->assertSee('Porzucenia formularza');
    }

    public function test_dashboard_does_not_query_analytics_events_table(): void
    {
        // Strukturalna weryfikacja: usuwamy tabelę analytics_events.
        // Dashboard ma działać wyłącznie na tabelach agregatów B3.
        Schema::connection('analytics')->dropIfExists('analytics_events');
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Kurs bez eventów', ['sessions_total' => 3, 'viewed_not_started' => 3]);

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ]))
            ->assertOk()
            ->assertSee('Kurs bez eventów');
    }

    public function test_dashboard_returns_404_when_feature_disabled(): void
    {
        config()->set('analytics.form_abandonment_dashboard.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index'))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // B5 — CSV AI-safe export
    // ---------------------------------------------------------------------

    public function test_admin_can_download_courses_csv(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Szkolenie CSV', [
            'sessions_total' => 8, 'reached_viewed' => 8, 'reached_started' => 5,
            'converted' => 2, 'viewed_not_started' => 3, 'started_not_submit_clicked' => 3,
        ]);

        $response = $this->actingAs($admin)->get(route('analytics.form-abandonments.export.courses', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attachment; filename="pne-form-abandonments-courses-2026-06-01_2026-06-30.csv"',
            $response->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString('Szkolenie CSV', $this->csvBody($response));
    }

    public function test_admin_can_download_campaigns_csv(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCampaign('2026-06-15', 'PROMO-CSV', [
            'sessions_total' => 6, 'reached_viewed' => 6, 'converted' => 1, 'viewed_not_started' => 5,
        ]);

        $response = $this->actingAs($admin)->get(route('analytics.form-abandonments.export.campaigns', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attachment; filename="pne-form-abandonments-campaigns-2026-06-01_2026-06-30.csv"',
            $response->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString('PROMO-CSV', $this->csvBody($response));
    }

    public function test_non_admin_cannot_download_csv(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->get(route('analytics.form-abandonments.export.courses'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('analytics.form-abandonments.export.campaigns'))
            ->assertForbidden();
    }

    public function test_guest_cannot_download_csv(): void
    {
        $this->get(route('analytics.form-abandonments.export.courses'))->assertRedirect(route('login'));
        $this->get(route('analytics.form-abandonments.export.campaigns'))->assertRedirect(route('login'));
    }

    public function test_courses_csv_has_expected_header(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Kurs', ['sessions_total' => 1, 'reached_viewed' => 1, 'viewed_not_started' => 1]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.courses', [
            'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ])));

        $header = $this->csvLine($body, 0);
        $this->assertSame([
            'date_from', 'date_to', 'course_id', 'course_title_snapshot', 'sessions_total',
            'reached_viewed', 'reached_started', 'reached_submit_clicked', 'reached_submit_attempted',
            'reached_created', 'viewed_not_started', 'started_not_submit_clicked',
            'submit_clicked_not_attempted', 'submit_attempted_not_created', 'converted',
            'abandoned_total', 'conversion_rate', 'viewed_not_started_rate',
            'started_not_submit_clicked_rate', 'submit_clicked_not_attempted_rate',
            'submit_attempted_not_created_rate',
        ], $header);
    }

    public function test_campaigns_csv_has_expected_header(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCampaign('2026-06-15', 'C', ['sessions_total' => 1, 'reached_viewed' => 1, 'viewed_not_started' => 1]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.campaigns', [
            'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ])));

        $header = $this->csvLine($body, 0);
        $this->assertSame([
            'date_from', 'date_to', 'campaign_code', 'campaign_id', 'campaign_name', 'sessions_total',
            'reached_viewed', 'reached_started', 'reached_submit_clicked', 'reached_submit_attempted',
            'reached_created', 'viewed_not_started', 'started_not_submit_clicked',
            'submit_clicked_not_attempted', 'submit_attempted_not_created', 'converted',
            'abandoned_total', 'conversion_rate', 'viewed_not_started_rate',
            'started_not_submit_clicked_rate', 'submit_clicked_not_attempted_rate',
            'submit_attempted_not_created_rate',
        ], $header);
    }

    public function test_csv_respects_date_filter(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-05', 100, 'W zakresie CSV', ['sessions_total' => 3, 'reached_viewed' => 3, 'viewed_not_started' => 3]);
        $this->seedCourse('2026-05-01', 101, 'Poza zakresem CSV', ['sessions_total' => 9, 'reached_viewed' => 9, 'viewed_not_started' => 9]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.courses', [
            'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ])));

        $this->assertStringContainsString('W zakresie CSV', $body);
        $this->assertStringNotContainsString('Poza zakresem CSV', $body);
    }

    public function test_courses_csv_respects_course_id_filter(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-10', 200, 'Kurs 200', ['sessions_total' => 5, 'reached_viewed' => 5, 'viewed_not_started' => 5]);
        $this->seedCourse('2026-06-10', 300, 'Kurs 300', ['sessions_total' => 8, 'reached_viewed' => 8, 'viewed_not_started' => 8]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.courses', [
            'date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'course_id' => 200,
        ])));

        $this->assertStringContainsString('Kurs 200', $body);
        $this->assertStringNotContainsString('Kurs 300', $body);
    }

    public function test_campaigns_csv_respects_campaign_code_filter(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCampaign('2026-06-10', 'KEEP-CSV', ['sessions_total' => 5, 'reached_viewed' => 5, 'viewed_not_started' => 5]);
        $this->seedCampaign('2026-06-10', 'SKIP-CSV', ['sessions_total' => 8, 'reached_viewed' => 8, 'viewed_not_started' => 8]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.campaigns', [
            'date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'campaign_code' => 'KEEP-CSV',
        ])));

        $this->assertStringContainsString('KEEP-CSV', $body);
        $this->assertStringNotContainsString('SKIP-CSV', $body);
    }

    public function test_csv_counts_and_rates_are_correct(): void
    {
        $admin = $this->userWithRole('admin');
        // sessions=8, converted=2 -> conversion_rate 0.25; viewed_not_started=4 -> 0.5
        $this->seedCourse('2026-06-15', 100, 'Kurs', [
            'sessions_total' => 8, 'reached_viewed' => 8, 'reached_started' => 4,
            'reached_submit_clicked' => 3, 'reached_submit_attempted' => 2, 'reached_created' => 2,
            'viewed_not_started' => 4, 'started_not_submit_clicked' => 1,
            'submit_clicked_not_attempted' => 1, 'submit_attempted_not_created' => 0, 'converted' => 2,
        ]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.courses', [
            'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ])));

        $row = $this->csvLine($body, 1);
        $map = array_combine($this->csvLine($body, 0), $row);

        $this->assertSame('8', $map['sessions_total']);
        $this->assertSame('2', $map['converted']);
        $this->assertSame('6', $map['abandoned_total']);
        $this->assertSame('0.25', $map['conversion_rate']);
        $this->assertSame('0.5', $map['viewed_not_started_rate']);
    }

    public function test_csv_does_not_contain_pii_or_forbidden_identifiers(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Kurs', ['sessions_total' => 5, 'reached_viewed' => 5, 'viewed_not_started' => 5]);
        $this->seedCampaign('2026-06-15', 'PROMO', ['sessions_total' => 5, 'reached_viewed' => 5, 'viewed_not_started' => 5]);

        foreach (['courses', 'campaigns'] as $type) {
            $body = $this->csvBody($this->actingAs($admin)->get(route("analytics.form-abandonments.export.{$type}", [
                'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
            ])));

            foreach ([
                'analytics_session_id', 'order_form_session_id', 'form_order_id', 'payment_order_id',
                'invoice_number', 'metadata', 'email', 'user_agent', 'referrer', '@',
            ] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $body, "CSV {$type} zawiera zakazane: {$forbidden}");
            }
        }
    }

    public function test_csv_with_no_data_returns_only_header(): void
    {
        $admin = $this->userWithRole('admin');

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.courses', [
            'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ])));

        $lines = array_values(array_filter(explode("\n", trim($body)), fn ($l) => $l !== ''));
        $this->assertCount(1, $lines, 'CSV bez danych powinien mieć tylko nagłówek.');
        $this->assertStringContainsString('sessions_total', $lines[0]);
    }

    public function test_export_links_on_dashboard_carry_current_filters(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 200, 'Kurs', ['sessions_total' => 5, 'reached_viewed' => 5, 'viewed_not_started' => 5]);

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'course_id' => 200,
                'campaign_code' => 'PROMO',
            ]))
            ->assertOk()
            ->assertSee(e(route('analytics.form-abandonments.export.courses', [
                'date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'course_id' => 200, 'campaign_code' => 'PROMO',
            ])), false)
            ->assertSee(e(route('analytics.form-abandonments.export.campaigns', [
                'date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'course_id' => 200, 'campaign_code' => 'PROMO',
            ])), false);
    }

    // ---------------------------------------------------------------------
    // B6 — wykres trendu dziennego + dzienny CSV
    // ---------------------------------------------------------------------

    public function test_build_returns_daily_trend_filling_range_with_zeros(): void
    {
        $this->seedCourse('2026-06-10', 100, 'Kurs', ['sessions_total' => 5, 'reached_viewed' => 5, 'converted' => 2, 'viewed_not_started' => 3]);

        $service = app(\App\Services\Analytics\AnalyticsFormAbandonmentDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-09', 'date_to' => '2026-06-11']);

        $this->assertCount(3, $data['trend']);
        $this->assertSame('2026-06-09', $data['trend'][0]['stat_date']);
        $this->assertSame(0, $data['trend'][0]['sessions_total']);
        $this->assertSame('2026-06-10', $data['trend'][1]['stat_date']);
        $this->assertSame(5, $data['trend'][1]['sessions_total']);
        $this->assertSame(2, $data['trend'][1]['converted']);
        $this->assertSame('2026-06-11', $data['trend'][2]['stat_date']);
        $this->assertSame(0, $data['trend'][2]['sessions_total']);
    }

    public function test_daily_trend_uses_campaign_table_when_campaign_filter_set(): void
    {
        $this->seedCourse('2026-06-10', 100, 'Kurs', ['sessions_total' => 50, 'reached_viewed' => 50, 'viewed_not_started' => 50]);
        $this->seedCampaign('2026-06-10', 'PROMO', ['sessions_total' => 7, 'reached_viewed' => 7, 'converted' => 1, 'viewed_not_started' => 6]);

        $service = app(\App\Services\Analytics\AnalyticsFormAbandonmentDashboardService::class);
        $data = $service->build(['date_from' => '2026-06-10', 'date_to' => '2026-06-10', 'campaign_code' => 'PROMO']);

        $this->assertCount(1, $data['trend']);
        $this->assertSame(7, $data['trend'][0]['sessions_total']);
        $this->assertSame(1, $data['trend'][0]['converted']);
    }

    public function test_dashboard_renders_trend_chart_canvas_when_data_exists(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Kurs', ['sessions_total' => 5, 'reached_viewed' => 5, 'converted' => 1, 'viewed_not_started' => 4]);

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index', ['date_from' => '2026-06-01', 'date_to' => '2026-06-30']))
            ->assertOk()
            ->assertSee('abandonmentTrendChart')
            ->assertSee('Trend dzienny');
    }

    public function test_admin_can_download_daily_csv(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Kurs', ['sessions_total' => 8, 'reached_viewed' => 8, 'converted' => 2, 'viewed_not_started' => 4]);

        $response = $this->actingAs($admin)->get(route('analytics.form-abandonments.export.daily', [
            'date_from' => '2026-06-15', 'date_to' => '2026-06-15',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attachment; filename="pne-form-abandonments-daily-2026-06-15_2026-06-15.csv"',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_non_admin_cannot_download_daily_csv(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->get(route('analytics.form-abandonments.export.daily'))
            ->assertForbidden();
    }

    public function test_daily_csv_has_expected_header(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Kurs', ['sessions_total' => 1, 'reached_viewed' => 1, 'viewed_not_started' => 1]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.daily', [
            'date_from' => '2026-06-15', 'date_to' => '2026-06-15',
        ])));

        $this->assertSame([
            'stat_date', 'sessions_total', 'reached_viewed', 'reached_started', 'reached_submit_clicked',
            'reached_submit_attempted', 'reached_created', 'viewed_not_started', 'started_not_submit_clicked',
            'submit_clicked_not_attempted', 'submit_attempted_not_created', 'converted', 'abandoned_total',
            'conversion_rate', 'viewed_not_started_rate', 'started_not_submit_clicked_rate',
            'submit_clicked_not_attempted_rate', 'submit_attempted_not_created_rate',
        ], $this->csvLine($body, 0));
    }

    public function test_daily_csv_has_one_row_per_day_in_range(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-10', 100, 'Kurs', ['sessions_total' => 3, 'reached_viewed' => 3, 'viewed_not_started' => 3]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.daily', [
            'date_from' => '2026-06-10', 'date_to' => '2026-06-12',
        ])));

        $lines = array_values(array_filter(explode("\n", trim($body)), fn ($l) => $l !== ''));
        // 1 nagłówek + 3 dni
        $this->assertCount(4, $lines);
        $this->assertSame('2026-06-10', $this->csvLine($body, 1)[0]);
        $this->assertSame('2026-06-11', $this->csvLine($body, 2)[0]);
        $this->assertSame('2026-06-12', $this->csvLine($body, 3)[0]);
    }

    public function test_daily_csv_rates_are_correct(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Kurs', [
            'sessions_total' => 8, 'reached_viewed' => 8, 'converted' => 2, 'viewed_not_started' => 4,
        ]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.daily', [
            'date_from' => '2026-06-15', 'date_to' => '2026-06-15',
        ])));

        $map = array_combine($this->csvLine($body, 0), $this->csvLine($body, 1));
        $this->assertSame('8', $map['sessions_total']);
        $this->assertSame('6', $map['abandoned_total']);
        $this->assertSame('0.25', $map['conversion_rate']);
        $this->assertSame('0.5', $map['viewed_not_started_rate']);
    }

    public function test_daily_csv_does_not_contain_pii(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 100, 'Kurs', ['sessions_total' => 5, 'reached_viewed' => 5, 'viewed_not_started' => 5]);

        $body = $this->csvBody($this->actingAs($admin)->get(route('analytics.form-abandonments.export.daily', [
            'date_from' => '2026-06-15', 'date_to' => '2026-06-15',
        ])));

        foreach ([
            'analytics_session_id', 'order_form_session_id', 'form_order_id', 'payment_order_id',
            'invoice_number', 'metadata', 'email', '@', 'course_id', 'campaign_code',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body, "Dzienny CSV zawiera zakazane: {$forbidden}");
        }
    }

    public function test_daily_export_link_on_dashboard_carries_filters(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedCourse('2026-06-15', 200, 'Kurs', ['sessions_total' => 5, 'reached_viewed' => 5, 'viewed_not_started' => 5]);

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index', [
                'date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'course_id' => 200,
            ]))
            ->assertOk()
            ->assertSee(e(route('analytics.form-abandonments.export.daily', [
                'date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'course_id' => 200,
            ])), false);
    }

    // ---------------------------------------------------------------------
    // Przycisk "Przelicz porzucenia" (ręczna agregacja B3 z panelu)
    // ---------------------------------------------------------------------

    public function test_guest_cannot_recompute_abandonments(): void
    {
        $this->post(route('analytics.form-abandonments.recompute'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_recompute_abandonments(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->post(route('analytics.form-abandonments.recompute'))
            ->assertForbidden();
    }

    public function test_recompute_is_not_available_when_feature_disabled(): void
    {
        config()->set('analytics.form_abandonment_dashboard.enabled', false);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.form-abandonments.recompute'))
            ->assertNotFound();
    }

    public function test_admin_can_recompute_selected_range_and_it_builds_stats(): void
    {
        config()->set('analytics.abandonment.timezone', 'Europe/Warsaw');
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEventsTable();

        $this->createFunnelEvent('order_form_viewed', 'order_form', 555, 'sess-recompute-1', '2026-06-20 10:00:00');

        $this->assertSame(0, AnalyticsDailyFormAbandonmentStat::query()->count());

        $this->actingAs($admin)
            ->post(route('analytics.form-abandonments.recompute'), [
                'date_from' => '2026-06-20',
                'date_to' => '2026-06-20',
            ])
            ->assertRedirect(route('analytics.form-abandonments.index', [
                'date_from' => '2026-06-20',
                'date_to' => '2026-06-20',
            ]))
            ->assertSessionHas('recompute_status');

        $this->assertSame(1, AnalyticsDailyFormAbandonmentStat::query()->where('course_id', 555)->count());
    }

    public function test_recompute_is_idempotent_and_does_not_duplicate(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEventsTable();

        $this->createFunnelEvent('order_form_viewed', 'order_form', 666, 'sess-recompute-2', '2026-06-21 12:00:00');

        $payload = ['date_from' => '2026-06-21', 'date_to' => '2026-06-21'];

        $this->actingAs($admin)->post(route('analytics.form-abandonments.recompute'), $payload);
        $this->actingAs($admin)->post(route('analytics.form-abandonments.recompute'), $payload);

        $this->assertSame(1, AnalyticsDailyFormAbandonmentStat::query()->where('course_id', 666)->count());
    }

    public function test_recompute_rejects_range_above_configured_limit(): void
    {
        config()->set('analytics.form_abandonment_dashboard.recompute_max_days', 31);
        $admin = $this->userWithRole('admin');
        $this->createAnalyticsEventsTable();

        $this->actingAs($admin)
            ->post(route('analytics.form-abandonments.recompute'), [
                'date_from' => '2026-01-01',
                'date_to' => '2026-03-31',
            ])
            ->assertSessionHas('recompute_error');
    }

    public function test_dashboard_shows_recompute_button(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index'))
            ->assertOk()
            ->assertSee('Przelicz porzucenia')
            ->assertSee(route('analytics.form-abandonments.recompute'), false);
    }

    public function test_dashboard_shows_date_range_presets(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.form-abandonments.index'))
            ->assertOk()
            ->assertSee('Szybki zakres:')
            ->assertSee('Ostatnie 7 dni')
            ->assertSee('Ostatnie 30 dni')
            ->assertSee('Poprzedni miesiąc');
    }

    private function createFunnelEvent(string $name, string $category, int $courseId, string $sessionId, string $occurredAt): void
    {
        \App\Models\Analytics\AnalyticsEvent::query()->create([
            'event_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'event_name' => $name,
            'event_category' => $category,
            'occurred_at' => $occurredAt,
            'app_source' => 'pnedu',
            'order_form_session_id' => $sessionId,
            'course_id' => $courseId,
            'course_title_snapshot' => 'Kurs '.$courseId,
            'campaign_code' => null,
        ]);
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
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    private function csvBody(\Illuminate\Testing\TestResponse $response): string
    {
        $response->assertOk();
        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        // Usuń BOM UTF-8 dla łatwiejszego porównywania.
        return preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
    }

    /**
     * @return list<string>
     */
    private function csvLine(string $body, int $index): array
    {
        $lines = array_values(array_filter(explode("\n", trim($body)), fn ($l) => $l !== ''));

        return str_getcsv($lines[$index] ?? '');
    }

    private function seedCourse(string $date, int $courseId, ?string $title, array $overrides = []): void
    {
        AnalyticsDailyFormAbandonmentStat::query()->create(array_merge([
            'stat_date' => $date,
            'course_id' => $courseId,
            'course_title_snapshot' => $title,
            'sessions_total' => 0,
            'reached_viewed' => 0,
            'reached_started' => 0,
            'reached_submit_clicked' => 0,
            'reached_submit_attempted' => 0,
            'reached_created' => 0,
            'viewed_not_started' => 0,
            'started_not_submit_clicked' => 0,
            'submit_clicked_not_attempted' => 0,
            'submit_attempted_not_created' => 0,
            'converted' => 0,
        ], $overrides));
    }

    private function seedCampaign(string $date, string $code, array $overrides = []): void
    {
        AnalyticsDailyCampaignAbandonmentStat::query()->create(array_merge([
            'stat_date' => $date,
            'campaign_code' => $code,
            'campaign_id' => null,
            'sessions_total' => 0,
            'reached_viewed' => 0,
            'reached_started' => 0,
            'reached_submit_clicked' => 0,
            'reached_submit_attempted' => 0,
            'reached_created' => 0,
            'viewed_not_started' => 0,
            'started_not_submit_clicked' => 0,
            'submit_clicked_not_attempted' => 0,
            'submit_attempted_not_created' => 0,
            'converted' => 0,
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

    private function createAbandonmentTables(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_daily_form_abandonment_stats');
        Schema::connection('analytics')->dropIfExists('analytics_daily_campaign_abandonment_stats');

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
