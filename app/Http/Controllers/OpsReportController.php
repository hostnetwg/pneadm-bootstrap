<?php

namespace App\Http\Controllers;

use App\Models\OpsRun;
use App\Services\Ops\OpsRunRecorder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpsReportController extends Controller
{
    public function index(Request $request): View
    {
        $type = (string) $request->get('type', '');
        $status = (string) $request->get('status', '');
        $dateFrom = (string) $request->get('date_from', '');
        $dateTo = (string) $request->get('date_to', '');

        $query = OpsRun::query()->withCount('items')->orderByDesc('started_at')->orderByDesc('id');

        if ($type !== '' && in_array($type, OpsRun::knownTypes(), true)) {
            $query->where('type', $type);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($dateFrom !== '') {
            $query->whereDate('period_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('period_date', '<=', $dateTo);
        }

        $runs = $query->paginate(25)->withQueryString();

        return view('ops-reports.index', [
            'runs' => $runs,
            'type' => $type,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function show(OpsRun $opsRun, OpsRunRecorder $recorder): View
    {
        $opsRun->load(['items.subject', 'actor']);

        return view('ops-reports.show', [
            'run' => $opsRun,
            'recorder' => $recorder,
        ]);
    }
}
