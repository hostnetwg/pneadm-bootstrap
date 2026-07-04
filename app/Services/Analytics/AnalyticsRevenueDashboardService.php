<?php

namespace App\Services\Analytics;

use App\Models\Analytics\AnalyticsDailyCampaignRevenueStat;
use App\Models\Analytics\AnalyticsDailyCourseRevenueStat;
use App\Models\Course;
use App\Models\MarketingCampaign;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Dashboard rozliczeń (Etap R2). READ-ONLY.
 *
 * Czyta WYŁĄCZNIE dzienne agregaty R1:
 * - analytics_daily_course_revenue_stats (per kurs),
 * - analytics_daily_campaign_revenue_stats (per kampania).
 *
 * Metryki w jednym wierszu agregatu mogą pochodzić z różnych dat eventów
 * (zamówienie / płatność online / faktura odroczona) — dashboard to jasno etykietuje.
 */
class AnalyticsRevenueDashboardService
{
    public function timezone(): string
    {
        return (string) config('analytics.revenue_dashboard.timezone', config('analytics.revenue.timezone', 'Europe/Warsaw'));
    }

    public function defaultDays(): int
    {
        return max(1, (int) config('analytics.revenue_dashboard.default_days', 14));
    }

    public function maxDays(): int
    {
        return max(1, (int) config('analytics.revenue_dashboard.max_days', 366));
    }

    public function aggregationLagDays(): int
    {
        return max(0, (int) config('analytics.revenue.aggregation_lag_days', 1));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array
    {
        $filters = $this->resolveFilters($input);

        $courseRows = $this->filteredCourseQuery($filters)->get();
        $campaignRows = $this->filteredCampaignQuery($filters)->get();

        $summary = $this->buildSummary($courseRows);
        $comparison = $this->buildPeriodComparison($filters, $summary);

        return [
            'filters' => $filters,
            'summary' => $summary,
            'courses' => $this->buildCourseTable($courseRows),
            'campaigns' => $this->buildCampaignTable($campaignRows),
            'trend' => $this->buildDailyTrend($filters, $courseRows, $campaignRows),
            'course_schedule' => $this->buildCourseScheduleMarkers($filters),
            'comparison' => $comparison,
            'meta' => [
                'lag_days' => $this->aggregationLagDays(),
                'default_days' => $this->defaultDays(),
                'max_days' => $this->maxDays(),
                'timezone' => $this->timezone(),
                'source_tables' => [
                    'analytics_daily_course_revenue_stats',
                    'analytics_daily_campaign_revenue_stats',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function resolveFilters(array $input): array
    {
        $timezone = $this->timezone();

        $defaultTo = Carbon::now($timezone)->startOfDay()->subDays($this->aggregationLagDays());
        $defaultFrom = $defaultTo->copy()->subDays($this->defaultDays() - 1);

        $dateFrom = filled($input['date_from'] ?? null)
            ? Carbon::parse((string) $input['date_from'], $timezone)->startOfDay()
            : $defaultFrom;

        $dateTo = filled($input['date_to'] ?? null)
            ? Carbon::parse((string) $input['date_to'], $timezone)->startOfDay()
            : $defaultTo;

        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $maxDays = $this->maxDays();
        if ($dateFrom->diffInDays($dateTo) + 1 > $maxDays) {
            $dateFrom = $dateTo->copy()->subDays($maxDays - 1);
        }

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'course_id' => filled($input['course_id'] ?? null) ? (int) $input['course_id'] : null,
            'campaign_code' => filled($input['campaign_code'] ?? null) ? trim((string) $input['campaign_code']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredCourseQuery(array $filters): Builder
    {
        $query = AnalyticsDailyCourseRevenueStat::query()
            ->whereDate('stat_date', '>=', $filters['date_from'])
            ->whereDate('stat_date', '<=', $filters['date_to']);

        if ($filters['course_id'] !== null) {
            $query->where('course_id', $filters['course_id']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredCampaignQuery(array $filters): Builder
    {
        $query = AnalyticsDailyCampaignRevenueStat::query()
            ->whereDate('stat_date', '>=', $filters['date_from'])
            ->whereDate('stat_date', '<=', $filters['date_to']);

        if ($filters['campaign_code'] !== null) {
            $query->where('campaign_code', $filters['campaign_code']);
        }

        return $query;
    }

    /**
     * @param  Collection<int, AnalyticsDailyCourseRevenueStat>  $courseRows
     * @return array<string, int|float>
     */
    private function buildSummary(Collection $courseRows): array
    {
        return [
            'orders_created' => (int) $courseRows->sum('orders_created'),
            'ordered_revenue_gross' => round((float) $courseRows->sum('ordered_revenue_gross'), 2),
            'online_paid_orders' => (int) $courseRows->sum('online_paid_orders'),
            'online_paid_revenue_gross' => round((float) $courseRows->sum('online_paid_revenue_gross'), 2),
            'deferred_invoiced_orders' => (int) $courseRows->sum('deferred_invoiced_orders'),
            'deferred_invoiced_revenue_gross' => round((float) $courseRows->sum('deferred_invoiced_revenue_gross'), 2),
            'online_invoiced_marker_orders' => (int) $courseRows->sum('online_invoiced_marker_orders'),
            'settled_orders_total' => (int) $courseRows->sum('settled_orders_total'),
            'settled_revenue_gross' => round((float) $courseRows->sum('settled_revenue_gross'), 2),
            'orders_created_without_campaign' => (int) $courseRows->sum('orders_created_without_campaign'),
            'online_paid_without_campaign' => (int) $courseRows->sum('online_paid_without_campaign'),
            'deferred_invoiced_without_campaign' => (int) $courseRows->sum('deferred_invoiced_without_campaign'),
        ];
    }

    /**
     * @param  Collection<int, AnalyticsDailyCourseRevenueStat>  $courseRows
     * @return list<array<string, mixed>>
     */
    private function buildCourseTable(Collection $courseRows): array
    {
        return $courseRows
            ->groupBy('course_id')
            ->map(function (Collection $rows, int|string $courseId): array {
                return $this->aggregateRevenueRow([
                    'course_id' => (int) $courseId,
                    'course_title_snapshot' => $this->latestTitle($rows),
                ], $rows, includeDiagnostics: true);
            })
            ->sortByDesc('settled_revenue_gross')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AnalyticsDailyCampaignRevenueStat>  $campaignRows
     * @return list<array<string, mixed>>
     */
    private function buildCampaignTable(Collection $campaignRows): array
    {
        $rows = $campaignRows
            ->groupBy('campaign_code')
            ->map(function (Collection $group, int|string $campaignCode): array {
                $campaignId = $group->pluck('campaign_id')->filter()->first();

                return $this->aggregateRevenueRow([
                    'campaign_code' => (string) $campaignCode,
                    'campaign_id' => $campaignId !== null ? (int) $campaignId : null,
                    'campaign_name' => null,
                ], $group, includeDiagnostics: false);
            })
            ->sortByDesc('settled_revenue_gross')
            ->values()
            ->all();

        return $this->enrichWithCampaignMetadata($rows);
    }

    /**
     * Dzienny trend (jeden wiersz na każdy stat_date w zakresie, z wypełnieniem brakujących dni zerami).
     *
     * Źródło zależy od aktywnego filtra:
     * - gdy ustawiono `campaign_code` → grupujemy tabelę kampanii po stat_date,
     * - w przeciwnym razie → grupujemy tabelę kursów po stat_date (z filtrem course_id).
     *
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, AnalyticsDailyCourseRevenueStat>  $courseRows
     * @param  Collection<int, AnalyticsDailyCampaignRevenueStat>  $campaignRows
     * @return list<array<string, mixed>>
     */
    private function buildDailyTrend(array $filters, Collection $courseRows, Collection $campaignRows): array
    {
        $source = $filters['campaign_code'] !== null ? $campaignRows : $courseRows;
        $includeDiagnostics = $filters['campaign_code'] === null;

        $byDate = $source->groupBy(fn ($row): string => Carbon::parse($row->stat_date)->toDateString());

        $rows = [];
        $cursor = Carbon::parse($filters['date_from']);
        $end = Carbon::parse($filters['date_to']);

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            /** @var Collection<int, mixed> $dayRows */
            $dayRows = $byDate->get($key, collect());

            $row = [
                'stat_date' => $key,
                'orders_created' => (int) $dayRows->sum('orders_created'),
                'ordered_revenue_gross' => round((float) $dayRows->sum('ordered_revenue_gross'), 2),
                'online_paid_orders' => (int) $dayRows->sum('online_paid_orders'),
                'online_paid_revenue_gross' => round((float) $dayRows->sum('online_paid_revenue_gross'), 2),
                'deferred_invoiced_orders' => (int) $dayRows->sum('deferred_invoiced_orders'),
                'deferred_invoiced_revenue_gross' => round((float) $dayRows->sum('deferred_invoiced_revenue_gross'), 2),
                'online_invoiced_marker_orders' => (int) $dayRows->sum('online_invoiced_marker_orders'),
                'settled_orders_total' => (int) $dayRows->sum('settled_orders_total'),
                'settled_revenue_gross' => round((float) $dayRows->sum('settled_revenue_gross'), 2),
            ];

            if ($includeDiagnostics) {
                $row['orders_created_without_campaign'] = (int) $dayRows->sum('orders_created_without_campaign');
                $row['online_paid_without_campaign'] = (int) $dayRows->sum('online_paid_without_campaign');
                $row['deferred_invoiced_without_campaign'] = (int) $dayRows->sum('deferred_invoiced_without_campaign');
            }

            $rows[] = $row;
            $cursor->addDay();
        }

        return $rows;
    }

    /**
     * Terminy szkoleń (start) w zakresie wykresu — odczyt z tabeli courses (nie z agregatów R1).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{course_id: int, title: string, start_date: string, start_time: string}>
     */
    private function buildCourseScheduleMarkers(array $filters): array
    {
        $timezone = $this->timezone();
        $from = Carbon::parse((string) $filters['date_from'], $timezone)->startOfDay();
        $to = Carbon::parse((string) $filters['date_to'], $timezone)->endOfDay();

        try {
            return Course::query()
                ->whereNotNull('start_date')
                ->whereBetween('start_date', [$from, $to])
                ->orderBy('start_date')
                ->orderBy('id')
                ->get(['id', 'title', 'start_date'])
                ->map(function (Course $course) use ($timezone): array {
                    $start = Carbon::parse($course->start_date)->timezone($timezone);

                    return [
                        'course_id' => (int) $course->id,
                        'title' => (string) $course->title,
                        'start_date' => $start->toDateString(),
                        'start_time' => $start->format('H:i'),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  Collection<int, mixed>  $rows
     * @return array<string, mixed>
     */
    private function aggregateRevenueRow(array $identity, Collection $rows, bool $includeDiagnostics): array
    {
        $row = array_merge($identity, [
            'orders_created' => (int) $rows->sum('orders_created'),
            'ordered_revenue_gross' => round((float) $rows->sum('ordered_revenue_gross'), 2),
            'online_paid_orders' => (int) $rows->sum('online_paid_orders'),
            'online_paid_revenue_gross' => round((float) $rows->sum('online_paid_revenue_gross'), 2),
            'deferred_invoiced_orders' => (int) $rows->sum('deferred_invoiced_orders'),
            'deferred_invoiced_revenue_gross' => round((float) $rows->sum('deferred_invoiced_revenue_gross'), 2),
            'online_invoiced_marker_orders' => (int) $rows->sum('online_invoiced_marker_orders'),
            'settled_orders_total' => (int) $rows->sum('settled_orders_total'),
            'settled_revenue_gross' => round((float) $rows->sum('settled_revenue_gross'), 2),
        ]);

        if ($includeDiagnostics) {
            $row['orders_created_without_campaign'] = (int) $rows->sum('orders_created_without_campaign');
            $row['online_paid_without_campaign'] = (int) $rows->sum('online_paid_without_campaign');
            $row['deferred_invoiced_without_campaign'] = (int) $rows->sum('deferred_invoiced_without_campaign');
        }

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function enrichWithCampaignMetadata(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        try {
            $codes = collect($rows)
                ->pluck('campaign_code')
                ->filter(fn ($code): bool => is_string($code) && $code !== '')
                ->unique()
                ->values()
                ->all();

            if ($codes === []) {
                return $rows;
            }

            $campaigns = MarketingCampaign::query()
                ->whereIn('campaign_code', $codes)
                ->get(['id', 'campaign_code', 'name'])
                ->keyBy('campaign_code');

            return array_map(function (array $row) use ($campaigns): array {
                $code = $row['campaign_code'] ?? null;
                $campaign = is_string($code) ? $campaigns->get($code) : null;

                if ($campaign === null) {
                    return $row;
                }

                $row['campaign_id'] = (int) $campaign->id;
                $row['campaign_name'] = $campaign->name;

                return $row;
            }, $rows);
        } catch (Throwable) {
            return $rows;
        }
    }

    /**
     * @param  Collection<int, AnalyticsDailyCourseRevenueStat>  $rows
     */
    private function latestTitle(Collection $rows): ?string
    {
        return $rows
            ->sortByDesc('stat_date')
            ->pluck('course_title_snapshot')
            ->first(fn ($title): bool => filled($title));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, int|float>  $summary
     * @return array<string, mixed>
     */
    private function buildPeriodComparison(array $filters, array $summary): array
    {
        $periodComparison = app(AnalyticsPeriodComparison::class);
        $previousPeriod = $periodComparison->previousPeriodDates(
            (string) $filters['date_from'],
            (string) $filters['date_to'],
        );

        $previousFilters = array_merge($filters, [
            'date_from' => $previousPeriod['date_from'],
            'date_to' => $previousPeriod['date_to'],
        ]);

        $previousSummary = $this->buildSummary($this->filteredCourseQuery($previousFilters)->get());

        return $periodComparison->build(
            (string) $filters['date_from'],
            (string) $filters['date_to'],
            $summary,
            $previousSummary,
            [
                'orders_created',
                'ordered_revenue_gross',
                'online_paid_orders',
                'online_paid_revenue_gross',
                'deferred_invoiced_orders',
                'deferred_invoiced_revenue_gross',
                'settled_orders_total',
                'settled_revenue_gross',
                'online_invoiced_marker_orders',
            ],
        );
    }
}
