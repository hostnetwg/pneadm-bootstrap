<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsAbandonmentHealthcheckCommandTest extends TestCase
{
    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputBufferLevel = ob_get_level();

        config()->set('analytics.abandonment.timezone', 'Europe/Warsaw');
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('analytics');
        $this->createTables();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_healthcheck_passes_when_buckets_are_consistent(): void
    {
        $this->seedStat('2026-06-25', sessions: 38, buckets: [
            'viewed_not_started' => 28,
            'started_not_submit_clicked' => 1,
            'submit_clicked_not_attempted' => 1,
            'submit_attempted_not_created' => 1,
            'converted' => 7,
        ]);

        $this->artisan('analytics:abandonment-healthcheck', ['--from' => '2026-06-20', '--to' => '2026-06-26'])
            ->expectsOutputToContain('WERDYKT: agregaty B3 spójne')
            ->assertExitCode(0);
    }

    public function test_healthcheck_fails_when_buckets_are_inconsistent(): void
    {
        // suma kubełków (10) != sessions_total (38)
        $this->seedStat('2026-06-25', sessions: 38, buckets: [
            'viewed_not_started' => 5,
            'converted' => 5,
        ]);

        $this->artisan('analytics:abandonment-healthcheck', ['--from' => '2026-06-20', '--to' => '2026-06-26'])
            ->expectsOutputToContain('wykryto niespójność')
            ->assertExitCode(1);
    }

    public function test_healthcheck_handles_empty_range_without_error(): void
    {
        $this->artisan('analytics:abandonment-healthcheck', ['--days' => 3])
            ->assertExitCode(0);
    }

    public function test_healthcheck_reports_js_events_inflow(): void
    {
        $this->seedEvent('order_form_started', '2026-06-25 10:00:00');
        $this->seedEvent('order_form_viewed', '2026-06-25 10:01:00');
        $this->seedStat('2026-06-25', sessions: 1, buckets: ['converted' => 1]);

        $this->artisan('analytics:abandonment-healthcheck', ['--from' => '2026-06-25', '--to' => '2026-06-25'])
            ->expectsOutputToContain('order_form_started')
            ->assertExitCode(0);
    }

    /**
     * @param  array<string, int>  $buckets
     */
    private function seedStat(string $date, int $sessions, array $buckets): void
    {
        DB::connection('analytics')->table('analytics_daily_form_abandonment_stats')->insert(array_merge([
            'stat_date' => $date,
            'course_id' => 100,
            'course_title_snapshot' => 'Kurs',
            'sessions_total' => $sessions,
            'reached_viewed' => $sessions,
            'reached_started' => 0,
            'reached_submit_clicked' => 0,
            'reached_submit_attempted' => 0,
            'reached_created' => $buckets['converted'] ?? 0,
            'viewed_not_started' => 0,
            'started_not_submit_clicked' => 0,
            'submit_clicked_not_attempted' => 0,
            'submit_attempted_not_created' => 0,
            'converted' => 0,
        ], $buckets));
    }

    private function seedEvent(string $name, string $occurredAt): void
    {
        DB::connection('analytics')->table('analytics_events')->insert([
            'event_uuid' => (string) Str::uuid(),
            'event_name' => $name,
            'event_category' => 'order_form',
            'occurred_at' => $occurredAt,
            'app_source' => 'pnedu',
            'order_form_session_id' => (string) Str::uuid(),
            'course_id' => 100,
        ]);
    }

    private function createTables(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_daily_form_abandonment_stats');
        Schema::connection('analytics')->dropIfExists('analytics_events');

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

        Schema::connection('analytics')->create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_name', 100);
            $table->string('event_category', 50);
            $table->timestamp('occurred_at');
            $table->string('app_source', 32);
            $table->uuid('order_form_session_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
