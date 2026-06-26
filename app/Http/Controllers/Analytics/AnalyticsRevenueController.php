<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Analytics\AnalyticsDateRangePresets;
use App\Services\Analytics\AnalyticsRevenueAggregationService;
use App\Services\Analytics\AnalyticsRevenueCsvExportService;
use App\Services\Analytics\AnalyticsRevenueDashboardService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AnalyticsRevenueController extends Controller
{
    /** Filtry współdzielone przez dashboard (i przyszłe eksporty CSV w R3). */
    private const FILTER_KEYS = ['date_from', 'date_to', 'course_id', 'campaign_code'];

    /** Domyślny limit zakresu dni dla ręcznego przeliczenia (gdyby brakowało configu). */
    private const DEFAULT_MAX_RECOMPUTE_DAYS = 92;

    /**
     * Dashboard rozliczeń (Etap R2). READ-ONLY.
     * Czyta wyłącznie dzienne agregaty R1 (bez skanowania analytics_events).
     */
    public function index(
        Request $request,
        AnalyticsRevenueDashboardService $dashboard,
        AnalyticsDateRangePresets $presets,
    ): View {
        $this->ensureEnabled();

        $data = $dashboard->build($request->only(self::FILTER_KEYS));
        $data['date_presets'] = $presets->build($dashboard->timezone(), $dashboard->aggregationLagDays());

        return view('analytics.revenue.index', $data);
    }

    /**
     * Eksport CSV "AI-safe" per kurs (Etap R3). Te same filtry co dashboard.
     */
    public function exportCourses(Request $request, AnalyticsRevenueCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamCourses($request->only(self::FILTER_KEYS));
    }

    /**
     * Eksport CSV "AI-safe" per kampania (Etap R3). Te same filtry co dashboard.
     */
    public function exportCampaigns(Request $request, AnalyticsRevenueCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamCampaigns($request->only(self::FILTER_KEYS));
    }

    /**
     * Eksport CSV "AI-safe" dziennego trendu (Etap R3) — jeden wiersz na stat_date.
     */
    public function exportDaily(Request $request, AnalyticsRevenueCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamDaily($request->only(self::FILTER_KEYS));
    }

    /**
     * Ręczne przeliczenie agregatów rozliczeń (R1) dla widocznego zakresu dat.
     * Idempotentne (serwis kasuje i liczy od zera per dzień), admin-only (middleware grupy).
     */
    public function recompute(
        Request $request,
        AnalyticsRevenueDashboardService $dashboard,
        AnalyticsRevenueAggregationService $aggregation,
    ): RedirectResponse {
        $this->ensureEnabled();

        $timezone = $aggregation->timezone();
        $lag = max(0, (int) config('analytics.revenue.aggregation_lag_days', 1));
        $defaultTo = Carbon::now($timezone)->startOfDay()->subDays($lag);
        $defaultFrom = $defaultTo->copy()->subDays(max(1, $dashboard->defaultDays()) - 1);

        $from = filled($request->input('date_from'))
            ? Carbon::parse((string) $request->input('date_from'), $timezone)->startOfDay()
            : $defaultFrom;

        $to = filled($request->input('date_to'))
            ? Carbon::parse((string) $request->input('date_to'), $timezone)->startOfDay()
            : $defaultTo;

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $redirect = redirect()->route('analytics.revenue.index', $this->preservedFilters($request));

        $maxDays = max(1, (int) config('analytics.revenue_dashboard.recompute_max_days', self::DEFAULT_MAX_RECOMPUTE_DAYS));

        if ($from->diffInDays($to) + 1 > $maxDays) {
            return $redirect->with(
                'recompute_error',
                sprintf('Zakres jest zbyt duży (max %d dni). Zawęź daty lub użyj komendy w konsoli.', $maxDays)
            );
        }

        try {
            $result = $aggregation->aggregateForDateRange($from, $to);
        } catch (Throwable) {
            return $redirect->with('recompute_error', 'Przeliczenie nie powiodło się. Spróbuj ponownie lub użyj komendy w konsoli.');
        }

        $this->logRecomputeSafely($from->toDateString(), $to->toDateString(), $result);

        return $redirect->with('recompute_status', sprintf(
            'Przeliczono rozliczenia: dni %d, wiersze kursów %d, wiersze kampanii %d (%s – %s).',
            count($result['dates']),
            $result['course_rows'],
            $result['campaign_rows'],
            $from->toDateString(),
            $to->toDateString(),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function preservedFilters(Request $request): array
    {
        return array_filter([
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
            'course_id' => (string) $request->input('course_id', ''),
            'campaign_code' => (string) $request->input('campaign_code', ''),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @param  array{course_rows: int, campaign_rows: int, dates: list<string>}  $result
     */
    private function logRecomputeSafely(string $from, string $to, array $result): void
    {
        try {
            ActivityLog::logCustom(
                'analytics_revenue_recomputed',
                'Ręczne przeliczenie agregatów rozliczeń z panelu.',
                [
                    'date_from' => $from,
                    'date_to' => $to,
                    'days' => count($result['dates']),
                    'course_rows' => $result['course_rows'],
                    'campaign_rows' => $result['campaign_rows'],
                ],
            );
        } catch (Throwable) {
            // Audit log nie może blokować przeliczenia ani odpowiedzi.
        }
    }

    private function ensureEnabled(): void
    {
        if (! config('analytics.revenue_dashboard.enabled', true)) {
            abort(404);
        }
    }
}
