<?php

namespace App\Services\Analytics;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Eksport CSV "AI-safe" dla dashboardu porzuceń formularza (Etap B5).
 *
 * Czyta WYŁĄCZNIE agregaty B3/B4 (przez AnalyticsFormAbandonmentDashboardService) —
 * NIE skanuje analytics_events, NIE eksportuje raw metadata, sesji ani eventów.
 * Zawiera wyłącznie: identyfikatory kursu/kampanii, snapshot tytułu/nazwy, daty agregacji,
 * liczniki i rates (jako ułamki dziesiętne z kropką, np. 0.2375).
 */
class AnalyticsFormAbandonmentCsvExportService
{
    /** Liczba miejsc po przecinku dla rates w CSV. */
    private const RATE_PRECISION = 4;

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
        'sessions_total',
        'reached_viewed',
        'reached_started',
        'reached_submit_clicked',
        'reached_submit_attempted',
        'reached_created',
        'viewed_not_started',
        'started_not_submit_clicked',
        'submit_clicked_not_attempted',
        'submit_attempted_not_created',
        'converted',
        'abandoned_total',
        'conversion_rate',
        'viewed_not_started_rate',
        'started_not_submit_clicked_rate',
        'submit_clicked_not_attempted_rate',
        'submit_attempted_not_created_rate',
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
        'sessions_total',
        'reached_viewed',
        'reached_started',
        'reached_submit_clicked',
        'reached_submit_attempted',
        'reached_created',
        'viewed_not_started',
        'started_not_submit_clicked',
        'submit_clicked_not_attempted',
        'submit_attempted_not_created',
        'converted',
        'abandoned_total',
        'conversion_rate',
        'viewed_not_started_rate',
        'started_not_submit_clicked_rate',
        'submit_clicked_not_attempted_rate',
        'submit_attempted_not_created_rate',
    ];

    public function __construct(
        private readonly AnalyticsFormAbandonmentDashboardService $dashboard,
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
     * @param  list<array<string, mixed>>  $courses
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function courseExportRows(array $courses, array $filters): array
    {
        return array_map(function (array $row) use ($filters): array {
            $sessions = (int) $row['sessions_total'];

            return [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'course_id' => $row['course_id'],
                'course_title_snapshot' => $row['course_title_snapshot'] ?? '',
                'sessions_total' => $sessions,
                'reached_viewed' => (int) $row['reached_viewed'],
                'reached_started' => (int) $row['reached_started'],
                'reached_submit_clicked' => (int) $row['reached_submit_clicked'],
                'reached_submit_attempted' => (int) $row['reached_submit_attempted'],
                'reached_created' => (int) $row['reached_created'],
                'viewed_not_started' => (int) $row['viewed_not_started'],
                'started_not_submit_clicked' => (int) $row['started_not_submit_clicked'],
                'submit_clicked_not_attempted' => (int) $row['submit_clicked_not_attempted'],
                'submit_attempted_not_created' => (int) $row['submit_attempted_not_created'],
                'converted' => (int) $row['converted'],
                'abandoned_total' => (int) $row['abandoned_total'],
                'conversion_rate' => $this->rate((int) $row['converted'], $sessions),
                'viewed_not_started_rate' => $this->rate((int) $row['viewed_not_started'], $sessions),
                'started_not_submit_clicked_rate' => $this->rate((int) $row['started_not_submit_clicked'], $sessions),
                'submit_clicked_not_attempted_rate' => $this->rate((int) $row['submit_clicked_not_attempted'], $sessions),
                'submit_attempted_not_created_rate' => $this->rate((int) $row['submit_attempted_not_created'], $sessions),
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
            $sessions = (int) $row['sessions_total'];

            return [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'campaign_code' => $row['campaign_code'] ?? '',
                'campaign_id' => $row['campaign_id'] ?? '',
                'campaign_name' => $row['campaign_name'] ?? '',
                'sessions_total' => $sessions,
                'reached_viewed' => (int) $row['reached_viewed'],
                'reached_started' => (int) $row['reached_started'],
                'reached_submit_clicked' => (int) $row['reached_submit_clicked'],
                'reached_submit_attempted' => (int) $row['reached_submit_attempted'],
                'reached_created' => (int) $row['reached_created'],
                'viewed_not_started' => (int) $row['viewed_not_started'],
                'started_not_submit_clicked' => (int) $row['started_not_submit_clicked'],
                'submit_clicked_not_attempted' => (int) $row['submit_clicked_not_attempted'],
                'submit_attempted_not_created' => (int) $row['submit_attempted_not_created'],
                'converted' => (int) $row['converted'],
                'abandoned_total' => (int) $row['abandoned_total'],
                'conversion_rate' => $this->rate((int) $row['converted'], $sessions),
                'viewed_not_started_rate' => $this->rate((int) $row['viewed_not_started'], $sessions),
                'started_not_submit_clicked_rate' => $this->rate((int) $row['started_not_submit_clicked'], $sessions),
                'submit_clicked_not_attempted_rate' => $this->rate((int) $row['submit_clicked_not_attempted'], $sessions),
                'submit_attempted_not_created_rate' => $this->rate((int) $row['submit_attempted_not_created'], $sessions),
            ];
        }, $campaigns);
    }

    /**
     * Rate jako ułamek dziesiętny z kropką (np. 0.2375). Pusty string przy braku sesji
     * (bez dzielenia przez zero) — czytelne dla arkusza/AI.
     */
    private function rate(int $numerator, int $denominator): string
    {
        if ($denominator <= 0) {
            return '';
        }

        return (string) round($numerator / $denominator, self::RATE_PRECISION);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filename(string $type, array $filters): string
    {
        return sprintf(
            'pne-form-abandonments-%s-%s_%s.csv',
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

            // BOM UTF-8 — zgodnie ze standardem CSV w pneadm (poprawne polskie znaki w Excelu).
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
