<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class RSPOImportService
{
    private const API_BASE_URL = 'https://api-rspo.men.gov.pl/api';
    private const ITEMS_PER_PAGE = 100;

    /**
     * Pobiera placówki z RSPO API z filtrowaniem
     * 
     * @param array $filters Filtry wyszukiwania (typ_podmiotu_id, wojewodztwo_nazwa, etc.)
     * @param callable|null $progressCallback Callback wywoływany dla każdej strony (page, totalPages, currentCount)
     * @return array Tablica placówek z emailami
     */
    public function fetchSchools(array $filters = [], ?callable $progressCallback = null): array
    {
        $allSchools = [];
        $page = 1;
        $hasMore = true;

        try {
            while ($hasMore) {
                $params = array_merge($filters, [
                    'page' => $page,
                ]);

                $response = Http::accept('application/ld+json')
                    ->timeout(30)
                    ->get(self::API_BASE_URL . '/placowki/', $params);

                if (!$response->successful()) {
                    Log::warning("RSPO API Error - fetchSchools", [
                        'status' => $response->status(),
                        'page' => $page,
                        'filters' => $filters
                    ]);
                    break;
                }

                $data = $response->json();
                
                if (isset($data['hydra:member'])) {
                    $schools = $data['hydra:member'];
                    $totalItems = $data['hydra:totalItems'] ?? 0;
                    $totalPages = (int) ceil($totalItems / self::ITEMS_PER_PAGE);
                    
                    // Filtruj tylko placówki z emailami
                    foreach ($schools as $school) {
                        $email = $this->extractEmail($school);
                        if ($email) {
                            $allSchools[] = [
                                'email' => $email,
                                'name' => $school['nazwa'] ?? $school['nazwaSkrocona'] ?? 'Brak nazwy',
                                'rspo' => $school['numerRspo'] ?? null,
                                'wojewodztwo' => $school['wojewodztwo'] ?? null,
                                'miejscowosc' => $school['miejscowosc'] ?? null,
                                'typ' => $school['typ'] ?? null, // Nazwa typu z API
                                'typ_podmiotu_id' => $filters['typ_podmiotu_id'] ?? null,
                                'raw_data' => $school, // Pełne dane dla późniejszego użycia
                            ];
                        }
                    }

                    // Wywołaj callback jeśli podano
                    if ($progressCallback) {
                        $progressCallback($page, $totalPages, count($allSchools));
                    }

                    // Sprawdź czy są kolejne strony
                    $hasMore = isset($data['hydra:view']['hydra:next']) && count($schools) === self::ITEMS_PER_PAGE;
                    $page++;
                } else {
                    $hasMore = false;
                }

                // Zabezpieczenie przed nieskończoną pętlą
                if ($page > 1000) {
                    Log::warning("RSPO Import - Przekroczono limit stron (1000)");
                    break;
                }
            }
        } catch (Exception $e) {
            Log::error('RSPO Import Service Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => $filters
            ]);
        }

        return $allSchools;
    }

    /**
     * Pobiera typy podmiotów z RSPO
     */
    public function getSchoolTypes(): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get(self::API_BASE_URL . '/typ/');

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data)) {
                    usort($data, function($a, $b) {
                        return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                    });
                    return $data;
                }
            }
        } catch (Exception $e) {
            Log::error('RSPO Import Service - getSchoolTypes', [
                'message' => $e->getMessage()
            ]);
        }

        return [];
    }

    /**
     * Pobiera województwa z RSPO
     */
    public function getWojewodztwa(): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get(self::API_BASE_URL . '/wojewodztwa/');

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data)) {
                    usort($data, function($a, $b) {
                        return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                    });
                    return $data;
                }
            }
        } catch (Exception $e) {
            Log::error('RSPO Import Service - getWojewodztwa', [
                'message' => $e->getMessage()
            ]);
        }

        return [];
    }

    /**
     * Wyciąga email z danych placówki
     */
    private function extractEmail(array $school): ?string
    {
        $email = $school['email'] ?? null;
        
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($email));
        }

        return null;
    }

    /**
     * Grupuje szkoły według kryterium (województwo, typ, etc.)
     */
    public function groupSchools(array $schools, string $groupBy = 'wojewodztwo'): array
    {
        $grouped = [];

        // Jeśli grupowanie po typie, pobierz mapę typów dla fallback
        $typesMap = [];
        if ($groupBy === 'typ') {
            $types = $this->getSchoolTypes();
            foreach ($types as $type) {
                $typesMap[$type['id']] = $type['nazwa'] ?? null;
            }
        }

        foreach ($schools as $school) {
            // Pobierz klucz grupy
            $key = null;
            
            if ($groupBy === 'typ') {
                // Dla typu: najpierw sprawdź pole 'typ', potem zmapuj z typ_podmiotu_id
                $key = $school['typ'] ?? null;
                
                // Jeśli nie ma typu, spróbuj zmapować z typ_podmiotu_id
                if (!$key && isset($school['typ_podmiotu_id']) && isset($typesMap[$school['typ_podmiotu_id']])) {
                    $key = $typesMap[$school['typ_podmiotu_id']];
                }
                
                // Jeśli nadal nie ma, użyj "Inne"
                if (!$key) {
                    $key = 'Inne';
                }
            } else {
                // Dla innych kryteriów (wojewodztwo, miejscowosc)
                $key = $school[$groupBy] ?? 'Inne';
            }
            
            // Normalizuj klucz (trim, usuń nadmiarowe spacje)
            $key = trim($key);
            if (empty($key)) {
                $key = 'Inne';
            }
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $school;
        }

        return $grouped;
    }

    /**
     * Waliduje i czyści dane szkoły przed importem
     */
    public function validateSchool(array $school): bool
    {
        return !empty($school['email']) 
            && filter_var($school['email'], FILTER_VALIDATE_EMAIL)
            && !empty($school['name']);
    }
}

