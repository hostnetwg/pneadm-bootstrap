<?php

namespace Tests\Unit;

use App\Services\Analytics\AnalyticsPeriodComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalyticsPeriodComparisonTest extends TestCase
{
    private AnalyticsPeriodComparison $comparison;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comparison = new AnalyticsPeriodComparison;
    }

    public function test_previous_period_has_same_length_and_ends_day_before_current_start(): void
    {
        $period = $this->comparison->previousPeriodDates('2026-06-10', '2026-06-16');

        $this->assertSame('2026-06-03', $period['date_from']);
        $this->assertSame('2026-06-09', $period['date_to']);
        $this->assertSame(7, $period['days']);
    }

    public function test_single_day_period(): void
    {
        $period = $this->comparison->previousPeriodDates('2026-06-15', '2026-06-15');

        $this->assertSame('2026-06-14', $period['date_from']);
        $this->assertSame('2026-06-14', $period['date_to']);
        $this->assertSame(1, $period['days']);
    }

    #[DataProvider('countComparisonProvider')]
    public function test_compare_counts(float $current, float $previous, float $expectedDelta, ?float $expectedPercent): void
    {
        $result = $this->comparison->build(
            '2026-06-10',
            '2026-06-16',
            ['orders' => $current],
            ['orders' => $previous],
            ['orders'],
        );

        $metric = $result['metrics']['orders'];
        $this->assertSame($current, $metric['current']);
        $this->assertSame($previous, $metric['previous']);
        $this->assertSame($expectedDelta, $metric['delta']);
        $this->assertSame($expectedPercent, $metric['delta_percent']);
    }

    public static function countComparisonProvider(): array
    {
        return [
            'growth' => [15.0, 10.0, 5.0, 50.0],
            'decline' => [8.0, 10.0, -2.0, -20.0],
            'from_zero' => [5.0, 0.0, 5.0, null],
        ];
    }

    public function test_compare_rates_uses_percentage_points(): void
    {
        $result = $this->comparison->build(
            '2026-06-10',
            '2026-06-16',
            ['conversion_rate' => 25.0],
            ['conversion_rate' => 20.0],
            [],
            ['conversion_rate'],
        );

        $metric = $result['metrics']['conversion_rate'];
        $this->assertSame(25.0, $metric['current']);
        $this->assertSame(20.0, $metric['previous']);
        $this->assertSame(5.0, $metric['delta']);
        $this->assertNull($metric['delta_percent']);
    }
}
