<?php

namespace Tests\Unit;

use App\Support\TrainerInvoicePeriodFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrainerInvoicePeriodFilterTest extends TestCase
{
    public function test_current_month_resolves_dates(): void
    {
        $now = Carbon::parse('2026-05-15');
        $resolved = TrainerInvoicePeriodFilter::resolve(
            new Request(['period' => TrainerInvoicePeriodFilter::PERIOD_CURRENT_MONTH]),
            $now
        );

        $this->assertSame('2026-05-01', $resolved['date_from']);
        $this->assertSame('2026-05-31', $resolved['date_to']);
    }

    public function test_custom_period_uses_request_dates(): void
    {
        $resolved = TrainerInvoicePeriodFilter::resolve(new Request([
            'period' => TrainerInvoicePeriodFilter::PERIOD_CUSTOM,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]));

        $this->assertSame('2026-01-01', $resolved['date_from']);
        $this->assertSame('2026-01-31', $resolved['date_to']);
    }
}
