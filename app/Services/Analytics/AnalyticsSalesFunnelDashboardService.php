<?php

namespace App\Services\Analytics;

use App\Models\Analytics\AnalyticsDailyCampaignStat;
use App\Models\Analytics\AnalyticsDailyCourseStat;
use App\Models\MarketingCampaign;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class AnalyticsSalesFunnelDashboardService
{
    private const SORT_ORDERS = 'orders_created';

    private const SORT_FORM_VIEWS = 'order_form_views';

    private const SORT_CONVERSION = 'form_to_order_rate';

    private const SORT_VALIDATION = 'validation_failures';

    public function timezone(): string
    {
        return (string) config('analytics.sales_funnel_dashboard.timezone', 'Europe/Warsaw');
    }

    public function defaultDays(): int
    {
        return (int) config('analytics.sales_funnel_dashboard.default_days', 14);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array
    {
        $filters = $this->resolveFilters($input);
        $courseRows = $this->courseRows($filters);
        $campaignRows = $this->campaignRows($filters, (string) ($input['sort'] ?? self::SORT_ORDERS));

        $summary = $this->buildSummary($courseRows, $campaignRows);
        $funnel = $this->buildFunnel($summary);
        $rates = $this->buildRates($summary);
        $landingTargets = $this->buildLandingTargetComparison($filters, $courseRows);
        $alerts = $this->buildAlerts($campaignRows, $courseRows);
        $comparison = $this->buildPeriodComparison($filters, $courseRows, $campaignRows, $summary, $rates);

        return [
            'filters' => $filters,
            'summary' => $summary,
            'funnel' => $funnel,
            'rates' => $rates,
            'campaigns' => $campaignRows,
            'courses' => $this->buildCourseTable($courseRows),
            'landing_targets' => $landingTargets,
            'alerts' => $alerts,
            'comparison' => $comparison,
            'sort' => $this->resolveSort((string) ($input['sort'] ?? self::SORT_ORDERS)),
            'has_utm_filters' => false,
            'column_map' => $this->columnMap(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function resolveFilters(array $input): array
    {
        $timezone = $this->timezone();
        $today = Carbon::now($timezone)->startOfDay();
        $defaultFrom = $today->copy()->subDays($this->defaultDays() - 1);

        $dateFrom = filled($input['date_from'] ?? null)
            ? Carbon::parse((string) $input['date_from'], $timezone)->startOfDay()
            : $defaultFrom;

        $dateTo = filled($input['date_to'] ?? null)
            ? Carbon::parse((string) $input['date_to'], $timezone)->startOfDay()
            : $today;

        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'campaign_code' => filled($input['campaign_code'] ?? null)
                ? trim((string) $input['campaign_code'])
                : null,
            'course_id' => filled($input['course_id'] ?? null)
                ? (int) $input['course_id']
                : null,
            'landing_target' => filled($input['landing_target'] ?? null)
                ? trim((string) $input['landing_target'])
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredCourseQuery(array $filters): Builder
    {
        $query = AnalyticsDailyCourseStat::query()
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
        $query = AnalyticsDailyCampaignStat::query()
            ->whereDate('stat_date', '>=', $filters['date_from'])
            ->whereDate('stat_date', '<=', $filters['date_to']);

        if ($filters['campaign_code'] !== null) {
            $query->where('campaign_code', $filters['campaign_code']);
        }

        if ($filters['landing_target'] !== null) {
            $query->where('landing_target', $filters['landing_target']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AnalyticsDailyCourseStat>
     */
    private function courseRows(array $filters): Collection
    {
        return $this->filteredCourseQuery($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function campaignRows(array $filters, string $sort): array
    {
        $grouped = $this->filteredCampaignQuery($filters)
            ->get()
            ->groupBy('campaign_code')
            ->map(function (Collection $rows, string $campaignCode): array {
                $first = $rows->first();

                $linkEntries = (int) $rows->sum('link_entries');
                $descriptionViews = (int) $rows->sum('course_description_views');
                $formViews = (int) $rows->sum('order_form_views');
                $submitAttempts = (int) $rows->sum('submit_attempts');
                $validationFailures = (int) $rows->sum('validation_failures');
                $ordersCreated = (int) $rows->sum('orders_created');
                $revenue = (float) $rows->sum('revenue_snapshot');

                return [
                    'campaign_code' => $campaignCode,
                    'campaign_channel' => $first?->campaign_channel,
                    'landing_target' => $first?->landing_target,
                    'campaign_id' => null,
                    'campaign_name' => null,
                    'campaign_course_id' => null,
                    'campaign_course_title' => null,
                    'link_entries' => $linkEntries,
                    'description_views' => $descriptionViews,
                    'form_views' => $formViews,
                    'form_submits' => $submitAttempts,
                    'validation_errors' => $validationFailures,
                    'orders_created' => $ordersCreated,
                    'revenue_gross' => round($revenue, 2),
                    'form_to_order_rate' => $this->rate($ordersCreated, $formViews),
                    'submit_to_order_rate' => $this->rate($ordersCreated, $submitAttempts),
                ];
            })
            ->values()
            ->all();

        $grouped = $this->enrichWithCampaignMetadata($grouped);

        return $this->sortCampaignRows($grouped, $this->resolveSort($sort));
    }

    /**
     * Dokleja do wierszy kampanii dane z tabeli marketing_campaigns (połączenie domyślne):
     * ID kampanii (link do karty), nazwę kampanii oraz tytuł powiązanego szkolenia.
     *
     * Łączymy po unikalnym `campaign_code`. Tylko nieusunięte kampanie dostają link/nazwę
     * (route model binding i tak ukrywa soft-deleted). Fail-safe: każdy błąd odczytu zostawia
     * wiersze bez wzbogacenia — dashboard analityki nie może się wywalić przez tabelę kampanii.
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
                ->with('course:id,title')
                ->whereIn('campaign_code', $codes)
                ->get(['id', 'campaign_code', 'name', 'course_id'])
                ->keyBy('campaign_code');

            return array_map(function (array $row) use ($campaigns): array {
                $code = $row['campaign_code'] ?? null;
                $campaign = is_string($code) ? $campaigns->get($code) : null;

                if ($campaign === null) {
                    return $row;
                }

                $row['campaign_id'] = (int) $campaign->id;
                $row['campaign_name'] = $campaign->name;
                $row['campaign_course_id'] = $campaign->course_id !== null ? (int) $campaign->course_id : null;
                $row['campaign_course_title'] = $campaign->course?->title;

                return $row;
            }, $rows);
        } catch (Throwable) {
            // Wzbogacenie jest opcjonalne — bez metadanych pokazujemy sam kod kampanii.
            return $rows;
        }
    }

    /**
     * @param  Collection<int, AnalyticsDailyCourseStat>  $courseRows
     * @param  list<array<string, mixed>>  $campaignRows
     * @return array<string, int|float>
     */
    private function buildSummary(Collection $courseRows, array $campaignRows): array
    {
        $campaignCollection = collect($campaignRows);

        return [
            'short_link_visits' => (int) $campaignCollection->sum('link_entries'),
            'description_views' => (int) $courseRows->sum('views_course_description'),
            'form_views' => (int) $courseRows->sum('views_order_form'),
            'form_submits' => (int) $courseRows->sum('submit_attempts'),
            'validation_errors' => (int) $courseRows->sum('validation_failures'),
            'orders_created' => (int) $courseRows->sum('orders_created'),
            'revenue_gross' => round((float) $courseRows->sum('revenue_snapshot'), 2),
        ];
    }

    /**
     * @param  array<string, int|float>  $summary
     * @return list<array<string, int|string|null>>
     */
    private function buildFunnel(array $summary): array
    {
        return [
            ['label' => 'Wejścia w opis', 'key' => 'description_views', 'value' => (int) $summary['description_views']],
            ['label' => 'Wejścia w formularz', 'key' => 'form_views', 'value' => (int) $summary['form_views']],
            ['label' => 'Próby submitu', 'key' => 'form_submits', 'value' => (int) $summary['form_submits']],
            ['label' => 'Błędy walidacji', 'key' => 'validation_errors', 'value' => (int) $summary['validation_errors']],
            ['label' => 'Zamówienia', 'key' => 'orders_created', 'value' => (int) $summary['orders_created']],
        ];
    }

    /**
     * @param  array<string, int|float>  $summary
     * @return array<string, float|null>
     */
    private function buildRates(array $summary): array
    {
        $descriptionViews = (int) $summary['description_views'];
        $formViews = (int) $summary['form_views'];
        $formSubmits = (int) $summary['form_submits'];
        $validationErrors = (int) $summary['validation_errors'];
        $ordersCreated = (int) $summary['orders_created'];

        return [
            'description_to_form' => $this->rate($formViews, $descriptionViews),
            'form_to_submit' => $this->rate($formSubmits, $formViews),
            'submit_to_order' => $this->rate($ordersCreated, $formSubmits),
            'form_to_order' => $this->rate($ordersCreated, $formViews),
            'validation_errors_per_submit' => $this->rate($validationErrors, $formSubmits),
        ];
    }

    /**
     * @param  Collection<int, AnalyticsDailyCourseStat>  $courseRows
     * @return list<array<string, mixed>>
     */
    private function buildCourseTable(Collection $courseRows): array
    {
        return $courseRows
            ->groupBy('course_id')
            ->map(function (Collection $rows, int|string $courseId): array {
                $first = $rows->first();
                $descriptionViews = (int) $rows->sum('views_course_description');
                $formViews = (int) $rows->sum('views_order_form');
                $submitAttempts = (int) $rows->sum('submit_attempts');
                $validationFailures = (int) $rows->sum('validation_failures');
                $ordersCreated = (int) $rows->sum('orders_created');

                return [
                    'course_id' => (int) $courseId,
                    'course_title_snapshot' => $first?->course_title_snapshot,
                    'description_views' => $descriptionViews,
                    'form_views' => $formViews,
                    'form_submits' => $submitAttempts,
                    'validation_errors' => $validationFailures,
                    'orders_created' => $ordersCreated,
                    'revenue_gross' => round((float) $rows->sum('revenue_snapshot'), 2),
                    'description_to_form_rate' => $this->rate($formViews, $descriptionViews),
                    'form_to_order_rate' => $this->rate($ordersCreated, $formViews),
                    'submit_to_order_rate' => $this->rate($ordersCreated, $submitAttempts),
                ];
            })
            ->sortByDesc('orders_created')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, AnalyticsDailyCourseStat>  $courseRows
     * @return array<string, mixed>
     */
    private function buildLandingTargetComparison(array $filters, Collection $courseRows): array
    {
        $landingFilter = $filters;
        $landingFilter['landing_target'] = null;

        $targets = $this->filteredCampaignQuery($landingFilter)->get();

        $descriptionViews = (int) $courseRows->sum('views_course_description');
        $formViews = (int) $courseRows->sum('views_order_form');
        $submitAttempts = (int) $courseRows->sum('submit_attempts');
        $ordersCreated = (int) $courseRows->sum('orders_created');

        return [
            'by_landing_target' => $this->landingTargetRowsFromCampaignStats($targets),
            'course_proxy' => [
                'course_description' => [
                    'label' => 'Opis szkolenia (proxy: views_course_description)',
                    'form_views' => $descriptionViews,
                    'form_submits' => $submitAttempts,
                    'orders_created' => $ordersCreated,
                    'form_to_order_rate' => $this->rate($ordersCreated, $descriptionViews),
                ],
                'order_form_direct' => [
                    'label' => 'Formularz bezpośredni (proxy: views_order_form)',
                    'form_views' => $formViews,
                    'form_submits' => $submitAttempts,
                    'orders_created' => $ordersCreated,
                    'form_to_order_rate' => $this->rate($ordersCreated, $formViews),
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, AnalyticsDailyCampaignStat>  $rows
     * @return list<array<string, mixed>>
     */
    private function landingTargetRowsFromCampaignStats(Collection $rows): array
    {
        return $rows
            ->filter(fn (AnalyticsDailyCampaignStat $row): bool => filled($row->landing_target))
            ->groupBy('landing_target')
            ->map(function (Collection $group, string $landingTarget): array {
                $formViews = (int) $group->sum('order_form_views');
                $submitAttempts = (int) $group->sum('submit_attempts');
                $ordersCreated = (int) $group->sum('orders_created');

                return [
                    'landing_target' => $landingTarget,
                    'form_views' => $formViews,
                    'form_submits' => $submitAttempts,
                    'orders_created' => $ordersCreated,
                    'form_to_order_rate' => $this->rate($ordersCreated, $formViews),
                ];
            })
            ->sortBy('landing_target')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $campaignRows
     * @param  Collection<int, AnalyticsDailyCourseStat>  $courseRows
     * @return list<array<string, string>>
     */
    private function buildAlerts(array $campaignRows, Collection $courseRows): array
    {
        $alerts = [];

        foreach ($campaignRows as $row) {
            $code = (string) $row['campaign_code'];
            $clicks = (int) $row['link_entries'];
            $forms = (int) $row['form_views'];
            $orders = (int) $row['orders_created'];

            if ($clicks >= 50 && $this->rate($forms, $clicks) !== null && $this->rate($forms, $clicks) < 5) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Kampania {$code}: dużo kliknięć ({$clicks}), mało wejść w formularz ({$forms}).",
                ];
            }

            if ($forms >= 20 && $this->rate($orders, $forms) !== null && $this->rate($orders, $forms) < 2) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Kampania {$code}: dużo formularzy ({$forms}), mało zamówień ({$orders}).",
                ];
            }

            if ($clicks >= 30 && $orders === 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => "Kampania {$code}: ruch z linku ({$clicks}) bez zamówień w wybranym okresie.",
                ];
            }
        }

        $submitAttempts = (int) $courseRows->sum('submit_attempts');
        $validationFailures = (int) $courseRows->sum('validation_failures');

        if ($submitAttempts >= 10 && $this->rate($validationFailures, $submitAttempts) !== null && $this->rate($validationFailures, $submitAttempts) > 30) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Wysoki udział błędów walidacji: {$validationFailures} na {$submitAttempts} prób submitu.",
            ];
        }

        return $alerts;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortCampaignRows(array $rows, string $sort): array
    {
        $collection = collect($rows);

        return match ($sort) {
            self::SORT_FORM_VIEWS => $collection->sortByDesc('form_views')->values()->all(),
            self::SORT_CONVERSION => $collection->sortByDesc(fn (array $row): float => $row['form_to_order_rate'] ?? -1)->values()->all(),
            self::SORT_VALIDATION => $collection->sortByDesc('validation_errors')->values()->all(),
            default => $collection->sortByDesc('orders_created')->values()->all(),
        };
    }

    private function resolveSort(string $sort): string
    {
        return in_array($sort, [
            self::SORT_ORDERS,
            self::SORT_FORM_VIEWS,
            self::SORT_CONVERSION,
            self::SORT_VALIDATION,
        ], true) ? $sort : self::SORT_ORDERS;
    }

    private function rate(int|float $numerator, int|float $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(((float) $numerator / (float) $denominator) * 100, 2);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, AnalyticsDailyCourseStat>  $courseRows
     * @param  list<array<string, mixed>>  $campaignRows
     * @param  array<string, int|float>  $summary
     * @param  array<string, float|null>  $rates
     * @return array<string, mixed>
     */
    private function buildPeriodComparison(
        array $filters,
        Collection $courseRows,
        array $campaignRows,
        array $summary,
        array $rates,
    ): array {
        $periodComparison = app(AnalyticsPeriodComparison::class);
        $previousPeriod = $periodComparison->previousPeriodDates(
            (string) $filters['date_from'],
            (string) $filters['date_to'],
        );

        $previousFilters = array_merge($filters, [
            'date_from' => $previousPeriod['date_from'],
            'date_to' => $previousPeriod['date_to'],
        ]);

        $previousCourseRows = $this->courseRows($previousFilters);
        $previousCampaignRows = $this->campaignRows($previousFilters, self::SORT_ORDERS);
        $previousSummary = $this->buildSummary($previousCourseRows, $previousCampaignRows);
        $previousRates = $this->buildRates($previousSummary);

        $currentMetrics = array_merge($summary, ['form_to_order' => $rates['form_to_order'] ?? null]);
        $previousMetrics = array_merge($previousSummary, ['form_to_order' => $previousRates['form_to_order'] ?? null]);

        return $periodComparison->build(
            (string) $filters['date_from'],
            (string) $filters['date_to'],
            $currentMetrics,
            $previousMetrics,
            [
                'short_link_visits',
                'description_views',
                'form_views',
                'form_submits',
                'validation_errors',
                'orders_created',
            ],
            ['form_to_order'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function columnMap(): array
    {
        return [
            'short_link_visits' => 'analytics_daily_campaign_stats.link_entries',
            'description_views' => 'analytics_daily_course_stats.views_course_description',
            'form_views' => 'analytics_daily_course_stats.views_order_form',
            'form_submits' => 'analytics_daily_course_stats.submit_attempts',
            'validation_errors' => 'analytics_daily_course_stats.validation_failures',
            'orders_created' => 'analytics_daily_course_stats.orders_created',
            'revenue_gross' => 'analytics_daily_course_stats.revenue_snapshot',
            'campaign_description_views' => 'analytics_daily_campaign_stats.course_description_views',
            'campaign_form_views' => 'analytics_daily_campaign_stats.order_form_views',
        ];
    }
}
