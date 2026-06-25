<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsFormAbandonmentDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AnalyticsFormAbandonmentController extends Controller
{
    /**
     * Dashboard porzuceń formularza (Etap B4). READ-ONLY.
     * Czyta wyłącznie dzienne agregaty B3 (bez skanowania analytics_events).
     */
    public function index(Request $request, AnalyticsFormAbandonmentDashboardService $dashboard): View
    {
        if (! config('analytics.form_abandonment_dashboard.enabled', true)) {
            abort(404);
        }

        $data = $dashboard->build($request->only([
            'date_from',
            'date_to',
            'course_id',
            'campaign_code',
        ]));

        return view('analytics.form-abandonments.index', $data);
    }
}
