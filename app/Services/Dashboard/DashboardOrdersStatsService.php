<?php

namespace App\Services\Dashboard;

use App\Models\FormOrder;
use App\Support\UtcStorageDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardOrdersStatsService
{
    private const SNAPSHOT_CACHE_SECONDS = 20;

    /**
     * @return array{
     *     as_of: string,
     *     form_today: int,
     *     form_yesterday: int,
     *     form_handling: int,
     *     deferred_handling: int,
     *     online_handling: int,
     *     latest_form_order_id: int
     * }
     */
    public function snapshot(): array
    {
        return Cache::remember(
            'dashboard.orders.stats.snapshot.v1',
            self::SNAPSHOT_CACHE_SECONDS,
            fn () => $this->buildSnapshot()
        );
    }

    /**
     * @return array{
     *     as_of: string,
     *     form_today: int,
     *     form_yesterday: int,
     *     form_handling: int,
     *     deferred_handling: int,
     *     online_handling: int,
     *     latest_form_order_id: int
     * }
     */
    private function buildSnapshot(): array
    {
        $tz = UtcStorageDate::appTimezone();
        $todayLocal = Carbon::today($tz);
        $yesterdayLocal = Carbon::yesterday($tz);

        $todayStartUtc = UtcStorageDate::dayStartUtc($todayLocal);
        $tomorrowStartUtc = UtcStorageDate::dayStartUtc($todayLocal->copy()->addDay());
        $yesterdayStartUtc = UtcStorageDate::dayStartUtc($yesterdayLocal);

        return [
            'as_of' => Carbon::now($tz)->toIso8601String(),
            'form_today' => FormOrder::includedInDashboardMetrics()
                ->where('order_date', '>=', $todayStartUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $tomorrowStartUtc->format('Y-m-d H:i:s'))
                ->count(),
            'form_yesterday' => FormOrder::includedInDashboardMetrics()
                ->where('order_date', '>=', $yesterdayStartUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $todayStartUtc->format('Y-m-d H:i:s'))
                ->count(),
            'form_handling' => FormOrder::needsActiveHandling()->count(),
            'deferred_handling' => $this->countHandlingBySettlement('deferred'),
            'online_handling' => $this->countHandlingBySettlement('online'),
            'latest_form_order_id' => (int) (FormOrder::query()->max('id') ?? 0),
        ];
    }

    private function countHandlingBySettlement(string $settlement): int
    {
        $query = FormOrder::needsActiveHandling();

        if ($settlement === 'deferred') {
            $query->where(function ($q) {
                $q->where('payment_mode', FormOrder::PAYMENT_MODE_DEFERRED_INVOICE)
                    ->orWhereNull('payment_mode');
            });
        } elseif ($settlement === 'online') {
            $query->where('payment_mode', FormOrder::PAYMENT_MODE_ONLINE_GATEWAY);
        }

        return $query->count();
    }
}
