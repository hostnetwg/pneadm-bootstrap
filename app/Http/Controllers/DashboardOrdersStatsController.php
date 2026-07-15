<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardOrdersDashboardService;
use App\Services\Dashboard\DashboardOrdersStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardOrdersStatsController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardOrdersStatsService $stats,
        DashboardOrdersDashboardService $dashboard,
    ): JsonResponse {
        if ($request->boolean('sections')) {
            return response()->json($dashboard->snapshotWithSections($request));
        }

        $payload = $stats->snapshot();
        // Zawsze dołącz aktualną tabelę „Ostatnie zamówienia” (kolor badge + ✓ przetworzone),
        // niezależnie od tego, czy zmieniły się liczniki headline.
        $payload['recent_orders'] = $dashboard->recentOrdersPayload();

        return response()->json($payload);
    }
}
