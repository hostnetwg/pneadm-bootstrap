<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsDateRangePresets;
use App\Services\Analytics\AnalyticsRevenueDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AnalyticsRevenueController extends Controller
{
    /** Filtry współdzielone przez dashboard (i przyszłe eksporty CSV w R3). */
    private const FILTER_KEYS = ['date_from', 'date_to', 'course_id', 'campaign_code'];

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

    private function ensureEnabled(): void
    {
        if (! config('analytics.revenue_dashboard.enabled', true)) {
            abort(404);
        }
    }
}
