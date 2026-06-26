<?php

namespace Tests\Feature;

use App\Services\Analytics\AnalyticsDateRangePresets;
use Carbon\Carbon;
use Tests\TestCase;

class AnalyticsDateRangePresetsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_presets_without_lag_anchor_to_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00', 'Europe/Warsaw'));

        $presets = (new AnalyticsDateRangePresets())->build('Europe/Warsaw', 0);
        $byKey = collect($presets)->keyBy('key');

        $this->assertSame('2026-06-20', $byKey['7d']['date_from']);
        $this->assertSame('2026-06-26', $byKey['7d']['date_to']);
        $this->assertSame('2026-06-13', $byKey['14d']['date_from']);
        $this->assertSame('2026-06-26', $byKey['14d']['date_to']);
    }

    public function test_presets_with_lag_anchor_to_mature_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00', 'Europe/Warsaw'));

        $presets = (new AnalyticsDateRangePresets())->build('Europe/Warsaw', 2);
        $byKey = collect($presets)->keyBy('key');

        // Kotwica = dziś − 2 = 2026-06-24
        $this->assertSame('2026-06-24', $byKey['7d']['date_to']);
        $this->assertSame('2026-06-18', $byKey['7d']['date_from']);
        $this->assertSame('2026-06-24', $byKey['30d']['date_to']);
        $this->assertSame('2026-05-26', $byKey['30d']['date_from']);
    }

    public function test_this_month_and_previous_month_presets(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00', 'Europe/Warsaw'));

        $byKey = collect((new AnalyticsDateRangePresets())->build('Europe/Warsaw', 0))->keyBy('key');

        $this->assertSame('2026-06-01', $byKey['mtd']['date_from']);
        $this->assertSame('2026-06-26', $byKey['mtd']['date_to']);
        $this->assertSame('2026-05-01', $byKey['prev_month']['date_from']);
        $this->assertSame('2026-05-31', $byKey['prev_month']['date_to']);
    }

    public function test_presets_return_expected_set(): void
    {
        $keys = collect((new AnalyticsDateRangePresets())->build('Europe/Warsaw'))->pluck('key')->all();

        $this->assertSame(['7d', '14d', '30d', '90d', 'mtd', 'prev_month'], $keys);
    }
}
