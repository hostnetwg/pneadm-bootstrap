<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsSalesFunnelDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AnalyticsSalesFunnelController extends Controller
{
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
}
