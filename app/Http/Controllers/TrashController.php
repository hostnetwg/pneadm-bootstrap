<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Instructor;
use App\Models\Course;
use App\Models\Participant;
use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\CourseLocation;
use App\Models\CourseOnlineDetails;

class TrashController extends Controller
{
    /**
     * Wyświetla listę wszystkich usuniętych rekordów (kosz)
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $table = $request->get('table', 'all');
        $perPage = $request->get('per_page', 25);

        $trashData = [];

        // Funkcja pomocnicza do pobierania usuniętych rekordów
        $getTrashedRecords = function($modelClass, $tableName, $displayFields) use ($search) {
            $query = $modelClass::onlyTrashed();
            
            if ($search) {
                foreach ($displayFields as $field) {
                    $query->orWhere($field, 'LIKE', "%{$search}%");
                }
            }
            
            return $query->orderBy('deleted_at', 'desc')->get()->map(function($record) use ($tableName, $displayFields) {
                return [
                    'id' => $record->id,
                    'table' => $tableName,
                    'model_class' => get_class($record),
                    'deleted_at' => $record->deleted_at,
                    'display_data' => $this->getDisplayData($record, $displayFields),
                    'record' => $record
                ];
            });
        };

        // Definicje tabel i pól do wyświetlania
        $tableDefinitions = [
            'users' => [
                'model' => User::class,
                'display_fields' => ['name', 'email'],
                'label' => 'Użytkownicy'
            ],
            'instructors' => [
                'model' => Instructor::class,
                'display_fields' => ['first_name', 'last_name', 'email'],
                'label' => 'Instruktorzy'
            ],
            'courses' => [
                'model' => Course::class,
                'display_fields' => ['title', 'description'],
                'label' => 'Kursy'
            ],
            'participants' => [
                'model' => Participant::class,
                'display_fields' => ['first_name', 'last_name', 'email'],
                'label' => 'Uczestnicy'
            ],
            'form_orders' => [
                'model' => FormOrder::class,
                'display_fields' => ['participant_name', 'product_name', 'orderer_name'],
                'label' => 'Zamówienia formularzy'
            ],
            'form_order_participants' => [
                'model' => FormOrderParticipant::class,
                'display_fields' => ['participant_firstname', 'participant_lastname', 'participant_email'],
                'label' => 'Uczestnicy zamówień'
            ],
            'course_locations' => [
                'model' => CourseLocation::class,
                'display_fields' => ['location_name', 'address', 'post_office'],
                'label' => 'Lokalizacje kursów'
            ],
            'course_online_details' => [
                'model' => CourseOnlineDetails::class,
                'display_fields' => ['platform', 'meeting_link'],
                'label' => 'Szczegóły kursów online'
            ]
        ];

        // Pobieranie danych dla wybranej tabeli lub wszystkich
        if ($table === 'all') {
            foreach ($tableDefinitions as $tableName => $definition) {
                $trashData = array_merge($trashData, $getTrashedRecords(
                    $definition['model'], 
                    $tableName, 
                    $definition['display_fields']
                )->toArray());
            }
        } else {
            if (isset($tableDefinitions[$table])) {
                $definition = $tableDefinitions[$table];
                $trashData = $getTrashedRecords(
                    $definition['model'], 
                    $table, 
                    $definition['display_fields']
                )->toArray();
            }
        }

        // Sortowanie po dacie usunięcia (najnowsze pierwsze)
        usort($trashData, function($a, $b) {
            return $b['deleted_at'] <=> $a['deleted_at'];
        });

        // Paginacja
        $total = count($trashData);
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedData = array_slice($trashData, $offset, $perPage);

        $trashRecords = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedData,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );

        return view('trash.index', compact('trashRecords', 'search', 'table', 'perPage', 'tableDefinitions'));
    }

    /**
     * Przywraca usunięty rekord
     */
    public function restore(Request $request, $table, $id)
    {
        try {
            $modelClass = $this->getModelClass($table);
            
            if (!$modelClass) {
                return redirect()->back()->with('error', 'Nieprawidłowy typ rekordu.');
            }

            $record = $modelClass::onlyTrashed()->findOrFail($id);
            $record->restore();

            return redirect()->back()->with('success', 'Rekord został przywrócony pomyślnie!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Wystąpił błąd podczas przywracania rekordu: ' . $e->getMessage());
        }
    }

    /**
     * Usuwa rekord trwale
     */
    public function forceDelete(Request $request, $table, $id)
    {
        try {
            $modelClass = $this->getModelClass($table);
            
            if (!$modelClass) {
                return redirect()->back()->with('error', 'Nieprawidłowy typ rekordu.');
            }

            $record = $modelClass::onlyTrashed()->findOrFail($id);
            $record->forceDelete();

            return redirect()->back()->with('success', 'Rekord został trwale usunięty!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Wystąpił błąd podczas trwałego usuwania rekordu: ' . $e->getMessage());
        }
    }

    /**
     * Opróżnia kosz dla konkretnej tabeli
     */
    public function emptyTable(Request $request, $table)
    {
        try {
            $modelClass = $this->getModelClass($table);
            
            if (!$modelClass) {
                return redirect()->back()->with('error', 'Nieprawidłowy typ tabeli.');
            }

            $count = $modelClass::onlyTrashed()->count();
            $modelClass::onlyTrashed()->forceDelete();

            return redirect()->back()->with('success', "Kosz dla tabeli '{$table}' został opróżniony. Usunięto {$count} rekordów.");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Wystąpił błąd podczas opróżniania kosza: ' . $e->getMessage());
        }
    }

    /**
     * Opróżnia cały kosz
     */
    public function emptyAll(Request $request)
    {
        try {
            $totalDeleted = 0;
            $tables = [
                'users' => User::class,
                'instructors' => Instructor::class,
                'courses' => Course::class,
                'participants' => Participant::class,
                'form_orders' => FormOrder::class,
                'form_order_participants' => FormOrderParticipant::class,
                'course_locations' => CourseLocation::class,
                'course_online_details' => CourseOnlineDetails::class,
            ];

            foreach ($tables as $tableName => $modelClass) {
                $count = $modelClass::onlyTrashed()->count();
                $modelClass::onlyTrashed()->forceDelete();
                $totalDeleted += $count;
            }

            return redirect()->back()->with('success', "Cały kosz został opróżniony. Usunięto {$totalDeleted} rekordów.");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Wystąpił błąd podczas opróżniania całego kosza: ' . $e->getMessage());
        }
    }

    /**
     * Pobiera klasę modelu na podstawie nazwy tabeli
     */
    private function getModelClass($table)
    {
        $modelMap = [
            'users' => User::class,
            'instructors' => Instructor::class,
            'courses' => Course::class,
            'participants' => Participant::class,
            'form_orders' => FormOrder::class,
            'form_order_participants' => FormOrderParticipant::class,
            'course_locations' => CourseLocation::class,
            'course_online_details' => CourseOnlineDetails::class,
        ];

        return $modelMap[$table] ?? null;
    }

    /**
     * Tworzy dane do wyświetlenia dla rekordu
     */
    private function getDisplayData($record, $displayFields)
    {
        $data = [];
        foreach ($displayFields as $field) {
            if (isset($record->$field)) {
                $data[$field] = $record->$field;
            }
        }
        return $data;
    }
}