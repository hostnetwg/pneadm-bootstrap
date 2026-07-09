<?php

namespace App\Services\Analytics;

use App\Models\Analytics\AnalyticsDailyCampaignFunnel;
use App\Models\Analytics\AnalyticsDailyChannelFunnel;
use App\Models\Analytics\AnalyticsDailyCourseChannelFunnel;
use App\Models\Analytics\AnalyticsDailyDataQuality;
use App\Models\Analytics\AnalyticsDailyGusChannelFunnel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Raporty B4 — lejek formularza per kanał/kurs/kampania/GUS/jakość danych.
 * READ-ONLY — czyta wyłącznie tabele analytics_daily_*_funnels.
 */
class OrderFormFunnelDashboardService
{
    public function timezone(): string
    {
        return (string) config('analytics.order_form_funnel_dashboard.timezone', 'Europe/Warsaw');
    }

    public function defaultDays(): int
    {
        return max(1, (int) config('analytics.order_form_funnel_dashboard.default_days', 14));
    }

    public function maxDays(): int
    {
        return max(1, (int) config('analytics.order_form_funnel_dashboard.max_days', 366));
    }

    public function aggregationLagDays(): int
    {
        return max(0, (int) config('analytics.order_form_funnel.aggregation_lag_days', 2));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array
    {
        $filters = $this->resolveFilters($input);

        $channelRows = $this->channelQuery($filters)->orderByDesc('sessions_total')->get();
        $courseRows = $this->courseChannelQuery($filters)->orderByDesc('sessions_total')->limit(100)->get();
        $campaignRows = $this->campaignQuery($filters)->orderByDesc('sessions_total')->limit(100)->get();
        $gusRows = $this->gusQuery($filters)->orderByDesc('sessions_total')->limit(100)->get();
        $qualityRows = $this->qualityQuery($filters)->orderBy('stat_date')->get();

        $summary = $this->buildChannelSummary($channelRows);
        $attributionDeployedAt = (string) config('analytics.order_form_funnel.attribution_deployed_at', '');

        return [
            'filters' => $filters,
            'summary' => $summary,
            'channels' => $channelRows,
            'courses' => $courseRows,
            'campaigns' => $campaignRows,
            'gus' => $gusRows,
            'data_quality' => $qualityRows,
            'meta' => [
                'lag_days' => $this->aggregationLagDays(),
                'timezone' => $this->timezone(),
                'attribution_deployed_at' => $attributionDeployedAt !== '' ? $attributionDeployedAt : null,
                'includes_pre_attribution_days' => $attributionDeployedAt !== ''
                    && ($filters['date_from'] ?? '') < $attributionDeployedAt,
                'source_tables' => [
                    'analytics_daily_channel_funnels',
                    'analytics_daily_course_channel_funnels',
                    'analytics_daily_campaign_funnels',
                    'analytics_daily_gus_channel_funnels',
                    'analytics_daily_data_quality',
                ],
                'gus_note' => 'gus_conversion_delta to korelacja obserwacyjna, nie dowód przyczynowy wpływu GUS na konwersję.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function resolveFilters(array $input): array
    {
        $timezone = $this->timezone();
        $lag = $this->aggregationLagDays();
        $defaultEnd = Carbon::now($timezone)->subDays($lag)->startOfDay();
        $defaultStart = $defaultEnd->copy()->subDays($this->defaultDays() - 1);

        $dateFrom = filled($input['date_from'] ?? null)
            ? Carbon::parse((string) $input['date_from'], $timezone)->startOfDay()
            : $defaultStart;
        $dateTo = filled($input['date_to'] ?? null)
            ? Carbon::parse((string) $input['date_to'], $timezone)->startOfDay()
            : $defaultEnd;

        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $days = $dateFrom->diffInDays($dateTo) + 1;
        if ($days > $this->maxDays()) {
            $dateFrom = $dateTo->copy()->subDays($this->maxDays() - 1);
        }

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'traffic_channel' => filled($input['traffic_channel'] ?? null) ? (string) $input['traffic_channel'] : null,
            'course_id' => is_numeric($input['course_id'] ?? null) ? (int) $input['course_id'] : null,
            'campaign_code' => filled($input['campaign_code'] ?? null) ? (string) $input['campaign_code'] : null,
            'internal_promo_placement' => filled($input['internal_promo_placement'] ?? null)
                ? (string) $input['internal_promo_placement']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function channelQuery(array $filters): Builder
    {
        $query = AnalyticsDailyChannelFunnel::query()
            ->whereDate('stat_date', '>=', $filters['date_from'])
            ->whereDate('stat_date', '<=', $filters['date_to']);

        $this->applyCommonFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function courseChannelQuery(array $filters): Builder
    {
        $query = AnalyticsDailyCourseChannelFunnel::query()
            ->whereDate('stat_date', '>=', $filters['date_from'])
            ->whereDate('stat_date', '<=', $filters['date_to']);

        if ($filters['course_id'] !== null) {
            $query->where('course_id', $filters['course_id']);
        }

        $this->applyCommonFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function campaignQuery(array $filters): Builder
    {
        $query = AnalyticsDailyCampaignFunnel::query()
            ->whereDate('stat_date', '>=', $filters['date_from'])
            ->whereDate('stat_date', '<=', $filters['date_to']);

        if ($filters['campaign_code'] !== null) {
            $query->where('campaign_code', $filters['campaign_code']);
        }
        if ($filters['course_id'] !== null) {
            $query->where('course_id', $filters['course_id']);
        }

        $this->applyCommonFilters($query, $filters, false);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function gusQuery(array $filters): Builder
    {
        $query = AnalyticsDailyGusChannelFunnel::query()
            ->whereDate('stat_date', '>=', $filters['date_from'])
            ->whereDate('stat_date', '<=', $filters['date_to']);

        if ($filters['course_id'] !== null) {
            $query->where('course_id', $filters['course_id']);
        }

        $this->applyCommonFilters($query, $filters, false);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function qualityQuery(array $filters): Builder
    {
        return AnalyticsDailyDataQuality::query()
            ->whereDate('stat_date', '>=', $filters['date_from'])
            ->whereDate('stat_date', '<=', $filters['date_to']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonFilters(Builder $query, array $filters, bool $withInternalPromo = true): void
    {
        if ($filters['traffic_channel'] !== null) {
            $query->where('traffic_channel', $filters['traffic_channel']);
        }

        if ($withInternalPromo && $filters['internal_promo_placement'] !== null) {
            $query->where('internal_promo_placement', $filters['internal_promo_placement']);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AnalyticsDailyChannelFunnel>  $rows
     * @return array<string, int|float>
     */
    private function buildChannelSummary($rows): array
    {
        $summary = [
            'sessions_total' => 0,
            'order_created' => 0,
            'first_interaction' => 0,
            'gus_success_sessions' => 0,
            'gus_error_sessions' => 0,
            'server_only_conversions' => 0,
            'frontend_only_abandonments' => 0,
        ];

        foreach ($rows as $row) {
            foreach (array_keys($summary) as $key) {
                $summary[$key] += (int) $row->{$key};
            }
        }

        $summary['conversion_rate'] = $summary['sessions_total'] > 0
            ? round($summary['order_created'] / $summary['sessions_total'], 4)
            : 0.0;

        return $summary;
    }
}
