<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsFormAbandonmentCsvExportService;
use App\Services\Analytics\AnalyticsFormAbandonmentDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsFormAbandonmentController extends Controller
{
    /** Filtry współdzielone przez dashboard i eksporty CSV. */
    private const FILTER_KEYS = ['date_from', 'date_to', 'course_id', 'campaign_code'];

    /**
     * Dashboard porzuceń formularza (Etap B4). READ-ONLY.
     * Czyta wyłącznie dzienne agregaty B3 (bez skanowania analytics_events).
     */
    public function index(Request $request, AnalyticsFormAbandonmentDashboardService $dashboard): View
    {
        $this->ensureEnabled();

        $data = $dashboard->build($request->only(self::FILTER_KEYS));

        return view('analytics.form-abandonments.index', $data);
    }

    /**
     * Eksport CSV "AI-safe" per kurs (Etap B5). Te same filtry co dashboard.
     */
    public function exportCourses(Request $request, AnalyticsFormAbandonmentCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamCourses($request->only(self::FILTER_KEYS));
    }

    /**
     * Eksport CSV "AI-safe" per kampania (Etap B5). Te same filtry co dashboard.
     */
    public function exportCampaigns(Request $request, AnalyticsFormAbandonmentCsvExportService $export): StreamedResponse
    {
        $this->ensureEnabled();

        return $export->streamCampaigns($request->only(self::FILTER_KEYS));
    }

    private function ensureEnabled(): void
    {
        if (! config('analytics.form_abandonment_dashboard.enabled', true)) {
            abort(404);
        }
    }
}
