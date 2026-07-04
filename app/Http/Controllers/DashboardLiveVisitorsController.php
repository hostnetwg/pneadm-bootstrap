<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsLiveVisitorsService;
use Illuminate\Http\JsonResponse;

class DashboardLiveVisitorsController extends Controller
{
    public function __invoke(AnalyticsLiveVisitorsService $liveVisitors): JsonResponse
    {
        if (! config('analytics.live_visitors_dashboard.enabled', true)) {
            abort(404);
        }

        return response()->json($liveVisitors->snapshot());
    }
}
