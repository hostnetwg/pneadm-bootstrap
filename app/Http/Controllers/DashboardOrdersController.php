<?php

namespace App\Http\Controllers;

use App\Models\FormOrder;
use App\Services\Analytics\AnalyticsDateRangePresets;
use App\Services\Dashboard\DashboardOrdersStatsService;
use App\Support\UtcStorageDate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardOrdersController extends Controller
{
    private const DEFAULT_RANGE_DAYS = 14;

    /** Najwcześniejsza dozwolona data filtra (włącznie). */
    private const EARLIEST_DATE = '2020-01-01';

    /** Powyżej tej liczby dni wykres pokazuje miesiące zamiast poszczególnych dni. */
    private const DAILY_CHART_MAX_DAYS = 90;

    public function index(Request $request, DashboardOrdersStatsService $ordersStats)
    {
        $tz = UtcStorageDate::appTimezone();
        $todayLocal = Carbon::today($tz);

        $defaultDateFrom = $todayLocal->copy()->subDays(self::DEFAULT_RANGE_DAYS - 1)->toDateString();
        $defaultDateTo = $todayLocal->toDateString();

        $filters = [
            'date_from' => $request->input('date_from', $defaultDateFrom),
            'date_to' => $request->input('date_to', $defaultDateTo),
        ];

        $dateRangeError = null;
        $dateFrom = $todayLocal->copy()->subDays(self::DEFAULT_RANGE_DAYS - 1);
        $dateTo = $todayLocal->copy();

        try {
            $dateFrom = Carbon::parse((string) $filters['date_from'], $tz)->startOfDay();
            $dateTo = Carbon::parse((string) $filters['date_to'], $tz)->startOfDay();

            if ($dateFrom->greaterThan($dateTo)) {
                throw new \InvalidArgumentException('Data „Od” nie może być późniejsza niż data „Do”.');
            }

            $earliest = Carbon::parse(self::EARLIEST_DATE, $tz)->startOfDay();
            if ($dateFrom->lessThan($earliest)) {
                throw new \InvalidArgumentException('Data „Od” nie może być wcześniejsza niż '.self::EARLIEST_DATE.'.');
            }

            if ($dateTo->greaterThan($todayLocal)) {
                $dateTo = $todayLocal->copy();
            }

            $filters['date_from'] = $dateFrom->toDateString();
            $filters['date_to'] = $dateTo->toDateString();
        } catch (\InvalidArgumentException $e) {
            $dateRangeError = $e->getMessage();
            $dateFrom = Carbon::parse($defaultDateFrom, $tz)->startOfDay();
            $dateTo = Carbon::parse($defaultDateTo, $tz)->startOfDay();
            $filters['date_from'] = $dateFrom->toDateString();
            $filters['date_to'] = $dateTo->toDateString();
        } catch (\Throwable) {
            $dateRangeError = 'Nieprawidłowy format daty.';
            $dateFrom = Carbon::parse($defaultDateFrom, $tz)->startOfDay();
            $dateTo = Carbon::parse($defaultDateTo, $tz)->startOfDay();
            $filters['date_from'] = $dateFrom->toDateString();
            $filters['date_to'] = $dateTo->toDateString();
        }

        [$fromUtc, $toUtc] = UtcStorageDate::utcRangeForLocalDays($dateFrom, $dateTo);
        $dayCount = (int) $dateFrom->diffInDays($dateTo) + 1;
        $chartGranularity = $dayCount > self::DAILY_CHART_MAX_DAYS ? 'month' : 'day';
        $dailyChart = $this->buildDashboardOrderChart(
            $dateFrom,
            $dateTo,
            $fromUtc,
            $toUtc,
            $tz,
            $chartGranularity,
        );

        [$datePresets, $datePresetsYears] = $this->buildDatePresets($tz, $todayLocal);

        $headlineStats = $ordersStats->snapshot();

        $stats = [
            'form_today' => $headlineStats['form_today'],
            'form_yesterday' => $headlineStats['form_yesterday'],
            'form_handling' => $headlineStats['form_handling'],
            'deferred_handling' => $headlineStats['deferred_handling'],
            'online_handling' => $headlineStats['online_handling'],
            'period_total' => array_sum($dailyChart['total']),
            'period_online' => array_sum($dailyChart['online']),
            'period_deferred' => array_sum($dailyChart['deferred']),
            'period_days' => count($dailyChart['total']),
            'period_avg' => count($dailyChart['total']) > 0
                ? round(array_sum($dailyChart['total']) / count($dailyChart['total']), 1)
                : 0,
            'period_avg_label' => $chartGranularity === 'month' ? 'miesiąc' : 'dzień',
        ];

        $recentFormOrders = FormOrder::query()
            ->with(['course:id,title'])
            ->orderByDesc('order_date')
            ->limit(8)
            ->get();

        return view('dashboard.orders', compact(
            'stats',
            'recentFormOrders',
            'todayLocal',
            'filters',
            'datePresets',
            'datePresetsYears',
            'dateRangeError',
            'dailyChart',
            'chartGranularity',
            'tz',
        ) + [
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
            'date_from' => self::EARLIEST_DATE,
            'date_to' => $todayStr,
        ];

        return [$short, $years];
    }

    /**
     * Wykres zamówień: z numerem FV lub w kolejce do obsługi, wg daty złożenia (order_date).
     *
     * @return array{
     *     labels: list<string>,
     *     labels_short: list<string>,
     *     online: list<int>,
     *     deferred: list<int>,
     *     total: list<int>
     * }
     */
    private function buildDashboardOrderChart(
        Carbon $dateFrom,
        Carbon $dateTo,
        string $fromUtc,
        string $toUtc,
        string $tz,
        string $granularity,
    ): array {
        $buckets = $this->initializeChartBuckets($dateFrom, $dateTo, $tz, $granularity);

        FormOrder::query()
            ->withInvoiceOrNeedsHandling()
            ->whereBetween('order_date', [$fromUtc, $toUtc])
            ->select(['id', 'order_date', 'payment_mode'])
            ->orderBy('id')
            ->each(function (FormOrder $order) use (&$buckets, $tz, $granularity): void {
                $raw = $order->getRawOriginal('order_date');
                if (! $raw) {
                    return;
                }

                $local = Carbon::parse((string) $raw, 'UTC')->timezone($tz);
                $key = $granularity === 'month'
                    ? $local->format('Y-m')
                    : $local->toDateString();

                if (! array_key_exists($key, $buckets)) {
                    return;
                }

                $buckets[$key]['total']++;

                if ($order->payment_mode === FormOrder::PAYMENT_MODE_ONLINE_GATEWAY) {
                    $buckets[$key]['online']++;
                } else {
                    $buckets[$key]['deferred']++;
                }
            });

        $labels = [];
        $labelsShort = [];
        $online = [];
        $deferred = [];
        $total = [];

        foreach ($buckets as $key => $counts) {
            if ($granularity === 'month') {
                $labelCarbon = Carbon::createFromFormat('Y-m', $key, $tz)->locale('pl')->startOfMonth();
                $labels[] = $labelCarbon->isoFormat('MMMM YYYY');
                $labelsShort[] = $labelCarbon->isoFormat('MMM YY');
            } else {
                $labelCarbon = Carbon::parse($key, $tz)->locale('pl');
                $labels[] = $labelCarbon->isoFormat('D MMM YYYY');
                $labelsShort[] = $labelCarbon->isoFormat('D MMM');
            }

            $online[] = $counts['online'];
            $deferred[] = $counts['deferred'];
            $total[] = $counts['total'];
        }

        return [
            'labels' => $labels,
            'labels_short' => $labelsShort,
            'online' => $online,
            'deferred' => $deferred,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, array{online: int, deferred: int, total: int}>
     */
    private function initializeChartBuckets(
        Carbon $dateFrom,
        Carbon $dateTo,
        string $tz,
        string $granularity,
    ): array {
        $buckets = [];

        if ($granularity === 'month') {
            $cursor = $dateFrom->copy()->startOfMonth();
            $end = $dateTo->copy()->startOfMonth();

            while ($cursor->lessThanOrEqualTo($end)) {
                $buckets[$cursor->format('Y-m')] = ['online' => 0, 'deferred' => 0, 'total' => 0];
                $cursor->addMonth();
            }

            return $buckets;
        }

        $cursor = $dateFrom->copy();

        while ($cursor->lessThanOrEqualTo($dateTo)) {
            $buckets[$cursor->toDateString()] = ['online' => 0, 'deferred' => 0, 'total' => 0];
            $cursor->addDay();
        }

        return $buckets;
    }
}
