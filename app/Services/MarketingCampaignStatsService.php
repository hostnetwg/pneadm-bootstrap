<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingCampaignStatsService
{
    public function __construct(
        private readonly CourseFunnelStatsService $funnelStats,
    ) {}

    /**
     * @return array{from: Carbon, to: Carbon, preset: string}|null
     */
    public function resolvePeriod(Request $request): ?array
    {
        $preset = (string) $request->query('period', '');

        if ($preset === 'today') {
            return $this->periodTuple(now()->copy()->startOfDay(), now()->copy()->endOfDay(), 'today');
        }

        if ($preset === 'yesterday') {
            $day = now()->copy()->subDay();

            return $this->periodTuple($day->copy()->startOfDay(), $day->copy()->endOfDay(), 'yesterday');
        }

        if ($preset === '7d') {
            return $this->periodTuple(
                now()->copy()->subDays(6)->startOfDay(),
                now()->copy()->endOfDay(),
                '7d'
            );
        }

        if ($preset === '30d') {
            return $this->periodTuple(
                now()->copy()->subDays(29)->startOfDay(),
                now()->copy()->endOfDay(),
                '30d'
            );
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = Carbon::parse($request->query('date_from'))->startOfDay();
            $to = Carbon::parse($request->query('date_to'))->endOfDay();

            if ($to->lt($from)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            return $this->periodTuple($from, $to, 'custom');
        }

        return null;
    }

    public function isLifetimeMode(?array $period): bool
    {
        return $period === null;
    }

    /**
     * @param  Builder<\App\Models\MarketingCampaign>  $query
     * @return Builder<\App\Models\MarketingCampaign>
     */
    public function applyStatsToQuery(Builder $query, ?array $period): Builder
    {
        if ($this->isLifetimeMode($period)) {
            return $query
                ->withCount('formOrders')
                ->withSum('statsDaily as link_entries_total', 'link_entries');
        }

        $from = $period['from'];
        $to = $period['to'];

        return $query
            ->select('marketing_campaigns.*')
            ->selectSub($this->linkEntriesSubquery($from, $to), 'link_entries_total')
            ->selectSub($this->ordersCountSubquery($from, $to), 'form_orders_count');
    }

    /**
     * @param  Builder<\App\Models\MarketingCampaign>  $query
     * @return Builder<\App\Models\MarketingCampaign>
     */
    public function applyActivityFilter(Builder $query, ?array $period, string $activityMetric, bool $onlyWithActivity): Builder
    {
        if (! $onlyWithActivity || $this->isLifetimeMode($period)) {
            return $query;
        }

        $from = $period['from'];
        $to = $period['to'];

        return $query->where(function (Builder $builder) use ($activityMetric, $from, $to) {
            if ($activityMetric === 'orders') {
                $builder->whereIn('marketing_campaigns.campaign_code', $this->campaignCodesWithOrdersSubquery($from, $to));
            } elseif ($activityMetric === 'entries') {
                $builder->whereIn('marketing_campaigns.campaign_code', $this->campaignCodesWithEntriesSubquery($from, $to));
            } else {
                $builder->whereIn('marketing_campaigns.campaign_code', $this->campaignCodesWithEntriesSubquery($from, $to))
                    ->orWhereIn('marketing_campaigns.campaign_code', $this->campaignCodesWithOrdersSubquery($from, $to));
            }
        });
    }

    /**
     * @param  Builder<\App\Models\MarketingCampaign>  $query
     */
    public function applySort(Builder $query, string $sortBy, string $sortOrder, ?array $period): Builder
    {
        if ($sortBy === 'source_type') {
            $query->join('marketing_source_types', 'marketing_campaigns.source_type_id', '=', 'marketing_source_types.id')
                ->orderBy('marketing_source_types.name', $sortOrder);

            if ($this->isLifetimeMode($period)) {
                $query->select('marketing_campaigns.*');
            }

            return $query;
        }

        if ($sortBy === 'orders_count') {
            return $query->orderBy('form_orders_count', $sortOrder);
        }

        if ($sortBy === 'link_entries_count') {
            if ($this->isLifetimeMode($period)) {
                return $query
                    ->orderByRaw(
                        '(SELECT COALESCE(SUM(link_entries), 0) FROM marketing_campaign_stats_daily WHERE campaign_code = marketing_campaigns.campaign_code) '.$sortOrder
                    )
                    ->orderBy('marketing_campaigns.created_at', 'desc');
            }

            return $query
                ->orderBy('link_entries_total', $sortOrder)
                ->orderBy('marketing_campaigns.created_at', 'desc');
        }

        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * @param  Builder<\App\Models\MarketingCampaign>  $query
     * @return array{link_entries: int, orders: int}
     */
    public function periodTotalsForQuery(Builder $query, array $period): array
    {
        $campaignCodes = (clone $query)
            ->toBase()
            ->select('marketing_campaigns.campaign_code')
            ->pluck('campaign_code')
            ->filter()
            ->values()
            ->all();

        if ($campaignCodes === []) {
            return ['link_entries' => 0, 'orders' => 0];
        }

        $linkEntries = (int) DB::table('marketing_campaign_stats_daily')
            ->whereIn('campaign_code', $campaignCodes)
            ->whereBetween('stat_date', [$period['from']->toDateString(), $period['to']->toDateString()])
            ->sum('link_entries');

        $operationalSubmitted = $this->funnelStats->operationalSubmittedOrderSql('fo.invoice_number', 'fo.status_completed');

        $orders = (int) DB::table('form_orders as fo')
            ->whereNull('fo.deleted_at')
            ->whereIn('fo.fb_source', $campaignCodes)
            ->whereBetween('fo.order_date', [$period['from'], $period['to']])
            ->whereRaw($operationalSubmitted)
            ->count();

        return [
            'link_entries' => $linkEntries,
            'orders' => $orders,
        ];
    }

    public function defaultSortForMetric(string $activityMetric): string
    {
        return $activityMetric === 'orders' ? 'orders_count' : 'link_entries_count';
    }

    public function linkEntriesSubquery(Carbon $from, Carbon $to): QueryBuilder
    {
        return DB::table('marketing_campaign_stats_daily as mcs')
            ->selectRaw('COALESCE(SUM(mcs.link_entries), 0)')
            ->whereColumn('mcs.campaign_code', 'marketing_campaigns.campaign_code')
            ->whereBetween('mcs.stat_date', [$from->toDateString(), $to->toDateString()]);
    }

    public function ordersCountSubquery(Carbon $from, Carbon $to): QueryBuilder
    {
        $operationalSubmitted = $this->funnelStats->operationalSubmittedOrderSql('fo.invoice_number', 'fo.status_completed');

        return DB::table('form_orders as fo')
            ->selectRaw('COUNT(*)')
            ->whereColumn('fo.fb_source', 'marketing_campaigns.campaign_code')
            ->whereNull('fo.deleted_at')
            ->whereBetween('fo.order_date', [$from, $to])
            ->whereRaw($operationalSubmitted);
    }

    private function campaignCodesWithEntriesSubquery(Carbon $from, Carbon $to): \Closure
    {
        return function (QueryBuilder $sub) use ($from, $to) {
            $sub->select('campaign_code')
                ->from('marketing_campaign_stats_daily')
                ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
                ->groupBy('campaign_code')
                ->havingRaw('SUM(link_entries) > 0');
        };
    }

    private function campaignCodesWithOrdersSubquery(Carbon $from, Carbon $to): \Closure
    {
        $operationalSubmitted = $this->funnelStats->operationalSubmittedOrderSql('fo.invoice_number', 'fo.status_completed');

        return function (QueryBuilder $sub) use ($from, $to, $operationalSubmitted) {
            $sub->select('fo.fb_source')
                ->from('form_orders as fo')
                ->whereNull('fo.deleted_at')
                ->whereBetween('fo.order_date', [$from, $to])
                ->whereRaw($operationalSubmitted)
                ->groupBy('fo.fb_source');
        };
    }

    /**
     * @return array{from: Carbon, to: Carbon, preset: string}
     */
    private function periodTuple(Carbon $from, Carbon $to, string $preset): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
        ];
    }
}
