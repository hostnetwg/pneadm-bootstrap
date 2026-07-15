<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsLiveVisitorsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardLiveVisitorsController extends Controller
{
    public function __invoke(AnalyticsLiveVisitorsService $liveVisitors): JsonResponse
    {
        if (! config('analytics.live_visitors_dashboard.enabled', true)) {
            abort(404);
        }

        $ttl = max(0, (int) config('analytics.live_visitors_dashboard.response_cache_seconds', 15));

        if ($ttl <= 0) {
            return response()->json($liveVisitors->snapshot());
        }

        $payload = Cache::remember(
            'dashboard.live_visitors.snapshot.v1',
            $ttl,
            fn () => $liveVisitors->snapshot()
        );

        return response()->json($payload);
    }
}
