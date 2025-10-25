<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogController extends Controller
{
    /**
     * Wyświetla listę wszystkich logów aktywności
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        $logType = $request->get('log_type', '');
        $userId = $request->get('user_id', '');
        $modelType = $request->get('model_type', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        // Budujemy zapytanie
        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        // Filtr: wyszukiwanie
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('action', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('model_name', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filtr: typ akcji
        if (!empty($logType)) {
            $query->where('log_type', $logType);
        }

        // Filtr: użytkownik
        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        // Filtr: typ modelu
        if (!empty($modelType)) {
            $query->where('model_type', 'LIKE', "%{$modelType}%");
        }

        // Filtr: zakres dat
        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Paginacja
        if ($perPage === 'all') {
            $logs = $query->get();
            $logs = new \Illuminate\Pagination\LengthAwarePaginator(
                $logs,
                $logs->count(),
                $logs->count(),
                1,
                ['path' => request()->url(), 'pageName' => 'page']
            );
        } else {
            $logs = $query->paginate($perPage);
        }

        // Lista użytkowników dla filtra
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        // Typy modeli dla filtra
        $modelTypes = ActivityLog::select('model_type')
            ->whereNotNull('model_type')
            ->groupBy('model_type')
            ->pluck('model_type')
            ->map(function($type) {
                return [
                    'value' => $type,
                    'label' => class_basename($type)
                ];
            });

        return view('activity-logs.index', compact(
            'logs',
            'perPage',
            'search',
            'logType',
            'userId',
            'modelType',
            'dateFrom',
            'dateTo',
            'users',
            'modelTypes'
        ));
    }

    /**
     * Wyświetla szczegóły pojedynczego logu
     */
    public function show($id)
    {
        $log = ActivityLog::with('user')->findOrFail($id);

        // Znajdź poprzedni i następny log
        $prevLog = ActivityLog::where('id', '<', $id)->orderBy('id', 'desc')->first();
        $nextLog = ActivityLog::where('id', '>', $id)->orderBy('id', 'asc')->first();

        return view('activity-logs.show', compact('log', 'prevLog', 'nextLog'));
    }

    /**
     * Wyświetla logi dla konkretnego użytkownika
     */
    public function userLogs(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $perPage = $request->get('per_page', 25);
        $logType = $request->get('log_type', '');
        $search = $request->get('search', '');

        $query = ActivityLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if (!empty($logType)) {
            $query->where('log_type', $logType);
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('action', 'LIKE', "%{$search}%")
                  ->orWhere('model_name', 'LIKE', "%{$search}%");
            });
        }

        $logs = $query->paginate($perPage);

        return view('activity-logs.user-logs', compact('user', 'logs', 'perPage', 'logType', 'search'));
    }

    /**
     * Wyświetla logi dla konkretnego modelu
     */
    public function modelLogs(Request $request, $modelType, $modelId)
    {
        $perPage = $request->get('per_page', 25);
        $logType = $request->get('log_type', '');
        $search = $request->get('search', '');

        $query = ActivityLog::with('user')
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->orderBy('created_at', 'desc');

        if (!empty($logType)) {
            $query->where('log_type', $logType);
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('action', 'LIKE', "%{$search}%")
                  ->orWhere('model_name', 'LIKE', "%{$search}%");
            });
        }

        $logs = $query->paginate($perPage);

        // Pobierz nazwę modelu (pierwszy log)
        $modelName = $logs->first()->model_name ?? 'Nieznany';
        
        // Skrócona nazwa modelu dla wyświetlania
        $modelTypeShort = class_basename($modelType);

        return view('activity-logs.model-logs', compact('logs', 'perPage', 'modelType', 'modelId', 'modelName', 'modelTypeShort', 'logType', 'search'));
    }

    /**
     * Eksportuje logi do CSV
     */
    public function export(Request $request)
    {
        $logType = $request->get('log_type', '');
        $userId = $request->get('user_id', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        if (!empty($logType)) {
            $query->where('log_type', $logType);
        }
        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }
        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->get();

        $filename = 'activity_logs_' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // BOM dla UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Nagłówki
            fputcsv($file, [
                'ID',
                'Data',
                'Użytkownik',
                'Typ akcji',
                'Akcja',
                'Model',
                'Nazwa rekordu',
                'IP',
                'URL'
            ]);

            // Dane
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user ? $log->user->name : 'System',
                    $log->log_type_name,
                    $log->action,
                    $log->model_type_short ?? '-',
                    $log->model_name ?? '-',
                    $log->ip_address ?? '-',
                    $log->url ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Statystyki aktywności
     */
    public function statistics(Request $request)
    {
        $period = $request->get('period', '7'); // domyślnie 7 dni

        $dateFrom = now()->subDays($period);

        // Ogólne statystyki
        $totalLogs = ActivityLog::where('created_at', '>=', $dateFrom)->count();
        
        // Statystyki według typu akcji
        $logsByType = ActivityLog::where('created_at', '>=', $dateFrom)
            ->select('log_type', \DB::raw('count(*) as count'))
            ->groupBy('log_type')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->log_type => $item->count];
            });

        // Najbardziej aktywni użytkownicy
        $topUsers = ActivityLog::where('created_at', '>=', $dateFrom)
            ->select('user_id', \DB::raw('count(*) as count'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->with('user')
            ->get();

        // Aktywność według dni
        $activityByDay = ActivityLog::where('created_at', '>=', $dateFrom)
            ->select(\DB::raw('DATE(created_at) as date'), \DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Najpopularniejsze modele
        $topModels = ActivityLog::where('created_at', '>=', $dateFrom)
            ->whereNotNull('model_type')
            ->select('model_type', \DB::raw('count(*) as count'))
            ->groupBy('model_type')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'model' => class_basename($item->model_type),
                    'count' => $item->count
                ];
            });

        return view('activity-logs.statistics', compact(
            'totalLogs',
            'logsByType',
            'topUsers',
            'activityByDay',
            'topModels',
            'period'
        ));
    }
}
