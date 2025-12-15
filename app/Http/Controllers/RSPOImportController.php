<?php

namespace App\Http\Controllers;

use App\Services\RSPOImportService;
use App\Services\SendyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RSPOImportController extends Controller
{
    private RSPOImportService $rspoService;
    private SendyService $sendyService;
    private const DEFAULT_BRAND_ID = 4;

    public function __construct(RSPOImportService $rspoService, SendyService $sendyService)
    {
        $this->rspoService = $rspoService;
        $this->sendyService = $sendyService;
    }

    public function index(): View
    {
        // Pobierz typy placówek
        $schoolTypes = $this->rspoService->getSchoolTypes();
        
        // Dla każdego typu pobierz liczbę placówek (z cache)
        $typesWithCounts = [];
        foreach ($schoolTypes as $type) {
            $typeId = $type["id"] ?? null;
            if ($typeId) {
                $count = cache()->get("rspo_type_count_{$typeId}");
                $typesWithCounts[] = [
                    "id" => $typeId,
                    "nazwa" => $type["nazwa"] ?? "Brak nazwy",
                    "count" => $count
                ];
            }
        }

        // Pobierz istniejące listy z Sendy
        $brandId = self::DEFAULT_BRAND_ID;
        $sendyLists = $this->sendyService->getLists($brandId);

        return view("rspo.import.index", compact("typesWithCounts", "sendyLists", "brandId"));
    }

    public function import(Request $request): RedirectResponse
    {
        // Zwiększ limit czasu wykonania dla dużych importów (10 minut)
        set_time_limit(600);
        ini_set("max_execution_time", 600);

        $request->validate([
            "list_id" => "required|string",
            "type_ids" => "required|array|min:1",
            "type_ids.*" => "integer",
        ]);

        try {
            // Pobierz szkoły z zaznaczonych typów
            $allSchools = [];
            $selectedTypeIds = $request->input("type_ids", []);

            foreach ($selectedTypeIds as $typeId) {
                $filters = ["typ_podmiotu_id" => $typeId];
                $schools = $this->rspoService->fetchSchools($filters);
                $allSchools = array_merge($allSchools, $schools);
            }

            if (empty($allSchools)) {
                return back()->withErrors([
                    "import" => "Nie znaleziono szkół spełniających wybrane kryteria."
                ])->withInput();
            }

            // Pobierz ID listy z formularza
            $listId = $request->input("list_id");

            // Pobierz nazwę listy dla wyświetlenia wyników
            $brandId = self::DEFAULT_BRAND_ID;
            $sendyLists = $this->sendyService->getLists($brandId);
            $listName = "Nieznana lista";
            foreach ($sendyLists as $list) {
                if (($list["id"] ?? null) == $listId) {
                    $listName = $list["name"] ?? "Nieznana lista";
                    break;
                }
            }

            // Dodaj wszystkie szkoły do listy
            // Loguj rozpoczęcie importu
            Log::info("RSPO Import Started", [
                "list_id" => $listId,
                "list_name" => $listName,
                "total_schools" => count($allSchools),
                "selected_types" => $selectedTypeIds
            ]);
            
            $bulkResult = $this->sendyService->bulkSubscribe($listId, $allSchools);
            
            // Loguj zakończenie importu
            Log::info("RSPO Import Completed", [
                "list_id" => $listId,
                "success" => $bulkResult["success"],
                "failed" => $bulkResult["failed"]
            ]);

            // Zapisz wyniki w sesji
            session()->flash("import_results", [
                "list_id" => $listId,
                "list_name" => $listName,
                "subscribers_added" => $bulkResult["success"],
                "subscribers_failed" => $bulkResult["failed"],
                "total_schools" => count($allSchools),
                "errors" => $bulkResult["errors"] ?? []
            ]);

            return redirect()->route("rspo.import.index")
                ->with("success", "Import zakończony pomyślnie!");

        } catch (\Exception $e) {
            Log::error("RSPO Import Error", [
                "message" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
                "request" => $request->all()
            ]);

            return back()->withErrors([
                "import" => "Wystąpił błąd podczas importu: " . $e->getMessage()
            ])->withInput();
        }
    }
}