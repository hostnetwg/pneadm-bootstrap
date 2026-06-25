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
            'course_id', 'course_title_snapshot', 'sessions_total',
            'reached_started', 'reached_submit_clicked', 'reached_submit_attempted', 'reached_created',
            'viewed_not_started', 'started_not_submit_clicked', 'submit_clicked_not_attempted',
            'submit_attempted_not_created', 'converted', 'conversion_rate',
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
