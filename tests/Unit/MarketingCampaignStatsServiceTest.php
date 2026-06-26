<?php

namespace Tests\Unit;

use App\Services\MarketingCampaignStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class MarketingCampaignStatsServiceTest extends TestCase
{
    public function test_resolve_period_presets(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');
        $service = app(MarketingCampaignStatsService::class);

        $today = $service->resolvePeriod(Request::create('/marketing-campaigns', 'GET', ['period' => 'today']));
        $this->assertSame('today', $today['preset']);
        $this->assertSame('2026-06-18', $today['from']->toDateString());
        $this->assertSame('2026-06-18', $today['to']->toDateString());

        $week = $service->resolvePeriod(Request::create('/marketing-campaigns', 'GET', ['period' => '7d']));
        $this->assertSame('2026-06-12', $week['from']->toDateString());
        $this->assertSame('2026-06-18', $week['to']->toDateString());

        Carbon::setTestNow();
    }

    public function test_resolve_custom_range_from_dates(): void
    {
        $service = app(MarketingCampaignStatsService::class);

        $period = $service->resolvePeriod(Request::create('/marketing-campaigns', 'GET', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-10',
        ]));

        $this->assertNotNull($period);
        $this->assertSame('custom', $period['preset']);
        $this->assertSame('2026-06-01', $period['from']->toDateString());
        $this->assertSame('2026-06-10', $period['to']->toDateString());
    }

    public function test_lifetime_mode_when_no_period(): void
    {
        $service = app(MarketingCampaignStatsService::class);

        $this->assertTrue($service->isLifetimeMode($service->resolvePeriod(Request::create('/'))));
    }

    public function test_default_sort_for_metric(): void
    {
        $service = app(MarketingCampaignStatsService::class);

        $this->assertSame('link_entries_count', $service->defaultSortForMetric('entries'));
        $this->assertSame('orders_count', $service->defaultSortForMetric('orders'));
    }

    public function test_format_conversion_rate(): void
    {
        $service = app(MarketingCampaignStatsService::class);

        $this->assertSame('-', $service->formatConversionRate(0, 0));
        $this->assertSame('-', $service->formatConversionRate(5, 0));
        $this->assertSame('5,0%', $service->formatConversionRate(1, 20));
        $this->assertSame('33,3%', $service->formatConversionRate(1, 3));
    }
}
