<?php

namespace App\Services\Analytics;

use App\Models\Analytics\AnalyticsDailyCampaignAbandonmentStat;
use App\Models\Analytics\AnalyticsDailyFormAbandonmentStat;
use App\Models\MarketingCampaign;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Dashboard porzuceń formularza (Etap B4). READ-ONLY.
 *
 * Czyta WYŁĄCZNIE dzienne agregaty B3:
 * - analytics_daily_form_abandonment_stats (per kurs),
 * - analytics_daily_campaign_abandonment_stats (per kampania).
 *
 * NIE skanuje analytics_events. Brak PII, brak raw metadata.
 */
class AnalyticsFormAbandonmentDashboardService
{
    /**
     * Kubełki terminalne (rozłączne, sumują się do sessions_total) — kolejność = kolejność lejka.
     *
     * @var array<string, string>
     */
    private const BUCKETS = [
        'viewed_not_started' => 'Weszli, ale nie zaczęli formularza',
        'started_not_submit_clicked' => 'Zaczęli, ale nie kliknęli submit',
        'submit_clicked_not_attempted' => 'Kliknęli submit, ale backend nie odnotował próby',
        'submit_attempted_not_created' => 'Była próba submitu, ale nie utworzono zamówienia',
        'converted' => 'Zakończone zamówieniem',
    ];

    public function timezone(): string
    {
        return (string) config('analytics.form_abandonment_dashboard.timezone', 'Europe/Warsaw');
    }

    public function defaultDays(): int
    {
        return max(1, (int) config('analytics.form_abandonment_dashboard.default_days', 14));
    }

    public function maxDays(): int
    {
        return max(1, (int) config('analytics.form_abandonment_dashboard.max_days', 366));
    }

    public function aggregationLagDays(): int
    {
        return max(0, (int) config('analytics.abandonment.aggregation_lag_days', 2));
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
        $buckets = $this->buildBuckets($courseRows, (int) $summary['sessions_total']);

        return [
            'filters' => $filters,
            'summary' => $summary,
            'buckets' => $buckets,
            'courses' => $this->buildCourseTable($courseRows),
            'campaigns' => $this->buildCampaignTable($campaignRows),
            'meta' => [
                'lag_days' => $this->aggregationLagDays(),
                'default_days' => $this->defaultDays(),
                'max_days' => $this->maxDays(),
                'timezone' => $this->timezone(),
                'bucket_labels' => self::BUCKETS,
                'source_tables' => [
                    'analytics_daily_form_abandonment_stats',
                    'analytics_daily_campaign_abandonment_stats',
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

        // Domyślnie kończymy na ostatnim "dojrzałym" dniu (today - lag), bo agregacja B3 ma lag.
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

        // Ochrona przed zbyt dużym zakresem — przytnij date_from do max_days.
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
        $query = AnalyticsDailyFormAbandonmentStat::query()
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
        $query = AnalyticsDailyCampaignAbandonmentStat::query()
            ->whereDate('stat_date', '>=', $filters['date_from'])
            ->whereDate('stat_date', '<=', $filters['date_to']);

        if ($filters['campaign_code'] !== null) {
            $query->where('campaign_code', $filters['campaign_code']);
        }

        return $query;
    }

    /**
     * @param  Collection<int, AnalyticsDailyFormAbandonmentStat>  $courseRows
     * @return array<string, int|float|null>
     */
    private function buildSummary(Collection $courseRows): array
    {
        $sessionsTotal = (int) $courseRows->sum('sessions_total');
        $converted = (int) $courseRows->sum('converted');

        return [
            'sessions_total' => $sessionsTotal,
            'reached_started' => (int) $courseRows->sum('reached_started'),
            'reached_submit_clicked' => (int) $courseRows->sum('reached_submit_clicked'),
            'reached_submit_attempted' => (int) $courseRows->sum('reached_submit_attempted'),
            'reached_created' => (int) $courseRows->sum('reached_created'),
            'abandoned_total' => max(0, $sessionsTotal - $converted),
            'conversion_rate' => $this->rate($converted, $sessionsTotal),
        ];
    }

    /**
     * @param  Collection<int, AnalyticsDailyFormAbandonmentStat>  $courseRows
     * @return list<array<string, mixed>>
     */
    private function buildBuckets(Collection $courseRows, int $sessionsTotal): array
    {
        $buckets = [];

        foreach (self::BUCKETS as $key => $label) {
            $count = (int) $courseRows->sum($key);

            $buckets[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'percent' => $this->rate($count, $sessionsTotal),
            ];
        }

        return $buckets;
    }

    /**
     * @param  Collection<int, AnalyticsDailyFormAbandonmentStat>  $courseRows
     * @return list<array<string, mixed>>
     */
    private function buildCourseTable(Collection $courseRows): array
    {
        return $courseRows
            ->groupBy('course_id')
            ->map(function (Collection $rows, int|string $courseId): array {
                $sessionsTotal = (int) $rows->sum('sessions_total');
                $converted = (int) $rows->sum('converted');

                return [
                    'course_id' => (int) $courseId,
                    'course_title_snapshot' => $this->latestTitle($rows),
                    'sessions_total' => $sessionsTotal,
                    'reached_started' => (int) $rows->sum('reached_started'),
                    'reached_submit_clicked' => (int) $rows->sum('reached_submit_clicked'),
                    'reached_submit_attempted' => (int) $rows->sum('reached_submit_attempted'),
                    'reached_created' => (int) $rows->sum('reached_created'),
                    'viewed_not_started' => (int) $rows->sum('viewed_not_started'),
                    'started_not_submit_clicked' => (int) $rows->sum('started_not_submit_clicked'),
                    'submit_clicked_not_attempted' => (int) $rows->sum('submit_clicked_not_attempted'),
                    'submit_attempted_not_created' => (int) $rows->sum('submit_attempted_not_created'),
                    'converted' => $converted,
                    'conversion_rate' => $this->rate($converted, $sessionsTotal),
                ];
            })
            ->sortByDesc('sessions_total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AnalyticsDailyCampaignAbandonmentStat>  $campaignRows
     * @return list<array<string, mixed>>
     */
    private function buildCampaignTable(Collection $campaignRows): array
    {
        $rows = $campaignRows
            ->groupBy('campaign_code')
            ->map(function (Collection $group, int|string $campaignCode): array {
                $sessionsTotal = (int) $group->sum('sessions_total');
                $converted = (int) $group->sum('converted');
                $campaignId = $group->pluck('campaign_id')->filter()->first();

                return [
                    'campaign_code' => (string) $campaignCode,
                    'campaign_id' => $campaignId !== null ? (int) $campaignId : null,
                    'campaign_name' => null,
                    'sessions_total' => $sessionsTotal,
                    'reached_started' => (int) $group->sum('reached_started'),
                    'reached_submit_clicked' => (int) $group->sum('reached_submit_clicked'),
                    'reached_submit_attempted' => (int) $group->sum('reached_submit_attempted'),
                    'reached_created' => (int) $group->sum('reached_created'),
                    'viewed_not_started' => (int) $group->sum('viewed_not_started'),
                    'started_not_submit_clicked' => (int) $group->sum('started_not_submit_clicked'),
                    'submit_clicked_not_attempted' => (int) $group->sum('submit_clicked_not_attempted'),
                    'submit_attempted_not_created' => (int) $group->sum('submit_attempted_not_created'),
                    'converted' => $converted,
                    'conversion_rate' => $this->rate($converted, $sessionsTotal),
                ];
            })
            ->sortByDesc('sessions_total')
            ->values()
            ->all();

        return $this->enrichWithCampaignMetadata($rows);
    }

    /**
     * Dokleja nazwę kampanii i ID (do linku) z tabeli marketing_campaigns (połączenie domyślne),
     * łącząc po unikalnym campaign_code. Fail-safe: błąd odczytu zostawia same kody.
     *
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
     * @param  Collection<int, AnalyticsDailyFormAbandonmentStat>  $rows
     */
    private function latestTitle(Collection $rows): ?string
    {
        return $rows
            ->sortByDesc('stat_date')
            ->pluck('course_title_snapshot')
            ->first(fn ($title): bool => filled($title));
    }

    private function rate(int|float $numerator, int|float $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(((float) $numerator / (float) $denominator) * 100, 2);
    }
}
