<?php

namespace App\Services\Analytics;

use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderFormFunnelCsvExportService
{
    public function __construct(
        private readonly OrderFormFunnelDashboardService $dashboard,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function streamChannels(array $filters): StreamedResponse
    {
        $data = $this->dashboard->build($filters);

        return $this->stream(
            'pne-order-form-funnels-channels',
            $filters,
            [
                'stat_date', 'traffic_channel', 'conversion_reporting_channel', 'traffic_source', 'traffic_medium',
                'traffic_campaign', 'sessions_total', 'order_created', 'conversion_rate', 'first_interaction',
                'reached_submit_clicked', 'server_submit_attempted', 'gus_success_sessions', 'gus_error_sessions',
                'server_only_conversions', 'frontend_only_abandonments', 'internal_promo_placement',
            ],
            $data['channels']->map(fn ($row): array => [
                $row->stat_date?->toDateString(),
                $row->traffic_channel,
                $row->conversion_reporting_channel,
                $row->traffic_source,
                $row->traffic_medium,
                $row->traffic_campaign,
                $row->sessions_total,
                $row->order_created,
                $row->conversion_rate,
                $row->first_interaction,
                $row->reached_submit_clicked,
                $row->server_submit_attempted,
                $row->gus_success_sessions,
                $row->gus_error_sessions,
                $row->server_only_conversions,
                $row->frontend_only_abandonments,
                $row->internal_promo_placement,
            ])->all()
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function streamCourses(array $filters): StreamedResponse
    {
        $data = $this->dashboard->build($filters);

        return $this->stream(
            'pne-order-form-funnels-courses',
            $filters,
            [
                'stat_date', 'course_id', 'course_title_snapshot', 'traffic_channel', 'conversion_reporting_channel',
                'traffic_source', 'traffic_medium', 'traffic_campaign', 'sessions_total', 'order_created',
                'conversion_rate', 'first_interaction', 'reached_submit_clicked', 'server_submit_attempted',
                'gus_success_sessions', 'gus_error_sessions', 'server_only_conversions', 'frontend_only_abandonments',
            ],
            $data['courses']->map(fn ($row): array => [
                $row->stat_date?->toDateString(),
                $row->course_id,
                $row->course_title_snapshot,
                $row->traffic_channel,
                $row->conversion_reporting_channel,
                $row->traffic_source,
                $row->traffic_medium,
                $row->traffic_campaign,
                $row->sessions_total,
                $row->order_created,
                $row->conversion_rate,
                $row->first_interaction,
                $row->reached_submit_clicked,
                $row->server_submit_attempted,
                $row->gus_success_sessions,
                $row->gus_error_sessions,
                $row->server_only_conversions,
                $row->frontend_only_abandonments,
            ])->all()
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function streamCampaigns(array $filters): StreamedResponse
    {
        $data = $this->dashboard->build($filters);

        return $this->stream(
            'pne-order-form-funnels-campaigns',
            $filters,
            [
                'stat_date', 'campaign_code', 'campaign_id', 'campaign_name', 'traffic_channel', 'traffic_source',
                'traffic_medium', 'traffic_campaign', 'course_id', 'sessions_total', 'order_created',
                'conversion_rate', 'first_interaction', 'reached_submit_clicked', 'server_submit_attempted',
                'gus_success_sessions', 'gus_error_sessions', 'server_only_conversions',
                'sessions_without_campaign_metadata', 'suspicious_campaign_name_count', 'campaign_course_mismatch_count',
            ],
            $data['campaigns']->map(fn ($row): array => [
                $row->stat_date?->toDateString(),
                $row->campaign_code,
                $row->campaign_id,
                $row->campaign_name,
                $row->traffic_channel,
                $row->traffic_source,
                $row->traffic_medium,
                $row->traffic_campaign,
                $row->course_id,
                $row->sessions_total,
                $row->order_created,
                $row->conversion_rate,
                $row->first_interaction,
                $row->reached_submit_clicked,
                $row->server_submit_attempted,
                $row->gus_success_sessions,
                $row->gus_error_sessions,
                $row->server_only_conversions,
                $row->sessions_without_campaign_metadata,
                $row->suspicious_campaign_name_count,
                $row->campaign_course_mismatch_count,
            ])->all()
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function streamGus(array $filters): StreamedResponse
    {
        $data = $this->dashboard->build($filters);

        return $this->stream(
            'pne-order-form-funnels-gus',
            $filters,
            [
                'stat_date', 'course_id', 'traffic_channel', 'target', 'sessions_total', 'gus_lookup_success',
                'gus_lookup_error', 'orders_after_gus_success', 'orders_after_gus_error', 'orders_without_gus',
                'conversion_rate_with_gus', 'conversion_rate_without_gus', 'gus_conversion_delta',
                'recovered_after_gus_error', 'avg_gus_latency_ms',
            ],
            $data['gus']->map(fn ($row): array => [
                $row->stat_date?->toDateString(),
                $row->course_id,
                $row->traffic_channel,
                $row->target,
                $row->sessions_total,
                $row->gus_lookup_success,
                $row->gus_lookup_error,
                $row->orders_after_gus_success,
                $row->orders_after_gus_error,
                $row->orders_without_gus,
                $row->conversion_rate_with_gus,
                $row->conversion_rate_without_gus,
                $row->gus_conversion_delta,
                $row->recovered_after_gus_error,
                $row->avg_gus_latency_ms,
            ])->all()
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function streamDataQuality(array $filters): StreamedResponse
    {
        $data = $this->dashboard->build($filters);

        return $this->stream(
            'pne-order-form-funnels-data-quality',
            $filters,
            [
                'stat_date', 'sessions_total', 'sessions_with_frontend_events', 'sessions_backend_only',
                'sessions_with_attribution', 'sessions_without_attribution', 'sessions_with_traffic_channel',
                'orders_total', 'server_only_conversions', 'frontend_tracking_coverage_rate',
                'attribution_coverage_rate', 'traffic_channel_coverage_rate', 'schema_v2_event_rate',
                'tracking_data_quality_status', 'tracking_data_quality_score', 'tracking_data_quality_flags',
            ],
            $data['data_quality']->map(fn ($row): array => [
                $row->stat_date?->toDateString(),
                $row->sessions_total,
                $row->sessions_with_frontend_events,
                $row->sessions_backend_only,
                $row->sessions_with_attribution,
                $row->sessions_without_attribution,
                $row->sessions_with_traffic_channel,
                $row->orders_total,
                $row->server_only_conversions,
                $row->frontend_tracking_coverage_rate,
                $row->attribution_coverage_rate,
                $row->traffic_channel_coverage_rate,
                $row->schema_v2_event_rate,
                $row->tracking_data_quality_status,
                $row->tracking_data_quality_score,
                is_array($row->tracking_data_quality_flags) ? implode('|', $row->tracking_data_quality_flags) : '',
            ])->all()
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<int|string|null>>  $rows
     */
    private function stream(string $prefix, array $filters, array $headers, array $rows): StreamedResponse
    {
        $filename = sprintf(
            '%s-%s_%s.csv',
            $prefix,
            $filters['date_from'] ?? 'start',
            $filters['date_to'] ?? 'end'
        );

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
