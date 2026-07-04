<?php

namespace App\Http\Controllers;

use App\Models\FormOrder;
use App\Models\OnlinePaymentOrder;
use App\Services\Analytics\AnalyticsDateRangePresets;
use App\Support\UtcStorageDate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardOrdersController extends Controller
{
    private const DEFAULT_RANGE_DAYS = 14;

    private const MAX_RANGE_DAYS = 366;

    public function index(Request $request)
    {
        $tz = UtcStorageDate::appTimezone();
        $todayLocal = Carbon::today($tz);
        $yesterdayLocal = Carbon::yesterday($tz);

        $todayStartUtc = UtcStorageDate::dayStartUtc($todayLocal);
        $tomorrowStartUtc = UtcStorageDate::dayStartUtc($todayLocal->copy()->addDay());
        $yesterdayStartUtc = UtcStorageDate::dayStartUtc($yesterdayLocal);

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

            if ($dateFrom->diffInDays($dateTo) + 1 > self::MAX_RANGE_DAYS) {
                throw new \InvalidArgumentException('Maksymalny zakres to '.self::MAX_RANGE_DAYS.' dni.');
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
        $dailyChart = $this->buildDailyOrderCounts($dateFrom, $dateTo, $fromUtc, $toUtc, $tz);

        $datePresets = $this->buildDatePresets($todayLocal);

        $stats = [
            'form_total' => FormOrder::count(),
            'form_today' => FormOrder::where('order_date', '>=', $todayStartUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $tomorrowStartUtc->format('Y-m-d H:i:s'))
                ->count(),
            'form_yesterday' => FormOrder::where('order_date', '>=', $yesterdayStartUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $todayStartUtc->format('Y-m-d H:i:s'))
                ->count(),
            'form_handling' => FormOrder::needsActiveHandling()->count(),
            'form_invoiced_value' => FormOrder::withInvoice()->sum('product_price'),
            'online_pending' => OnlinePaymentOrder::whereIn('status', [
                OnlinePaymentOrder::STATUS_PENDING,
                OnlinePaymentOrder::STATUS_CREATED,
            ])->count(),
            'online_paid_today' => OnlinePaymentOrder::where('status', OnlinePaymentOrder::STATUS_PAID)
                ->where('updated_at', '>=', $todayStartUtc)
                ->count(),
            'period_total' => array_sum($dailyChart['counts']),
            'period_days' => count($dailyChart['counts']),
            'period_avg' => count($dailyChart['counts']) > 0
                ? round(array_sum($dailyChart['counts']) / count($dailyChart['counts']), 1)
                : 0,
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
            'dateRangeError',
            'dailyChart',
            'tz',
        ));
    }

    /**
     * @return list<array{key: string, label: string, date_from: string, date_to: string}>
     */
    private function buildDatePresets(Carbon $todayLocal): array
    {
        $tz = UtcStorageDate::appTimezone();
        $todayStr = $todayLocal->toDateString();
        $yesterdayStr = $todayLocal->copy()->subDay()->toDateString();

        return array_merge([
            [
                'key' => 'today',
                'label' => 'Dziś',
                'date_from' => $todayStr,
                'date_to' => $todayStr,
            ],
            [
                'key' => 'yesterday',
                'label' => 'Wczoraj',
                'date_from' => $yesterdayStr,
                'date_to' => $yesterdayStr,
            ],
        ], app(AnalyticsDateRangePresets::class)->build($tz, 0));
    }

    /**
     * @return array{labels: list<string>, labels_short: list<string>, counts: list<int>}
     */
    private function buildDailyOrderCounts(
        Carbon $dateFrom,
        Carbon $dateTo,
        string $fromUtc,
        string $toUtc,
        string $tz,
    ): array {
        $countsByDay = [];
        $cursor = $dateFrom->copy();

        while ($cursor->lessThanOrEqualTo($dateTo)) {
            $countsByDay[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }

        FormOrder::query()
            ->whereBetween('order_date', [$fromUtc, $toUtc])
            ->select(['id', 'order_date'])
            ->orderBy('id')
            ->each(function (FormOrder $order) use (&$countsByDay, $tz): void {
                $raw = $order->getRawOriginal('order_date');
                if (! $raw) {
                    return;
                }

                $day = Carbon::parse((string) $raw, 'UTC')->timezone($tz)->toDateString();

                if (array_key_exists($day, $countsByDay)) {
                    $countsByDay[$day]++;
                }
            });

        $labels = [];
        $labelsShort = [];
        $counts = [];

        foreach ($countsByDay as $day => $count) {
            $dayCarbon = Carbon::parse($day, $tz)->locale('pl');
            $labels[] = $dayCarbon->isoFormat('D MMM YYYY');
            $labelsShort[] = $dayCarbon->isoFormat('D MMM');
            $counts[] = $count;
        }

        return [
            'labels' => $labels,
            'labels_short' => $labelsShort,
            'counts' => $counts,
        ];
    }
}
