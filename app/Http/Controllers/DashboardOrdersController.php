<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsDateRangePresets;
use App\Services\Dashboard\DashboardOrdersDashboardService;
use App\Services\Dashboard\DashboardOrdersStatsService;
use App\Support\UtcStorageDate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardOrdersController extends Controller
{
    public function index(Request $request, DashboardOrdersDashboardService $dashboard, DashboardOrdersStatsService $ordersStats)
    {
        $context = $dashboard->chartContextFromRequest($request);
        $todayLocal = Carbon::today($context['tz']);

        [$datePresets, $datePresetsYears] = $this->buildDatePresets($context['tz'], $todayLocal);

        $headlineStats = $ordersStats->snapshot();
        $dailyChart = $context['daily_chart'];
        $period = $context['period'];

        $stats = [
            'form_today' => $headlineStats['form_today'],
            'form_yesterday' => $headlineStats['form_yesterday'],
            'form_handling' => $headlineStats['form_handling'],
            'deferred_handling' => $headlineStats['deferred_handling'],
            'online_handling' => $headlineStats['online_handling'],
            'latest_form_order_id' => $headlineStats['latest_form_order_id'],
            'period_total' => $period['total'],
            'period_online' => $period['online'],
            'period_deferred' => $period['deferred'],
            'period_days' => count($dailyChart['total']),
            'period_avg' => $period['avg'],
            'period_avg_label' => $period['avg_label'],
        ];

        $recentFormOrders = $dashboard->recentFormOrdersQuery()->get();

        $courseSchedule = $dashboard->courseScheduleForContext($context);

        return view('dashboard.orders', [
            'stats' => $stats,
            'recentFormOrders' => $recentFormOrders,
            'todayLocal' => $todayLocal,
            'filters' => $context['filters'],
            'datePresets' => $datePresets,
            'datePresetsYears' => $datePresetsYears,
            'dateRangeError' => $context['date_range_error'],
            'dailyChart' => $dailyChart,
            'chartGranularity' => $context['chart_granularity'],
            'courseSchedule' => $courseSchedule,
            'courseScheduleRangeLabel' => ($context['filters']['date_from'] ?? '').' → '.($context['filters']['date_to'] ?? ''),
            'tz' => $context['tz'],
            'liveVisitorsEnabled' => (bool) config('analytics.live_visitors_dashboard.enabled', true),
            'liveVisitorsPollSeconds' => max(10, (int) config('analytics.live_visitors_dashboard.poll_interval_seconds', 15)),
            'dashboardPollSeconds' => max(10, (int) config('analytics.live_visitors_dashboard.poll_interval_seconds', 15)),
            'liveVisitorsDebugUrl' => config('analytics.debug_panel.enabled', false)
                ? route('analytics.debug-events.index')
                : null,
        ]);
    }

    /**
     * @return array{0: list<array{key: string, label: string, date_from: string, date_to: string}>, 1: list<array{key: string, label: string, date_from: string, date_to: string}>}
     */
    private function buildDatePresets(string $tz, Carbon $todayLocal): array
    {
        $todayStr = $todayLocal->toDateString();
        $short = app(AnalyticsDateRangePresets::class)->build($tz, 0);

        $years = [];

        $years[] = [
            'key' => 'ytd',
            'label' => 'Ten rok',
            'date_from' => $todayLocal->copy()->startOfYear()->toDateString(),
            'date_to' => $todayStr,
        ];

        $prevYear = $todayLocal->copy()->subYear();
        $years[] = [
            'key' => 'prev_year',
            'label' => 'Poprzedni rok',
            'date_from' => $prevYear->copy()->startOfYear()->toDateString(),
            'date_to' => $prevYear->copy()->endOfYear()->toDateString(),
        ];

        for ($year = (int) $todayLocal->year; $year >= 2020; $year--) {
            $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, $tz);
            $yearEnd = $year === (int) $todayLocal->year
                ? $todayLocal->copy()
                : Carbon::create($year, 12, 31, 0, 0, 0, $tz);

            $years[] = [
                'key' => 'year_'.$year,
                'label' => (string) $year,
                'date_from' => $yearStart->toDateString(),
                'date_to' => $yearEnd->toDateString(),
            ];
        }

        $years[] = [
            'key' => 'since_2020',
            'label' => 'Od 2020',
            'date_from' => '2020-01-01',
            'date_to' => $todayStr,
        ];

        return [$short, $years];
    }
}
