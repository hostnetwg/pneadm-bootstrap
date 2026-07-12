<?php

namespace App\Services\Dashboard;

use App\Models\FormOrder;
use App\Support\UtcStorageDate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardOrdersDashboardService
{
    private const DEFAULT_RANGE_DAYS = 14;

    private const EARLIEST_DATE = '2020-01-01';

    private const DAILY_CHART_MAX_DAYS = 90;

    public function __construct(
        private readonly DashboardOrdersStatsService $stats,
        private readonly DashboardCourseScheduleService $courseSchedule,
    ) {}

    /**
     * @return array{
     *     filters: array{date_from: string, date_to: string},
     *     date_range_error: string|null,
     *     tz: string,
     *     chart_granularity: string,
     *     daily_chart: array{
     *         labels: list<string>,
     *         labels_short: list<string>,
     *         online: list<int>,
     *         deferred: list<int>,
     *         total: list<int>
     *     },
     *     period: array{
     *         total: int,
     *         online: int,
     *         deferred: int,
     *         avg: float,
     *         avg_label: string
     *     }
     * }
     */
    public function chartContextFromRequest(Request $request): array
    {
        $tz = UtcStorageDate::appTimezone();
        $todayLocal = Carbon::today($tz);

        $defaultDateFrom = $todayLocal->copy()->subDays(self::DEFAULT_RANGE_DAYS - 1)->toDateString();
        $defaultDateTo = $todayLocal->toDateString();

        $filters = [
            'date_from' => (string) $request->input('date_from', $defaultDateFrom),
            'date_to' => (string) $request->input('date_to', $defaultDateTo),
        ];

        $dateRangeError = null;
        $dateFrom = $todayLocal->copy()->subDays(self::DEFAULT_RANGE_DAYS - 1);
        $dateTo = $todayLocal->copy();

        try {
            $dateFrom = Carbon::parse($filters['date_from'], $tz)->startOfDay();
            $dateTo = Carbon::parse($filters['date_to'], $tz)->startOfDay();

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

        return [
            'filters' => $filters,
            'date_range_error' => $dateRangeError,
            'tz' => $tz,
            'chart_granularity' => $chartGranularity,
            'daily_chart' => $dailyChart,
            'period' => $this->periodStatsFromChart($dailyChart, $chartGranularity),
        ];
    }

    /**
     * @return array{
     *     as_of: string,
     *     form_today: int,
     *     form_yesterday: int,
     *     form_handling: int,
     *     deferred_handling: int,
     *     online_handling: int,
     *     latest_form_order_id: int,
     *     sections: array{
     *         period: array{total: int, online: int, deferred: int, avg: float, avg_label: string},
     *         chart: array{
     *             labels: list<string>,
     *             labels_short: list<string>,
     *             online: list<int>,
     *             deferred: list<int>,
     *             total: list<int>
     *         },
     *         recent_orders: list<array{
     *             id: int,
     *             ident: string,
     *             course_title: string|null,
     *             order_date: string|null,
     *             product_price: string|null,
     *             show_url: string
     *         }>,
     *         course_schedule: list<array{
     *             course_id: int,
     *             title: string,
     *             start_date: string,
     *             start_time: string,
     *             schedule_key: string,
     *             instructor_label: string|null
     *         }>,
     *         chart_granularity: string,
     *         date_range: array{from: string, to: string},
     *         shortcuts: array{form_handling: int}
     *     }
     * }
     */
    public function snapshotWithSections(Request $request): array
    {
        $context = $this->chartContextFromRequest($request);
        $headline = $this->stats->snapshot();
        $dateFrom = Carbon::parse($context['filters']['date_from'], $context['tz'])->startOfDay();
        $dateTo = Carbon::parse($context['filters']['date_to'], $context['tz'])->startOfDay();

        return array_merge($headline, [
            'sections' => [
                'period' => $context['period'],
                'chart' => $context['daily_chart'],
                'recent_orders' => $this->recentOrdersPayload(),
                'course_schedule' => $this->courseSchedule->buildForRange(
                    $dateFrom,
                    $dateTo,
                    $context['tz'],
                    $context['chart_granularity'],
                ),
                'chart_granularity' => $context['chart_granularity'],
                'date_range' => [
                    'from' => $context['filters']['date_from'],
                    'to' => $context['filters']['date_to'],
                ],
                'shortcuts' => [
                    'form_handling' => $headline['form_handling'],
                ],
            ],
        ]);
    }

    /**
     * @return list<array{
     *     course_id: int,
     *     title: string,
     *     start_date: string,
     *     start_time: string,
     *     schedule_key: string,
     *     instructor_label: string|null
     * }>
     */
    public function courseScheduleForContext(array $context): array
    {
        $dateFrom = Carbon::parse($context['filters']['date_from'], $context['tz'])->startOfDay();
        $dateTo = Carbon::parse($context['filters']['date_to'], $context['tz'])->startOfDay();

        return $this->courseSchedule->buildForRange(
            $dateFrom,
            $dateTo,
            $context['tz'],
            $context['chart_granularity'],
        );
    }

    /**
     * @param array{online: list<int>, deferred: list<int>, total: list<int>} $dailyChart
     * @return array{total: int, online: int, deferred: int, avg: float, avg_label: string}
     */
    public function periodStatsFromChart(array $dailyChart, string $chartGranularity): array
    {
        $totalCount = array_sum($dailyChart['total']);

        return [
            'total' => $totalCount,
            'online' => array_sum($dailyChart['online']),
            'deferred' => array_sum($dailyChart['deferred']),
            'avg' => count($dailyChart['total']) > 0
                ? round($totalCount / count($dailyChart['total']), 1)
                : 0.0,
            'avg_label' => $chartGranularity === 'month' ? 'miesiąc' : 'dzień',
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     ident: string,
     *     course_title: string|null,
     *     order_date: string|null,
     *     product_price: string|null,
     *     show_url: string
     * }>
     */
    public function recentOrdersPayload(): array
    {
        return FormOrder::query()
            ->with(['course:id,title'])
            ->orderByDesc('order_date')
            ->limit(8)
            ->get()
            ->map(function (FormOrder $order): array {
                $price = $order->product_price !== null
                    ? number_format((float) $order->product_price, 2, ',', ' ').' zł'
                    : null;

                return [
                    'id' => (int) $order->id,
                    'ident' => (string) $order->ident,
                    'course_title' => $order->course?->title,
                    'order_date' => $order->formatOrderDateLocal('d.m.Y H:i'),
                    'product_price' => $price,
                    'show_url' => route('form-orders.show', $order->id),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     labels_short: list<string>,
     *     labels_weekday: list<string>,
     *     date_keys: list<string>,
     *     online: list<int>,
     *     deferred: list<int>,
     *     total: list<int>
     * }
     */
    public function buildDashboardOrderChart(
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
        $labelsWeekday = [];
        $online = [];
        $deferred = [];
        $total = [];

        foreach ($buckets as $key => $counts) {
            if ($granularity === 'month') {
                $labelCarbon = Carbon::createFromFormat('Y-m', $key, $tz)->locale('pl')->startOfMonth();
                $labels[] = $labelCarbon->isoFormat('MMMM YYYY');
                $labelsShort[] = $labelCarbon->isoFormat('MMM YY');
                $labelsWeekday[] = '';
            } else {
                $labelCarbon = Carbon::parse($key, $tz)->locale('pl');
                $weekday = mb_lcfirst($labelCarbon->isoFormat('dddd'));
                $labels[] = $labelCarbon->isoFormat('D MMM YYYY').' ('.$weekday.')';
                $labelsShort[] = $labelCarbon->isoFormat('D MMM');
                $labelsWeekday[] = $weekday;
            }

            $online[] = $counts['online'];
            $deferred[] = $counts['deferred'];
            $total[] = $counts['total'];
        }

        return [
            'labels' => $labels,
            'labels_short' => $labelsShort,
            'labels_weekday' => $labelsWeekday,
            'date_keys' => array_keys($buckets),
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
