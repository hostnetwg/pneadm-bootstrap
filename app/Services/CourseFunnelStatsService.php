<?php

namespace App\Services;

use App\Models\CoursePageStatsDaily;
use App\Models\MarketingCampaign;
use App\Support\UtcStorageDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourseFunnelStatsService
{
    public function defaultStatsDays(): int
    {
        return max(1, (int) config('marketing.funnel_stats_days', 30));
    }

    /**
     * @param  array<int>  $courseIds
     * @return array<int, array{
     *     views_course_show: int,
     *     views_order_form: int,
     *     orders_submitted: int,
     *     orders_invoiced: int,
     *     cr_show_to_order: float|null,
     *     cr_form_to_order: float|null,
     *     cr_show_to_invoiced: float|null
     * }>
     */
    public function statsForCourses(array $courseIds, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($courseIds === []) {
            return [];
        }

        $to = ($to ?? now())->copy()->endOfDay();
        $from = ($from ?? now()->subDays($this->defaultStatsDays() - 1))->copy()->startOfDay();

        $viewStats = CoursePageStatsDaily::query()
            ->whereIn('course_id', $courseIds)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('course_id, SUM(views_course_show) as views_course_show, SUM(views_order_form) as views_order_form')
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        $orderStats = $this->orderCountsForCourses($courseIds, $from, $to);

        $result = [];
        foreach ($courseIds as $courseId) {
            $viewsShow = (int) ($viewStats->get($courseId)?->views_course_show ?? 0);
            $viewsForm = (int) ($viewStats->get($courseId)?->views_order_form ?? 0);
            $submitted = (int) ($orderStats[$courseId]['submitted'] ?? 0);
            $invoiced = (int) ($orderStats[$courseId]['invoiced'] ?? 0);

            $result[$courseId] = [
                'views_course_show' => $viewsShow,
                'views_order_form' => $viewsForm,
                'orders_submitted' => $submitted,
                'orders_invoiced' => $invoiced,
                // Zachowane dla kompatybilności widoków / starych odwołań
                'orders_paid' => $invoiced,
                'cr_show_to_order' => $this->conversionRate($submitted, $viewsShow),
                'cr_form_to_order' => $this->conversionRate($submitted, $viewsForm),
                'cr_show_to_invoiced' => $this->conversionRate($invoiced, $viewsShow),
            ];
        }

        return $result;
    }

    /**
     * Liczba kampanii marketingowych przypisanych do szkolenia (cała historia, bez soft delete).
     *
     * @param  array<int>  $courseIds
     * @return array<int, int>
     */
    public function campaignCountsForCourses(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        $counts = MarketingCampaign::query()
            ->whereIn('course_id', $courseIds)
            ->selectRaw('course_id, COUNT(*) as campaigns_count')
            ->groupBy('course_id')
            ->pluck('campaigns_count', 'course_id');

        $result = [];
        foreach ($courseIds as $courseId) {
            $result[$courseId] = (int) ($counts[$courseId] ?? 0);
        }

        return $result;
    }

    /**
     * Zamówienia w liczniku 🛒 — bez soft delete; bez zamkniętych ręcznie bez faktury
     * (status_completed = 1 i brak invoice_number), spójnie z badge „niewprowadzone” w panelu.
     *
     * @return array<int, array{submitted: int, invoiced: int}>
     */
    public function orderCountsForCourses(array $courseIds, Carbon $from, Carbon $to): array
    {
        $invoicePresent = $this->invoicePresentSql('fo.invoice_number');
        $operationalSubmitted = $this->operationalSubmittedOrderSql('fo.invoice_number', 'fo.status_completed');

        [$fromUtc, $toUtc] = UtcStorageDate::utcRangeForLocalDays($from, $to);

        $rows = DB::table('form_orders as fo')
            ->join('courses as c', function ($join) {
                $join->on('fo.product_id', '=', 'c.id')
                    ->orOn(function ($q) {
                        $q->whereNotNull('c.id_old')
                            ->where('c.id_old', '!=', '')
                            ->whereColumn('fo.publigo_product_id', 'c.id_old');
                    });
            })
            ->whereNull('fo.deleted_at')
            ->whereIn('c.id', $courseIds)
            ->whereBetween('fo.order_date', [$fromUtc, $toUtc])
            ->groupBy('c.id')
            ->selectRaw('c.id as course_id')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$operationalSubmitted} THEN fo.id END) as submitted")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$invoicePresent} THEN fo.id END) as invoiced")
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->course_id] = [
                'submitted' => (int) $row->submitted,
                'invoiced' => (int) $row->invoiced,
            ];
        }

        return $map;
    }

    /**
     * @return Collection<int, object>
     */
    public function funnelByCampaign(Carbon $from, Carbon $to, ?int $sourceTypeId = null): Collection
    {
        $invoicePresent = $this->invoicePresentSql('fo.invoice_number');
        $operationalSubmitted = $this->operationalSubmittedOrderSql('fo.invoice_number', 'fo.status_completed');

        [$fromUtc, $toUtc] = UtcStorageDate::utcRangeForLocalDays($from, $to);

        $entriesSubquery = DB::table('marketing_campaign_stats_daily')
            ->selectRaw('campaign_code, SUM(link_entries) as link_entries')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('campaign_code');

        $query = DB::table('marketing_campaigns as mc')
            ->leftJoin('marketing_source_types as mst', 'mst.id', '=', 'mc.source_type_id')
            ->leftJoinSub($entriesSubquery, 'mcs_agg', function ($join) {
                $join->on('mcs_agg.campaign_code', '=', 'mc.campaign_code');
            })
            ->leftJoin('form_orders as fo', function ($join) use ($fromUtc, $toUtc) {
                $join->on('fo.fb_source', '=', 'mc.campaign_code')
                    ->whereNull('fo.deleted_at')
                    ->whereBetween('fo.order_date', [$fromUtc, $toUtc]);
            })
            ->whereNull('mc.deleted_at')
            ->groupBy('mc.id', 'mc.campaign_code', 'mc.name', 'mst.name', 'mst.color', 'mc.is_active')
            ->selectRaw('mc.id, mc.campaign_code, mc.name, mst.name as source_type_name, mst.color as source_type_color, mc.is_active')
            ->selectRaw('COALESCE(MAX(mcs_agg.link_entries), 0) as link_entries')
            ->selectRaw("SUM(CASE WHEN fo.id IS NOT NULL AND {$operationalSubmitted} THEN 1 ELSE 0 END) as orders_submitted")
            ->selectRaw("SUM(CASE WHEN {$invoicePresent} THEN 1 ELSE 0 END) as orders_invoiced")
            ->selectRaw("SUM(CASE WHEN {$invoicePresent} THEN 1 ELSE 0 END) as orders_paid")
            ->orderByDesc('orders_submitted');

        if ($sourceTypeId) {
            $query->where('mc.source_type_id', $sourceTypeId);
        }

        return $query->get();
    }

    public function conversionRate(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

    public function formatCr(?float $rate): string
    {
        return $rate === null ? '—' : number_format($rate, 1, ',', ' ').' %';
    }

    /**
     * Warunek SQL: pole invoice_number jest uzupełnione (jak FormOrder::scopeWithInvoice).
     */
    public function invoicePresentSql(string $column = 'invoice_number'): string
    {
        return "({$column} IS NOT NULL AND {$column} != '' AND {$column} != '0')";
    }

    /**
     * Zamówienie operacyjnie „złożone” w lejku (🛒): wyklucza anulowane (cancelled_at).
     */
    public function operationalSubmittedOrderSql(string $invoiceColumn = 'invoice_number', string $statusColumn = 'status_completed'): string
    {
        unset($invoiceColumn, $statusColumn);

        return '(fo.cancelled_at IS NULL)';
    }
}
