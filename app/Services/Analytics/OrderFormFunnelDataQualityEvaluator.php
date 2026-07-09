<?php

namespace App\Services\Analytics;

use Carbon\Carbon;

/**
 * Status, flagi i score jakości trackingu dla raportu dziennego B4+.
 */
class OrderFormFunnelDataQualityEvaluator
{
    public function minSessionsForAlerts(): int
    {
        return max(1, (int) config('analytics.order_form_funnel.data_quality_min_sessions', 30));
    }

    /**
     * @param  array<string, int|float|list<int>>  $dataQuality
     * @return array{status: string, flags: list<string>, score: int}
     */
    public function evaluate(array $dataQuality, string $statDate, string $timezone): array
    {
        $sessions = max(0, (int) $dataQuality['sessions_total']);
        $orders = max(0, (int) $dataQuality['orders_total']);

        $frontendRate = (float) ($dataQuality['frontend_tracking_coverage_rate'] ?? 0);
        $trafficRate = (float) ($dataQuality['traffic_channel_coverage_rate'] ?? 0);
        $attrRate = (float) ($dataQuality['attribution_coverage_rate'] ?? 0);
        $schemaV2Rate = (float) ($dataQuality['schema_v2_event_rate'] ?? 0);

        $serverOnlyRate = $orders > 0
            ? (int) $dataQuality['server_only_conversions'] / $orders
            : 0.0;
        $ordersWithoutAttrRate = $orders > 0
            ? (int) $dataQuality['orders_without_attribution'] / $orders
            : 0.0;
        $ordersBackendOnlyRate = $orders > 0
            ? (int) $dataQuality['orders_backend_only'] / $orders
            : 0.0;

        $flags = [];

        if ($this->isWarmupOrDeployWindow($statDate, $timezone, $dataQuality)) {
            $flags[] = 'warmup_or_deploy_window';
        }

        if ($sessions < $this->minSessionsForAlerts()) {
            $flags[] = 'low_volume';

            return [
                'status' => 'low_volume',
                'flags' => $flags,
                'score' => $this->calculateScore($frontendRate, $trafficRate, $attrRate, $schemaV2Rate, $serverOnlyRate, $ordersWithoutAttrRate),
            ];
        }

        if ($frontendRate < 0.5) {
            $flags[] = 'frontend_coverage_low';
        } elseif ($frontendRate < 0.95) {
            $flags[] = 'frontend_coverage_degraded';
        }

        if ($serverOnlyRate > 0.3) {
            $flags[] = 'server_only_conversions_critical';
        } elseif ($serverOnlyRate > 0.1) {
            $flags[] = 'server_only_conversions_elevated';
        }

        if ($trafficRate < 0.7 && $orders > 0) {
            $flags[] = 'traffic_channel_coverage_critical';
        } elseif ($trafficRate < 0.9) {
            $flags[] = 'traffic_channel_coverage_degraded';
        }

        if ($ordersWithoutAttrRate > 0.1) {
            $flags[] = 'orders_without_attribution_elevated';
        }

        if ($attrRate < 0.9) {
            $flags[] = 'attribution_coverage_degraded';
        }

        if ($schemaV2Rate < 0.95) {
            $flags[] = 'schema_v2_coverage_degraded';
        }

        $status = $this->resolvePrimaryStatus(
            $sessions,
            $frontendRate,
            $trafficRate,
            $attrRate,
            $schemaV2Rate,
            $serverOnlyRate,
            $ordersWithoutAttrRate,
            $ordersBackendOnlyRate,
            $flags,
        );

        return [
            'status' => $status,
            'flags' => array_values(array_unique($flags)),
            'score' => $this->calculateScore($frontendRate, $trafficRate, $attrRate, $schemaV2Rate, $serverOnlyRate, $ordersWithoutAttrRate),
        ];
    }

    /**
     * @param  array<string, int|float|list<int>>  $dataQuality
     */
    private function isWarmupOrDeployWindow(string $statDate, string $timezone, array $dataQuality): bool
    {
        $deployedAt = config('analytics.order_form_funnel.tracking_deployed_at');
        if (filled($deployedAt)) {
            $deploy = Carbon::parse((string) $deployedAt, $timezone)->startOfDay();
            $stat = Carbon::parse($statDate, $timezone)->startOfDay();
            $warmupHours = max(1, (int) config('analytics.order_form_funnel.warmup_hours', 24));
            if ($stat->betweenIncluded($deploy, $deploy->copy()->addHours($warmupHours))) {
                return true;
            }
        }

        $versions = $dataQuality['_schema_versions_seen'] ?? [];
        if (is_array($versions) && count($versions) > 1) {
            return true;
        }

        if ((int) ($dataQuality['sessions_total'] ?? 0) === 0 && (int) ($dataQuality['orders_total'] ?? 0) === 0) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $flags
     */
    private function resolvePrimaryStatus(
        int $sessions,
        float $frontendRate,
        float $trafficRate,
        float $attrRate,
        float $schemaV2Rate,
        float $serverOnlyRate,
        float $ordersWithoutAttrRate,
        float $ordersBackendOnlyRate,
        array $flags,
    ): string {
        if (in_array('warmup_or_deploy_window', $flags, true) && $sessions < $this->minSessionsForAlerts()) {
            return 'warmup_or_deploy_window';
        }

        if ($frontendRate < 0.5 || $serverOnlyRate > 0.2 || $ordersBackendOnlyRate > 0.2) {
            return 'backend_only';
        }

        if ($trafficRate < 0.9 || $attrRate < 0.9 || $ordersWithoutAttrRate > 0.1) {
            return 'missing_attribution';
        }

        if ($frontendRate >= 0.5 && $frontendRate < 0.95) {
            return 'partial_frontend_tracking';
        }

        if ($sessions >= $this->minSessionsForAlerts()
            && $frontendRate >= 0.95
            && $trafficRate >= 0.98
            && $attrRate >= 0.95
            && $serverOnlyRate <= 0.03
            && $ordersWithoutAttrRate <= 0.03
            && $schemaV2Rate >= 0.95) {
            return 'complete';
        }

        if (in_array('warmup_or_deploy_window', $flags, true)) {
            return 'warmup_or_deploy_window';
        }

        return 'partial_frontend_tracking';
    }

    private function calculateScore(
        float $frontendRate,
        float $trafficRate,
        float $attrRate,
        float $schemaV2Rate,
        float $serverOnlyRate,
        float $ordersWithoutAttrRate,
    ): int {
        $score = 0;
        $score += (int) round($frontendRate * 30);
        $score += (int) round($trafficRate * 25);
        $score += (int) round($attrRate * 20);
        $score += (int) round($schemaV2Rate * 15);
        $score -= (int) round($serverOnlyRate * 40);
        $score -= (int) round($ordersWithoutAttrRate * 30);

        return max(0, min(100, $score));
    }

    /**
     * Alerty operacyjne na podstawie agregatu dziennego (read-only healthcheck).
     *
     * @param  array<string, mixed>  $dataQuality
     * @return array{
     *     level: string,
     *     critical: list<string>,
     *     warning: list<string>,
     *     info: list<string>,
     *     skipped_hard_alerts: bool,
     *     evaluation: array{status: string, flags: list<string>, score: int}
     * }
     */
    public function assessOperationalAlerts(array $dataQuality, string $statDate, string $timezone): array
    {
        $evaluation = $this->evaluate($dataQuality, $statDate, $timezone);
        $status = $evaluation['status'];
        $sessions = max(0, (int) ($dataQuality['sessions_total'] ?? 0));
        $orders = max(0, (int) ($dataQuality['orders_total'] ?? 0));

        $frontendRate = (float) ($dataQuality['frontend_tracking_coverage_rate'] ?? 0);
        $trafficRate = (float) ($dataQuality['traffic_channel_coverage_rate'] ?? 0);
        $attrRate = (float) ($dataQuality['attribution_coverage_rate'] ?? 0);
        $schemaV2Rate = (float) ($dataQuality['schema_v2_event_rate'] ?? 0);
        $serverOnlyRate = $orders > 0 ? (int) ($dataQuality['server_only_conversions'] ?? 0) / $orders : 0.0;
        $ordersWithoutAttrRate = $orders > 0 ? (int) ($dataQuality['orders_without_attribution'] ?? 0) / $orders : 0.0;

        $critical = [];
        $warning = [];
        $info = [];

        if ($status === 'low_volume') {
            $info[] = sprintf('Niski wolumen (%d sesji < %d) — pominięto twarde alerty.', $sessions, $this->minSessionsForAlerts());

            return $this->alertResult('info', $critical, $warning, $info, true, $evaluation);
        }

        if ($status === 'warmup_or_deploy_window') {
            $info[] = 'Okno warmup/deploy — pominięto twarde alerty (dane mogą być niepełne).';

            return $this->alertResult('info', $critical, $warning, $info, true, $evaluation);
        }

        if ($frontendRate < 0.5) {
            $critical[] = sprintf('frontend_tracking_coverage_rate=%.1f%% (<50%%)', $frontendRate * 100);
        } elseif ($frontendRate < 0.9) {
            $warning[] = sprintf('frontend_tracking_coverage_rate=%.1f%% (50–90%%)', $frontendRate * 100);
        }

        if ($orders > 0 && $trafficRate < 0.7) {
            $critical[] = sprintf('traffic_channel_coverage_rate=%.1f%% przy order_created>0', $trafficRate * 100);
        } elseif ($trafficRate < 0.9) {
            $warning[] = sprintf('traffic_channel_coverage_rate=%.1f%% (<90%%)', $trafficRate * 100);
        }

        if ($serverOnlyRate > 0.3) {
            $critical[] = sprintf('server_only_conversion_rate=%.1f%% (>30%%)', $serverOnlyRate * 100);
        } elseif ($serverOnlyRate > 0.1) {
            $warning[] = sprintf('server_only_conversion_rate=%.1f%% (10–30%%)', $serverOnlyRate * 100);
        }

        if ($ordersWithoutAttrRate > 0.1) {
            $warning[] = sprintf('orders_without_attribution_rate=%.1f%% (>10%%)', $ordersWithoutAttrRate * 100);
        }

        if ($attrRate < 0.9) {
            $warning[] = sprintf('attribution_coverage_rate=%.1f%% (<90%%)', $attrRate * 100);
        }

        if ($schemaV2Rate < 0.95) {
            $warning[] = sprintf('schema_v2_event_rate=%.1f%% (<95%%)', $schemaV2Rate * 100);
        }

        if (in_array('frontend_coverage_low', $evaluation['flags'], true) && $frontendRate >= 0.5) {
            $warning[] = 'frontend_coverage_degraded';
        }

        $level = $critical !== [] ? 'critical' : ($warning !== [] ? 'warning' : 'ok');

        if ($level === 'ok') {
            $info[] = sprintf('Status=%s, score=%d — progi w normie.', $status, $evaluation['score']);
        }

        return $this->alertResult($level, $critical, $warning, $info, false, $evaluation);
    }

    /**
     * @param  list<string>  $critical
     * @param  list<string>  $warning
     * @param  list<string>  $info
     * @param  array{status: string, flags: list<string>, score: int}  $evaluation
     * @return array{
     *     level: string,
     *     critical: list<string>,
     *     warning: list<string>,
     *     info: list<string>,
     *     skipped_hard_alerts: bool,
     *     evaluation: array{status: string, flags: list<string>, score: int}
     * }
     */
    private function alertResult(
        string $level,
        array $critical,
        array $warning,
        array $info,
        bool $skippedHardAlerts,
        array $evaluation,
    ): array {
        return [
            'level' => $level,
            'critical' => $critical,
            'warning' => $warning,
            'info' => $info,
            'skipped_hard_alerts' => $skippedHardAlerts,
            'evaluation' => $evaluation,
        ];
    }
}
