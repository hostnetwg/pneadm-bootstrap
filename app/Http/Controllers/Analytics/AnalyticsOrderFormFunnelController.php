<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsDateRangePresets;
use App\Services\Analytics\OrderFormFunnelAggregationService;
use App\Services\Analytics\OrderFormFunnelCsvExportService;
use App\Services\Analytics\OrderFormFunnelDashboardService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AnalyticsOrderFormFunnelController extends Controller
{
    private const FILTER_KEYS = [
        'date_from',
        'date_to',
        'traffic_channel',
        'course_id',
        'campaign_code',
        'internal_promo_placement',
    ];

    public function index(
        Request $request,
        OrderFormFunnelDashboardService $dashboard,
        AnalyticsDateRangePresets $presets,
    ): View {
        $this->ensureEnabled();

        $data = $dashboard->build($request->only(self::FILTER_KEYS));
        $data['date_presets'] = $presets->build($dashboard->timezone(), $dashboard->aggregationLagDays());

        return view('analytics.order-form-funnels.index', $data);
    }

    public function exportChannels(Request $request, OrderFormFunnelCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamChannels($request->only(self::FILTER_KEYS));
    }

    public function exportCourses(Request $request, OrderFormFunnelCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamCourses($request->only(self::FILTER_KEYS));
    }

    public function exportCampaigns(Request $request, OrderFormFunnelCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamCampaigns($request->only(self::FILTER_KEYS));
    }

    public function exportGus(Request $request, OrderFormFunnelCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamGus($request->only(self::FILTER_KEYS));
    }

    public function exportDataQuality(Request $request, OrderFormFunnelCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamDataQuality($request->only(self::FILTER_KEYS));
    }

    public function recompute(
        Request $request,
        OrderFormFunnelDashboardService $dashboard,
        OrderFormFunnelAggregationService $aggregation,
    ): RedirectResponse {
        $this->ensureEnabled();

        $filters = $dashboard->resolveFilters($request->only(self::FILTER_KEYS));
        $maxDays = max(1, (int) config('analytics.order_form_funnel_dashboard.recompute_max_days', 92));
        $from = Carbon::parse($filters['date_from'], $dashboard->timezone());
        $to = Carbon::parse($filters['date_to'], $dashboard->timezone());

        if ($from->diffInDays($to) + 1 > $maxDays) {
            return redirect()
                ->route('analytics.order-form-funnels.index', $this->preservedFilters($request))
                ->with('error', "Zakres przekracza limit ręcznego przeliczenia ({$maxDays} dni). Użyj komendy konsolowej.");
        }

        try {
            $aggregation->aggregateForDateRange($from, $to);
        } catch (Throwable $exception) {
            return redirect()
                ->route('analytics.order-form-funnels.index', $this->preservedFilters($request))
                ->with('error', 'Nie udało się przeliczyć agregatów: '.$exception->getMessage());
        }

        return redirect()
            ->route('analytics.order-form-funnels.index', $this->preservedFilters($request))
            ->with('success', 'Agregaty B4 zostały przeliczone dla wybranego zakresu dat.');
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('analytics.order_form_funnel_dashboard.enabled', true), 404);
    }

  /**
     * @return array<string, mixed>
     */
    private function preservedFilters(Request $request): array
    {
        return array_filter($request->only(self::FILTER_KEYS), fn ($value): bool => filled($value));
    }
}
