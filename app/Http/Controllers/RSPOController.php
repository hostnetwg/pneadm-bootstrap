<?php

namespace App\Http\Controllers;

use App\Services\TerytService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class RSPOController extends Controller
{
    private const API_BASE_URL = 'https://api-rspo.men.gov.pl/api';
    
    private TerytService $terytService;

    public function __construct(TerytService $terytService)
    {
        $this->terytService = $terytService;
    }

    /**
     * Wyświetla stronę wyszukiwarki RSPO
     */
    public function search(Request $request)
    {
        // Pobierz typy podmiotów (z cache na 24h)
        $types = Cache::remember('rspo_types', 86400, function () {
            try {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->get(self::API_BASE_URL . '/typ/');
                
                if ($response->successful()) {
                    $data = $response->json();
                    // API zwraca prostą tablicę obiektów z id i nazwa
                    if (is_array($data) && count($data) > 0) {
                        // Dla każdego typu pobierz liczbę placówek (z cache na 24h)
                        // Używamy równoległych zapytań dla lepszej wydajności
                        foreach ($data as &$type) {
                            $typeId = $type['id'] ?? null;
                            if ($typeId) {
                                $cacheKey = "rspo_type_count_{$typeId}";
                                $count = Cache::get($cacheKey);
                                
                                // Jeśli nie ma cache, spróbuj pobrać
                                if ($count === null) {
                                    // Próba z retry (max 2 próby)
                                    $maxRetries = 2;
                                    $retryDelay = 1; // sekunda
                                    
                                    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                                        try {
                                            // Pobierz tylko informację o całkowitej liczbie (używamy application/ld+json dla hydra:totalItems)
                                            // Używamy itemsPerPage=1 aby zminimalizować ilość danych do pobrania
                                            $countResponse = Http::accept('application/ld+json')
                                                ->timeout(15) // Zwiększony timeout dla typów z dużą liczbą placówek
                                                ->get(self::API_BASE_URL . '/placowki/', [
                                                    'typ_podmiotu_id' => $typeId,
                                                    'page' => 1,
                                                    'itemsPerPage' => 1, // Minimalna ilość danych - potrzebujemy tylko metadanych
                                                ]);
                                            
                                            if ($countResponse->successful()) {
                                                $countData = $countResponse->json();
                                                $totalItems = $countData['hydra:totalItems'] ?? null;
                                                if ($totalItems !== null) {
                                                    $count = (int) $totalItems;
                                                    // Cache'uj sukces na 24h
                                                    Cache::put($cacheKey, $count, 86400);
                                                    break; // Sukces - wyjdź z pętli
                                                }
                                            } elseif ($countResponse->status() >= 500 && $attempt < $maxRetries) {
                                                // Błąd serwera - spróbuj ponownie
                                                \Log::warning("RSPO API Error (type count for {$typeId}, attempt {$attempt}): HTTP {$countResponse->status()}");
                                                sleep($retryDelay);
                                                continue;
                                            }
                                        } catch (\Illuminate\Http\Client\ConnectionException $e) {
                                            // Timeout lub problem z połączeniem - spróbuj ponownie jeśli to nie ostatnia próba
                                            if ($attempt < $maxRetries) {
                                                \Log::warning("RSPO API Connection Error (type count for {$typeId}, attempt {$attempt}): " . $e->getMessage());
                                                sleep($retryDelay);
                                                continue;
                                            } else {
                                                \Log::warning("RSPO API Connection Error (type count for {$typeId}, final attempt): " . $e->getMessage());
                                            }
                                        } catch (\Exception $e) {
                                            // Loguj błąd dla debugowania
                                            \Log::warning("RSPO API Error (type count for {$typeId}, attempt {$attempt}): " . $e->getMessage());
                                            // Nie retry dla innych błędów
                                            break;
                                        }
                                    }
                                    
                                    // Jeśli po wszystkich próbach nie udało się pobrać, cache'uj null na 5 minut
                                    // aby szybko spróbować ponownie przy następnym załadowaniu
                                    if ($count === null) {
                                        Cache::put($cacheKey, null, 300); // 5 minut dla błędów
                                    }
                                }
                                
                                // Zawsze dodaj count (może być null jeśli nie udało się pobrać)
                                $type['count'] = $count;
                            }
                        }
                        unset($type); // Zwolnij referencję
                        
                        // Sortuj alfabetycznie po nazwie
                        usort($data, function($a, $b) {
                            return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                        });
                        return $data;
                    }
                }
            } catch (\Exception $e) {
                \Log::error('RSPO API Error (types): ' . $e->getMessage());
            }
            return [];
        });

        // Parametry wyszukiwania
        $selectedTypeId = $request->get('typ_podmiotu_id');
        $page = $request->get('page', 1);
        $results = null;
        $pagination = null;

        // Wyszukaj placówki (można wyszukiwać wszystkie typy lub konkretny typ)
        // Wyszukiwanie wykonujemy zawsze, gdy formularz został wysłany
        $hasSearchParams = $request->has('typ_podmiotu_id') || 
                          $request->has('wojewodztwo_nazwa') || 
                          $request->has('powiat_nazwa') || 
                          $request->has('miejscowosc_nazwa') || 
                          $page > 1;
        
        if ($hasSearchParams) {
            try {
                // Buduj parametry zapytania
                $params = [
                    'page' => $page,
                ];
                
                // Dodaj parametr typu podmiotu tylko jeśli wybrano konkretny typ
                if ($selectedTypeId) {
                    $params['typ_podmiotu_id'] = $selectedTypeId;
                }
                
                // Dodaj parametry lokalizacji zgodnie z dokumentacją API RSPO
                // API RSPO używa: wojewodztwo, powiat, miejscowosc (bez _nazwa)
                if ($request->has('wojewodztwo_nazwa') && $request->filled('wojewodztwo_nazwa')) {
                    $params['wojewodztwo'] = $request->get('wojewodztwo_nazwa');
                }
                
                if ($request->has('powiat_nazwa') && $request->filled('powiat_nazwa')) {
                    $params['powiat'] = $request->get('powiat_nazwa');
                }
                
                if ($request->has('miejscowosc_nazwa') && $request->filled('miejscowosc_nazwa')) {
                    $params['miejscowosc'] = $request->get('miejscowosc_nazwa');
                }

                // Używamy application/ld+json aby otrzymać format Hydra z informacją o całkowitej liczbie wyników
                $response = Http::accept('application/ld+json')
                    ->timeout(15)
                    ->get(self::API_BASE_URL . '/placowki/', $params);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Obsługa formatu Hydra (JSON-LD) - zawiera informację o całkowitej liczbie wyników
                    if (isset($data['hydra:member'])) {
                        $results = $data['hydra:member'];
                        $totalItems = $data['hydra:totalItems'] ?? count($results);
                        $pagination = [
                            'current_page' => (int) $page,
                            'total_items' => (int) $totalItems,
                            'items_per_page' => count($results),
                            'total_pages' => (int) ceil($totalItems / 100), // API zwraca max 100 wyników na stronę
                            'has_next' => isset($data['hydra:view']['hydra:next']),
                            'has_previous' => isset($data['hydra:view']['hydra:previous']),
                        ];
                    } elseif (is_array($data) && count($data) > 0) {
                        // Fallback: prosta tablica obiektów (gdy API nie zwraca formatu Hydra)
                        $results = $data;
                        $hasNext = count($results) >= 100;
                        $pagination = [
                            'current_page' => (int) $page,
                            'total_items' => count($results), // Nie znamy całkowitej liczby
                            'items_per_page' => count($results),
                            'total_pages' => null,
                            'has_next' => $hasNext,
                            'has_previous' => $page > 1,
                        ];
                    }
                }
            } catch (\Exception $e) {
                \Log::error('RSPO API Error (placowki): ' . $e->getMessage());
                session()->flash('error', 'Błąd podczas pobierania danych z API RSPO: ' . $e->getMessage());
            }
        }

        // Pobierz województwa z TERYT dla formularza
        $wojewodztwa = $this->terytService->getWojewodztwa();
        
        // Pobierz wybrane wartości z requestu
        $selectedWojewodztwo = $request->get('wojewodztwo_nazwa');
        $selectedPowiat = $request->get('powiat_nazwa');
        $selectedMiejscowosc = $request->get('miejscowosc_nazwa');
        
        // Jeśli wybrano województwo, pobierz powiaty
        $powiaty = [];
        if ($selectedWojewodztwo) {
            // Znajdź kod województwa
            foreach ($wojewodztwa as $woj) {
                if (($woj['nazwa'] ?? '') === $selectedWojewodztwo) {
                    $powiaty = $this->terytService->getPowiaty($woj['kod']);
                    break;
                }
            }
        }
        
        // Jeśli wybrano powiat, pobierz miejscowości
        $miejscowosci = [];
        if ($selectedPowiat && !empty($powiaty)) {
            foreach ($powiaty as $pow) {
                if (($pow['nazwa'] ?? '') === $selectedPowiat) {
                    $miejscowosci = $this->terytService->getMiejscowosci(
                        $pow['wojewodztwo_kod'],
                        $pow['kod']
                    );
                    break;
                }
            }
        }

        return view('rspo.search', compact(
            'types', 
            'selectedTypeId',
            'wojewodztwa',
            'powiaty',
            'miejscowosci',
            'selectedWojewodztwo',
            'selectedPowiat',
            'selectedMiejscowosc',
            'results', 
            'pagination', 
            'page'
        ));
    }

    /**
     * AJAX: Pobiera powiaty dla województwa
     */
    public function getPowiaty(Request $request): JsonResponse
    {
        $request->validate([
            'wojewodztwo_kod' => 'required|string',
        ]);

        try {
            // Pobierz parametry
            $wojewodztwoKod = $request->wojewodztwo_kod;
            $wojewodztwoNazwa = $request->get('wojewodztwo_nazwa');
            
            \Log::info('RSPO getPowiaty - Request', [
                'wojewodztwo_kod' => $wojewodztwoKod,
                'wojewodztwo_nazwa' => $wojewodztwoNazwa
            ]);
            
            // Jeśli kod jest pusty lub null, użyj nazwy do znalezienia kodu
            if (empty($wojewodztwoKod) || $wojewodztwoKod === 'null' || $wojewodztwoKod === '') {
                if ($wojewodztwoNazwa) {
                    // Spróbuj znaleźć kod po nazwie z selecta
                    $wojewodztwa = $this->terytService->getWojewodztwa();
                    foreach ($wojewodztwa as $woj) {
                        if (($woj['nazwa'] ?? '') === $wojewodztwoNazwa) {
                            $wojewodztwoKod = $woj['kod'] ?? '';
                            break;
                        }
                    }
                }
            }
            
            // Jeśli nadal nie mamy kodu, użyj nazwy bezpośrednio
            if (empty($wojewodztwoKod) && $wojewodztwoNazwa) {
                // Użyj metody która przyjmuje nazwę
                $powiaty = $this->terytService->getPowiatyByNazwa($wojewodztwoNazwa);
            } else {
                $powiaty = $this->terytService->getPowiaty($wojewodztwoKod);
            }
            
            \Log::info('RSPO getPowiaty - Response', [
                'wojewodztwo_kod' => $wojewodztwoKod,
                'powiaty_count' => count($powiaty)
            ]);
            
            return response()->json([
                'success' => true,
                'powiaty' => $powiaty
            ]);
        } catch (\Exception $e) {
            \Log::error('RSPO Controller Error - getPowiaty', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'wojewodztwo_kod' => $request->wojewodztwo_kod,
                'wojewodztwo_nazwa' => $request->get('wojewodztwo_nazwa')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Błąd podczas pobierania powiatów: ' . $e->getMessage(),
                'powiaty' => []
            ], 500);
        }
    }

    /**
     * AJAX: Pobiera miejscowości dla powiatu
     */
    public function getMiejscowosci(Request $request): JsonResponse
    {
        $request->validate([
            'wojewodztwo_kod' => 'required|string',
            'powiat_kod' => 'required|string',
        ]);

        try {
            $wojewodztwoKod = $request->wojewodztwo_kod;
            $powiatKod = $request->powiat_kod;
            $powiatNazwa = $request->get('powiat_nazwa');
            
            \Log::info('RSPO getMiejscowosci - Request', [
                'wojewodztwo_kod' => $wojewodztwoKod,
                'powiat_kod' => $powiatKod,
                'powiat_nazwa' => $powiatNazwa
            ]);
            
            // Jeśli mamy nazwę i kod powiatu, użyj obu (kod jest bardziej niezawodny)
            if ($powiatNazwa) {
                $miejscowosci = $this->terytService->getMiejscowosciByNazwaPowiatu($powiatNazwa, $powiatKod);
            } else {
                $miejscowosci = $this->terytService->getMiejscowosci($wojewodztwoKod, $powiatKod);
            }
            
            \Log::info('RSPO getMiejscowosci - Response', [
                'wojewodztwo_kod' => $wojewodztwoKod,
                'powiat_kod' => $powiatKod,
                'miejscowosci_count' => count($miejscowosci)
            ]);
            
            return response()->json([
                'success' => true,
                'miejscowosci' => $miejscowosci
            ]);
        } catch (\Exception $e) {
            \Log::error('RSPO Controller Error - getMiejscowosci', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'wojewodztwo_kod' => $request->wojewodztwo_kod,
                'powiat_kod' => $request->powiat_kod,
                'powiat_nazwa' => $request->get('powiat_nazwa')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Błąd podczas pobierania miejscowości: ' . $e->getMessage(),
                'miejscowosci' => []
            ], 500);
        }
    }
}
