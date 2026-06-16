<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\MarketingSourceType;
use App\Services\CourseFunnelStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingFunnelController extends Controller
{
    public function index(Request $request, CourseFunnelStatsService $statsService)
    {
        $days = max(1, (int) $request->get('days', $statsService->defaultStatsDays()));
        $to = Carbon::now()->endOfDay();
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        if ($request->filled('date_from')) {
            $from = Carbon::parse($request->get('date_from'))->startOfDay();
        }
        if ($request->filled('date_to')) {
            $to = Carbon::parse($request->get('date_to'))->endOfDay();
        }

        $sourceTypeId = $request->filled('source_type_id') ? (int) $request->get('source_type_id') : null;

        $courseQuery = Course::query()
            ->where('is_paid', true)
            ->orderByDesc('start_date')
            ->limit(100);

        if ($request->filled('course_id')) {
            $courseQuery->whereKey((int) $request->get('course_id'));
        }

        $courses = $courseQuery->get(['id', 'title', 'start_date']);
        $courseIds = $courses->pluck('id')->all();
        $funnelByCourse = $statsService->statsForCourses($courseIds, $from, $to);
        $campaignRows = $statsService->funnelByCampaign($from, $to, $sourceTypeId);

        $totals = [
            'views_course_show' => 0,
            'views_order_form' => 0,
            'orders_submitted' => 0,
            'orders_invoiced' => 0,
            'orders_paid' => 0,
        ];
        foreach ($funnelByCourse as $row) {
            $totals['views_course_show'] += $row['views_course_show'];
            $totals['views_order_form'] += $row['views_order_form'];
            $totals['orders_submitted'] += $row['orders_submitted'];
            $totals['orders_invoiced'] += $row['orders_invoiced'];
            $totals['orders_paid'] += $row['orders_invoiced'];
        }

        $sourceTypes = MarketingSourceType::active()->ordered()->get();
        $allCourses = Course::query()
            ->where('is_paid', true)
            ->orderByDesc('start_date')
            ->limit(300)
            ->get(['id', 'title', 'start_date']);

        $ordersWithoutCampaign = (int) DB::table('form_orders')
            ->whereNull('deleted_at')
            ->whereBetween('order_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('fb_source')->orWhere('fb_source', '');
            })
            ->count();

        return view('marketing-funnel.index', compact(
            'courses',
            'funnelByCourse',
            'campaignRows',
            'totals',
            'from',
            'to',
            'days',
            'sourceTypes',
            'sourceTypeId',
            'allCourses',
            'ordersWithoutCampaign',
            'statsService',
        ));
    }
}
