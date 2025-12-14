<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class RSPOController extends Controller
{
    private const API_BASE_URL = 'https://api-rspo.men.gov.pl/api';

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
                                $count = Cache::remember("rspo_type_count_{$typeId}", 86400, function () use ($typeId) {
                                    try {
                                        // Pobierz tylko informację o całkowitej liczbie (używamy application/ld+json dla hydra:totalItems)
                                        // Skrócony timeout, aby nie blokować strony
                                        $countResponse = Http::accept('application/ld+json')
                                            ->timeout(3)
                                            ->get(self::API_BASE_URL . '/placowki/', [
                                                'typ_podmiotu_id' => $typeId,
                                                'page' => 1,
                                            ]);
                                        
                                        if ($countResponse->successful()) {
                                            $countData = $countResponse->json();
                                            $totalItems = $countData['hydra:totalItems'] ?? null;
                                            if ($totalItems !== null) {
                                                return (int) $totalItems;
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        // Loguj błąd dla debugowania
                                        \Log::warning("RSPO API Error (type count for {$typeId}): " . $e->getMessage());
                                    }
                                    return null;
                                });
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
        // Jeśli typ_podmiotu_id jest pusty, wyszukujemy wszystkie typy
        if ($request->has('typ_podmiotu_id') || $page > 1) {
            try {
                // Buduj parametry zapytania
                $params = [
                    'page' => $page,
                ];
                
                // Dodaj parametr typu podmiotu tylko jeśli wybrano konkretny typ
                if ($selectedTypeId) {
                    $params['typ_podmiotu_id'] = $selectedTypeId;
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

        return view('rspo.search', compact(
            'types', 
            'selectedTypeId', 
            'results', 
            'pagination', 
            'page'
        ));
    }
}
