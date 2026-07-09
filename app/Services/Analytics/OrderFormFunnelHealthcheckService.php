<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY healthcheck B4+ — jakość trackingu formularza, atrybucja, lejek v2.
 */
class OrderFormFunnelHealthcheckService
{
    /** @var list<string> */
    private const V2_INFLOW_EVENTS = [
        'form_visible',
        'form_first_interaction',
        'form_submit_clicked',
        'client_validation_failed',
    ];

    /** @var list<string> */
    private const FORM_ENTRY_EVENTS = [
        'order_form_viewed',
        'form_visible',
    ];

    public function __construct(
        private readonly OrderFormFunnelDataQualityEvaluator $evaluator,
    ) {}

    public function timezone(): string
    {
        return (string) config('analytics.order_form_funnel.timezone', 'Europe/Warsaw');
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     timezone: string,
     *     tables_ok: bool,
     *     missing_tables: list<string>,
     *     v2_inflow: array<string, mixed>,
     *     daily_alerts: list<array<string, mixed>>,
     *     has_critical: bool,
     *     has_warning: bool
     * }
     */
    public function run(string $from, string $to): array
    {
        $timezone = $this->timezone();
        $connection = DB::connection('analytics');

        $requiredTables = [
            'analytics_daily_data_quality',
            'analytics_daily_channel_funnels',
            'order_form_attributions',
            'analytics_events',
        ];

        $missingTables = array_values(array_filter(
            $requiredTables,
            fn (string $table): bool => ! Schema::connection('analytics')->hasTable($table)
        ));

        $v2Inflow = $this->checkV2EventInflow($connection, $from, $to);
        $dailyAlerts = $this->checkDailyDataQuality($connection, $from, $to, $timezone);

        $hasCritical = $v2Inflow['critical'] || collect($dailyAlerts)->contains(fn (array $row): bool => $row['level'] === 'critical');
        $hasWarning = $v2Inflow['warning'] || collect($dailyAlerts)->contains(fn (array $row): bool => $row['level'] === 'warning');

        return [
            'from' => $from,
            'to' => $to,
            'timezone' => $timezone,
            'tables_ok' => $missingTables === [],
            'missing_tables' => $missingTables,
            'v2_inflow' => $v2Inflow,
            'daily_alerts' => $dailyAlerts,
            'has_critical' => $hasCritical,
            'has_warning' => $hasWarning,
        ];
    }

    /**
     * @return array{
     *     recent_form_entries: int,
     *     recent_v2_events: int,
     *     window_minutes: int,
     *     critical: bool,
     *     warning: bool,
     *     message: string
     * }
     */
    private function checkV2EventInflow(\Illuminate\Database\Connection $connection, string $from, string $to): array
    {
        $windowMinutes = max(30, (int) config('analytics.order_form_funnel.healthcheck_v2_window_minutes', 60));
        $since = Carbon::now('UTC')->subMinutes($windowMinutes);

        $formEntries = (int) $connection->table('analytics_events')
            ->where('occurred_at', '>=', $since)
            ->whereIn('event_name', self::FORM_ENTRY_EVENTS)
            ->count();

        $v2Events = (int) $connection->table('analytics_events')
            ->where('occurred_at', '>=', $since)
            ->whereIn('event_name', self::V2_INFLOW_EVENTS)
            ->count();

        $critical = false;
        $warning = false;
        $message = sprintf(
            'Ostatnie %d min: wejścia formularza=%d, eventy v2=%d.',
            $windowMinutes,
            $formEntries,
            $v2Events
        );

        if ($formEntries > 0 && $v2Events === 0) {
            $critical = true;
            $message .= ' CRITICAL: wejścia bez eventów v2.';
        } elseif ($formEntries > 5 && $v2Events < (int) floor($formEntries * 0.5)) {
            $warning = true;
            $message .= ' WARNING: niski udział eventów v2 względem wejść.';
        }

        return [
            'recent_form_entries' => $formEntries,
            'recent_v2_events' => $v2Events,
            'window_minutes' => $windowMinutes,
            'critical' => $critical,
            'warning' => $warning,
            'message' => $message,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function checkDailyDataQuality(
        \Illuminate\Database\Connection $connection,
        string $from,
        string $to,
        string $timezone,
    ): array {
        if (! Schema::connection('analytics')->hasTable('analytics_daily_data_quality')) {
            return [];
        }

        /** @var Collection<int, object> $rows */
        $rows = $connection->table('analytics_daily_data_quality')
            ->whereBetween('stat_date', [$from, $to])
            ->orderByDesc('stat_date')
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $dataQuality = [
                'sessions_total' => (int) $row->sessions_total,
                'sessions_with_frontend_events' => (int) $row->sessions_with_frontend_events,
                'sessions_backend_only' => (int) $row->sessions_backend_only,
                'sessions_with_attribution' => (int) $row->sessions_with_attribution,
                'sessions_without_attribution' => (int) $row->sessions_without_attribution,
                'sessions_with_traffic_channel' => (int) $row->sessions_with_traffic_channel,
                'sessions_without_traffic_channel' => (int) ($row->sessions_without_traffic_channel ?? 0),
                'orders_total' => (int) $row->orders_total,
                'orders_backend_only' => (int) ($row->orders_backend_only ?? 0),
                'orders_without_attribution' => (int) $row->orders_without_attribution,
                'server_only_conversions' => (int) $row->server_only_conversions,
                'frontend_tracking_coverage_rate' => (float) $row->frontend_tracking_coverage_rate,
                'attribution_coverage_rate' => (float) $row->attribution_coverage_rate,
                'traffic_channel_coverage_rate' => (float) $row->traffic_channel_coverage_rate,
                'schema_v2_event_rate' => (float) ($row->schema_v2_event_rate ?? 0),
            ];

            $alerts = $this->evaluator->assessOperationalAlerts(
                $dataQuality,
                (string) $row->stat_date,
                $timezone
            );

            $results[] = [
                'stat_date' => (string) $row->stat_date,
                'sessions_total' => (int) $row->sessions_total,
                'score' => $alerts['evaluation']['score'],
                'status' => $alerts['evaluation']['status'],
                'level' => $alerts['level'],
                'critical' => $alerts['critical'],
                'warning' => $alerts['warning'],
                'info' => $alerts['info'],
                'skipped_hard_alerts' => $alerts['skipped_hard_alerts'],
            ];
        }

        return $results;
    }
}
