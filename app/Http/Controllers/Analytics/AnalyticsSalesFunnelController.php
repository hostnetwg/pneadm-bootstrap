<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Analytics\AnalyticsDailyAggregationService;
use App\Services\Analytics\AnalyticsSalesFunnelDashboardService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class AnalyticsSalesFunnelController extends Controller
{
    /**
     * Domyślny limit zakresu dni dla ręcznego przeliczenia z panelu
     * (gdyby brakowało configu). Faktyczna wartość: config recompute_max_days.
     */
    private const DEFAULT_MAX_RECOMPUTE_DAYS = 366;

    public function index(Request $request, AnalyticsSalesFunnelDashboardService $dashboard): View
    {
        if (! config('analytics.sales_funnel_dashboard.enabled', true)) {
            abort(404);
        }

        $data = $dashboard->build($request->only([
            'date_from',
            'date_to',
            'campaign_code',
            'course_id',
            'landing_target',
            'sort',
        ]));

        return view('analytics.sales-funnel.index', $data);
    }

    /**
     * Ręczne przeliczenie agregatów dziennych dla widocznego zakresu dat.
     * Idempotentne (serwis kasuje i liczy od zera per dzień), admin-only (middleware grupy).
     */
    public function recompute(
        Request $request,
        AnalyticsSalesFunnelDashboardService $dashboard,
        AnalyticsDailyAggregationService $aggregation,
    ): RedirectResponse {
        if (! config('analytics.sales_funnel_dashboard.enabled', true)) {
            abort(404);
        }

        $timezone = $aggregation->timezone();
        $today = Carbon::now($timezone)->startOfDay();
        $defaultFrom = $today->copy()->subDays(max(1, $dashboard->defaultDays()) - 1);

        $from = filled($request->input('date_from'))
            ? Carbon::parse((string) $request->input('date_from'), $timezone)->startOfDay()
            : $defaultFrom;

        $to = filled($request->input('date_to'))
            ? Carbon::parse((string) $request->input('date_to'), $timezone)->startOfDay()
            : $today;

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $redirect = redirect()->route('analytics.sales-funnel.index', $this->preservedFilters($request));

        $maxDays = max(1, (int) config('analytics.sales_funnel_dashboard.recompute_max_days', self::DEFAULT_MAX_RECOMPUTE_DAYS));

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
            'Przeliczono agregaty: dni %d, wiersze kursów %d, wiersze kampanii %d (%s – %s).',
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
            'campaign_code' => (string) $request->input('campaign_code', ''),
            'course_id' => (string) $request->input('course_id', ''),
            'landing_target' => (string) $request->input('landing_target', ''),
            'sort' => (string) $request->input('sort', ''),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @param  array{course_rows: int, campaign_rows: int, dates: list<string>}  $result
     */
    private function logRecomputeSafely(string $from, string $to, array $result): void
    {
        try {
            ActivityLog::logCustom(
                'analytics_aggregates_recomputed',
                'Ręczne przeliczenie agregatów lejka sprzedaży z panelu.',
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
}
