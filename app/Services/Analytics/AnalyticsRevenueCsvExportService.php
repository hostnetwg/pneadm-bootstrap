<?php

namespace App\Services\Analytics;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Eksport CSV "AI-safe" dla dashboardu rozliczeń (Etap R3).
 *
 * Czyta WYŁĄCZNIE agregaty R1/R2 (przez AnalyticsRevenueDashboardService) —
 * NIE skanuje analytics_events, NIE eksportuje raw metadata, sesji ani eventów.
 * Zawiera wyłącznie: identyfikatory kursu/kampanii, snapshot tytułu/nazwy, daty agregacji,
 * liczniki i kwoty brutto (z kropką dziesiętną, np. 150.00).
 */
class AnalyticsRevenueCsvExportService
{
    /** Liczba miejsc po przecinku dla kwot w CSV. */
    private const MONEY_PRECISION = 2;

    /**
     * Kolumny CSV per kurs (kolejność = kolejność w pliku).
     *
     * @var list<string>
     */
    private const COURSE_COLUMNS = [
        'date_from',
        'date_to',
        'course_id',
        'course_title_snapshot',
        'orders_created',
        'ordered_revenue_gross',
        'online_paid_orders',
        'online_paid_revenue_gross',
        'deferred_invoiced_orders',
        'deferred_invoiced_revenue_gross',
        'online_invoiced_marker_orders',
        'settled_orders_total',
        'settled_revenue_gross',
        'orders_created_without_campaign',
        'online_paid_without_campaign',
        'deferred_invoiced_without_campaign',
    ];

    /**
     * Kolumny CSV per kampania.
     *
     * @var list<string>
     */
    private const CAMPAIGN_COLUMNS = [
        'date_from',
        'date_to',
        'campaign_code',
        'campaign_id',
        'campaign_name',
        'orders_created',
        'ordered_revenue_gross',
        'online_paid_orders',
        'online_paid_revenue_gross',
        'deferred_invoiced_orders',
        'deferred_invoiced_revenue_gross',
        'online_invoiced_marker_orders',
        'settled_orders_total',
        'settled_revenue_gross',
    ];

    /**
     * Kolumny CSV dziennego trendu (jeden wiersz na stat_date).
     *
     * @var list<string>
     */
    private const DAILY_COLUMNS = [
        'stat_date',
        'orders_created',
        'ordered_revenue_gross',
        'online_paid_orders',
        'online_paid_revenue_gross',
        'deferred_invoiced_orders',
        'deferred_invoiced_revenue_gross',
        'online_invoiced_marker_orders',
        'settled_orders_total',
        'settled_revenue_gross',
        'orders_created_without_campaign',
        'online_paid_without_campaign',
        'deferred_invoiced_without_campaign',
    ];

    /**
     * Kolumny dzienne bez diagnostyki kampanii (gdy filtr campaign_code jest aktywny).
     *
     * @var list<string>
     */
    private const DAILY_COLUMNS_CAMPAIGN_FILTER = [
        'stat_date',
        'orders_created',
        'ordered_revenue_gross',
        'online_paid_orders',
        'online_paid_revenue_gross',
        'deferred_invoiced_orders',
        'deferred_invoiced_revenue_gross',
        'online_invoiced_marker_orders',
        'settled_orders_total',
        'settled_revenue_gross',
    ];

    public function __construct(
        private readonly AnalyticsRevenueDashboardService $dashboard,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function streamCourses(array $input): StreamedResponse
    {
        $data = $this->dashboard->build($input);
        $filters = $data['filters'];
        $rows = $this->courseExportRows($data['courses'], $filters);
        $filename = $this->filename('courses', $filters);

        return $this->stream(self::COURSE_COLUMNS, $rows, $filename);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function streamCampaigns(array $input): StreamedResponse
    {
        $data = $this->dashboard->build($input);
        $filters = $data['filters'];
        $rows = $this->campaignExportRows($data['campaigns'], $filters);
        $filename = $this->filename('campaigns', $filters);

        return $this->stream(self::CAMPAIGN_COLUMNS, $rows, $filename);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function streamDaily(array $input): StreamedResponse
    {
        $data = $this->dashboard->build($input);
        $filters = $data['filters'];
        $campaignFilter = filled($filters['campaign_code'] ?? null);
        $columns = $campaignFilter ? self::DAILY_COLUMNS_CAMPAIGN_FILTER : self::DAILY_COLUMNS;
        $rows = $this->dailyExportRows($data['trend'], $campaignFilter);
        $filename = $this->filename('daily', $filters);

        return $this->stream($columns, $rows, $filename);
    }

    /**
     * @param  list<array<string, mixed>>  $trend
     * @return list<array<string, mixed>>
     */
    private function dailyExportRows(array $trend, bool $campaignFilter): array
    {
        return array_map(function (array $row) use ($campaignFilter): array {
            $exported = [
                'stat_date' => $row['stat_date'],
                'orders_created' => (int) $row['orders_created'],
                'ordered_revenue_gross' => $this->money((float) $row['ordered_revenue_gross']),
                'online_paid_orders' => (int) $row['online_paid_orders'],
                'online_paid_revenue_gross' => $this->money((float) $row['online_paid_revenue_gross']),
                'deferred_invoiced_orders' => (int) $row['deferred_invoiced_orders'],
                'deferred_invoiced_revenue_gross' => $this->money((float) $row['deferred_invoiced_revenue_gross']),
                'online_invoiced_marker_orders' => (int) $row['online_invoiced_marker_orders'],
                'settled_orders_total' => (int) $row['settled_orders_total'],
                'settled_revenue_gross' => $this->money((float) $row['settled_revenue_gross']),
            ];

            if (! $campaignFilter) {
                $exported['orders_created_without_campaign'] = (int) ($row['orders_created_without_campaign'] ?? 0);
                $exported['online_paid_without_campaign'] = (int) ($row['online_paid_without_campaign'] ?? 0);
                $exported['deferred_invoiced_without_campaign'] = (int) ($row['deferred_invoiced_without_campaign'] ?? 0);
            }

            return $exported;
        }, $trend);
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function courseExportRows(array $courses, array $filters): array
    {
        return array_map(function (array $row) use ($filters): array {
            return [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'course_id' => $row['course_id'],
                'course_title_snapshot' => $row['course_title_snapshot'] ?? '',
                'orders_created' => (int) $row['orders_created'],
                'ordered_revenue_gross' => $this->money((float) $row['ordered_revenue_gross']),
                'online_paid_orders' => (int) $row['online_paid_orders'],
                'online_paid_revenue_gross' => $this->money((float) $row['online_paid_revenue_gross']),
                'deferred_invoiced_orders' => (int) $row['deferred_invoiced_orders'],
                'deferred_invoiced_revenue_gross' => $this->money((float) $row['deferred_invoiced_revenue_gross']),
                'online_invoiced_marker_orders' => (int) $row['online_invoiced_marker_orders'],
                'settled_orders_total' => (int) $row['settled_orders_total'],
                'settled_revenue_gross' => $this->money((float) $row['settled_revenue_gross']),
                'orders_created_without_campaign' => (int) ($row['orders_created_without_campaign'] ?? 0),
                'online_paid_without_campaign' => (int) ($row['online_paid_without_campaign'] ?? 0),
                'deferred_invoiced_without_campaign' => (int) ($row['deferred_invoiced_without_campaign'] ?? 0),
            ];
        }, $courses);
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function campaignExportRows(array $campaigns, array $filters): array
    {
        return array_map(function (array $row) use ($filters): array {
            return [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'campaign_code' => $row['campaign_code'] ?? '',
                'campaign_id' => $row['campaign_id'] ?? '',
                'campaign_name' => $row['campaign_name'] ?? '',
                'orders_created' => (int) $row['orders_created'],
                'ordered_revenue_gross' => $this->money((float) $row['ordered_revenue_gross']),
                'online_paid_orders' => (int) $row['online_paid_orders'],
                'online_paid_revenue_gross' => $this->money((float) $row['online_paid_revenue_gross']),
                'deferred_invoiced_orders' => (int) $row['deferred_invoiced_orders'],
                'deferred_invoiced_revenue_gross' => $this->money((float) $row['deferred_invoiced_revenue_gross']),
                'online_invoiced_marker_orders' => (int) $row['online_invoiced_marker_orders'],
                'settled_orders_total' => (int) $row['settled_orders_total'],
                'settled_revenue_gross' => $this->money((float) $row['settled_revenue_gross']),
            ];
        }, $campaigns);
    }

    private function money(float $value): string
    {
        return number_format(round($value, self::MONEY_PRECISION), self::MONEY_PRECISION, '.', '');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filename(string $type, array $filters): string
    {
        return sprintf(
            'pne-revenue-%s-%s_%s.csv',
            $type,
            $filters['date_from'],
            $filters['date_to'],
        );
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function stream(array $columns, array $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ];

        $callback = function () use ($columns, $rows): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                $ordered = [];
                foreach ($columns as $column) {
                    $ordered[] = $row[$column] ?? '';
                }
                fputcsv($handle, $ordered);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
