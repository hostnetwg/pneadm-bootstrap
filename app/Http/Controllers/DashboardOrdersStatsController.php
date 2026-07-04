<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardOrdersStatsService;
use Illuminate\Http\JsonResponse;

class DashboardOrdersStatsController extends Controller
{
    public function __invoke(DashboardOrdersStatsService $stats): JsonResponse
    {
        return response()->json($stats->snapshot());
    }
}
