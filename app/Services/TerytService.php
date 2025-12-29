<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;
use SoapFault;
use App\Services\WSSoapClient;

class TerytService
{
    private const TERYT_WSDL = 'https://uslugaterytws1.stat.gov.pl/wsdl/terytws1.wsdl';
    private const TERYT_ENDPOINT = 'https://uslugaterytws1.stat.gov.pl/terytws1.svc';
    
    private string $username;
    private string $password;
    private ?WSSoapClient $soapClient = null;

    public function __construct()
    {
        $this->username = config('services.teryt.username');
        $this->password = config('services.teryt.password');
    }

    /**
     * Pobiera klienta SOAP (z cache)
     */
    private function getSoapClient(): WSSoapClient
    {
        if ($this->soapClient === null) {
            try {
                $this->soapClient = new WSSoapClient(
                    self::TERYT_WSDL,
                    [
                        'soap_version' => SOAP_1_1, // TERYT używa SOAP 1.1
                        'location' => self::TERYT_ENDPOINT,
                        'trace' => true,
                        'exceptions' => true,
                        'cache_wsdl' => WSDL_CACHE_BOTH,
                        'connection_timeout' => 30,
                        'stream_context' => stream_context_create([
                            'http' => [
                                'user_agent' => 'PHP SoapClient',
                            ]
                        ])
                    ],
                    $this->username,
                    $this->password
                );
            } catch (SoapFault $e) {
                Log::error('TERYT SOAP Client Error', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
                throw $e;
            }
        }

        return $this->soapClient;
    }

    /**
     * Pobiera listę województw z API RSPO (alternatywa gdy TERYT SOAP nie działa)
     */
    private function getWojewodztwaFromRSPO(): array
    {
        return Cache::remember('rspo_wojewodztwa', 86400, function () {
            try {
                $response = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                    ->timeout(30)
                    ->get('https://api-rspo.men.gov.pl/api/placowki/', [
                        'page' => 1,
                        'itemsPerPage' => 100
                    ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    $wojewodztwa = [];
                    
                    if (isset($data['hydra:member'])) {
                        foreach ($data['hydra:member'] as $placowka) {
                            $woj = $placowka['wojewodztwo'] ?? null;
                            if ($woj && !isset($wojewodztwa[$woj])) {
                                $wojewodztwa[$woj] = [
                                    'nazwa' => $woj,
                                    'kod' => $placowka['wojewodztwoKodTERYT'] ?? null,
                                ];
                            }
                        }
                    }
                    
                    // Pobierz więcej stron jeśli potrzeba
                    $totalItems = $data['hydra:totalItems'] ?? 0;
                    $pages = min(10, ceil($totalItems / 100)); // Max 10 stron dla wydajności
                    
                    for ($page = 2; $page <= $pages; $page++) {
                        $pageResponse = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                            ->timeout(30)
                            ->get('https://api-rspo.men.gov.pl/api/placowki/', [
                                'page' => $page,
                                'itemsPerPage' => 100
                            ]);
                        
                        if ($pageResponse->successful()) {
                            $pageData = $pageResponse->json();
                            if (isset($pageData['hydra:member'])) {
                                foreach ($pageData['hydra:member'] as $placowka) {
                                    $woj = $placowka['wojewodztwo'] ?? null;
                                    if ($woj && !isset($wojewodztwa[$woj])) {
                                        $wojewodztwa[$woj] = [
                                            'nazwa' => $woj,
                                            'kod' => $placowka['wojewodztwoKodTERYT'] ?? null,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    
                    $result = array_values($wojewodztwa);
                    usort($result, function($a, $b) {
                        return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                    });
                    
                    return $result;
                }
            } catch (\Exception $e) {
                Log::error('TERYT getWojewodztwaFromRSPO Error', [
                    'message' => $e->getMessage()
                ]);
            }
            
            return [];
        });
    }

    /**
     * Pobiera listę województw
     */
    public function getWojewodztwa(): array
    {
        // Najpierw spróbuj z TERYT SOAP, jeśli nie działa użyj RSPO API
        try {
            $result = Cache::remember('teryt_wojewodztwa', 86400 * 7, function () {
            try {
                Log::info('TERYT getWojewodztwa - Starting', [
                    'username' => $this->username,
                    'wsdl' => self::TERYT_WSDL
                ]);
                
                $client = $this->getSoapClient();
                
                // Uwierzytelnienie odbywa się przez WS-Security headers w WSSoapClient
                // Pobierz województwa
                $result = $client->PobierzListeWojewodztw();
                
                // Loguj surową odpowiedź dla debugowania
                $resultType = gettype($result);
                $resultVars = is_object($result) ? get_object_vars($result) : null;
                Log::info('TERYT getWojewodztwa - Raw response', [
                    'type' => $resultType,
                    'vars' => $resultVars,
                    'soap_request' => $client->__getLastRequest(),
                    'soap_response' => $client->__getLastResponse()
                ]);
                
                // Obsługa różnych formatów odpowiedzi SOAP
                $wojewodztwa = [];
                $items = [];
                
                if (is_object($result)) {
                    // Format 1: $result->PobierzListeWojewodztwResult->Wojewodztwo
                    if (isset($result->PobierzListeWojewodztwResult)) {
                        $methodResult = $result->PobierzListeWojewodztwResult;
                        if (isset($methodResult->Wojewodztwo)) {
                            $items = is_array($methodResult->Wojewodztwo) 
                                ? $methodResult->Wojewodztwo 
                                : [$methodResult->Wojewodztwo];
                        } elseif (is_object($methodResult)) {
                            $methodVars = get_object_vars($methodResult);
                            if (isset($methodVars['Wojewodztwo'])) {
                                $items = is_array($methodVars['Wojewodztwo']) 
                                    ? $methodVars['Wojewodztwo'] 
                                    : [$methodVars['Wojewodztwo']];
                            }
                        }
                    }
                    // Format 2: $result->Wojewodztwo
                    if (empty($items) && isset($result->Wojewodztwo)) {
                        $items = is_array($result->Wojewodztwo) 
                            ? $result->Wojewodztwo 
                            : [$result->Wojewodztwo];
                    }
                    // Format 3: $result->return
                    if (empty($items) && isset($result->return)) {
                        $items = is_array($result->return) 
                            ? $result->return 
                            : [$result->return];
                    }
                    // Format 4: Sprawdź wszystkie właściwości
                    if (empty($items)) {
                        $vars = get_object_vars($result);
                        foreach ($vars as $key => $value) {
                            if (is_object($value) || is_array($value)) {
                                if (is_object($value)) {
                                    $valueVars = get_object_vars($value);
                                    if (isset($valueVars['Wojewodztwo'])) {
                                        $items = is_array($valueVars['Wojewodztwo']) 
                                            ? $valueVars['Wojewodztwo'] 
                                            : [$valueVars['Wojewodztwo']];
                                        break;
                                    }
                                }
                                $items = is_array($value) ? $value : [$value];
                                break;
                            }
                        }
                    }
                } elseif (is_array($result)) {
                    $items = $result;
                }
                
                Log::info('TERYT getWojewodztwa - Parsed items', [
                    'items_count' => count($items),
                    'first_item_structure' => !empty($items) && is_object($items[0]) 
                        ? get_object_vars($items[0]) 
                        : (!empty($items) ? $items[0] : null)
                ]);
                
                foreach ($items as $woj) {
                    if (is_object($woj)) {
                        $wojewodztwa[] = [
                            'kod' => $woj->WOJ ?? $woj->kod ?? null,
                            'nazwa' => $woj->NAZWA ?? $woj->nazwa ?? null,
                            'nazwa_dodatkowa' => $woj->NAZWA_DOD ?? $woj->nazwa_dodatkowa ?? null,
                        ];
                    } elseif (is_array($woj)) {
                        $wojewodztwa[] = [
                            'kod' => $woj['WOJ'] ?? $woj['kod'] ?? null,
                            'nazwa' => $woj['NAZWA'] ?? $woj['nazwa'] ?? null,
                            'nazwa_dodatkowa' => $woj['NAZWA_DOD'] ?? $woj['nazwa_dodatkowa'] ?? null,
                        ];
                    }
                }
                
                // Sortuj alfabetycznie
                usort($wojewodztwa, function($a, $b) {
                    return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                });
                
                    return $wojewodztwa;
                } catch (SoapFault $e) {
                    Log::error('TERYT API Error - getWojewodztwa', [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode()
                    ]);
                    return []; // Zwróć pustą tablicę aby fallback mógł zadziałać
                } catch (Exception $e) {
                    Log::error('TERYT Service Exception - getWojewodztwa', [
                        'message' => $e->getMessage(),
                        'class' => get_class($e)
                    ]);
                    return []; // Zwróć pustą tablicę aby fallback mógł zadziałać
                }
            });
            
            // Jeśli mamy wynik z TERYT, zwróć go
            if (!empty($result)) {
                return $result;
            }
        } catch (\Exception $e) {
            Log::warning('TERYT SOAP failed, using RSPO API fallback', [
                'message' => $e->getMessage()
            ]);
        }
        
        // Fallback: użyj API RSPO
        return $this->getWojewodztwaFromRSPO();
    }

    /**
     * Pobiera listę powiatów dla województwa z API RSPO (fallback) - używa dedykowanego endpointu
     */
    private function getPowiatyFromRSPO(string $wojewodztwoNazwa): array
    {
        $cacheKey = "rspo_powiaty_{$wojewodztwoNazwa}";
        
        return Cache::remember($cacheKey, 86400, function () use ($wojewodztwoNazwa) {
            try {
                // Użyj dedykowanego endpointu /api/powiaty/ zamiast przeglądania wszystkich placówek
                $response = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                    ->timeout(30)
                    ->get('https://api-rspo.men.gov.pl/api/powiaty/', [
                        'wojewodztwo_nazwa' => $wojewodztwoNazwa,
                        'page' => 1,
                        'itemsPerPage' => 100
                    ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    $powiaty = [];
                    
                    if (isset($data['hydra:member'])) {
                        // API zwraca powiaty bezpośrednio - filtruj po województwie
                        foreach ($data['hydra:member'] as $powiat) {
                            $wojewodztwo = $powiat['wojewodztwo'] ?? [];
                            $wojewodztwoNazwaPowiatu = is_array($wojewodztwo) ? ($wojewodztwo['nazwa'] ?? null) : null;
                            
                            // Sprawdź czy powiat należy do wybranego województwa
                            if (strtoupper(trim($wojewodztwoNazwaPowiatu)) !== strtoupper(trim($wojewodztwoNazwa))) {
                                continue; // Pomiń jeśli nie pasuje
                            }
                            
                            $wojewodztwoKod = is_array($wojewodztwo) ? ($wojewodztwo['kodTeryt'] ?? null) : null;
                            
                            $powiaty[] = [
                                'nazwa' => $powiat['nazwa'] ?? null,
                                'kod' => $powiat['kodTeryt'] ?? null,
                                'wojewodztwo_kod' => $wojewodztwoKod,
                            ];
                        }
                    }
                    
                    // Jeśli są więcej stron, pobierz je
                    $totalItems = $data['hydra:totalItems'] ?? 0;
                    $pages = min(10, ceil($totalItems / 100)); // Max 10 stron (1000 powiatów)
                    
                    for ($page = 2; $page <= $pages; $page++) {
                        $pageResponse = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                            ->timeout(30)
                            ->get('https://api-rspo.men.gov.pl/api/powiaty/', [
                                'wojewodztwo_nazwa' => $wojewodztwoNazwa,
                                'page' => $page,
                                'itemsPerPage' => 100
                            ]);
                        
                        if ($pageResponse->successful()) {
                            $pageData = $pageResponse->json();
                            if (isset($pageData['hydra:member'])) {
                                foreach ($pageData['hydra:member'] as $powiat) {
                                    $wojewodztwo = $powiat['wojewodztwo'] ?? [];
                                    $wojewodztwoNazwaPowiatu = is_array($wojewodztwo) ? ($wojewodztwo['nazwa'] ?? null) : null;
                                    
                                    // Sprawdź czy powiat należy do wybranego województwa
                                    if (strtoupper(trim($wojewodztwoNazwaPowiatu)) !== strtoupper(trim($wojewodztwoNazwa))) {
                                        continue; // Pomiń jeśli nie pasuje
                                    }
                                    
                                    $wojewodztwoKod = is_array($wojewodztwo) ? ($wojewodztwo['kodTeryt'] ?? null) : null;
                                    
                                    $powiaty[] = [
                                        'nazwa' => $powiat['nazwa'] ?? null,
                                        'kod' => $powiat['kodTeryt'] ?? null,
                                        'wojewodztwo_kod' => $wojewodztwoKod,
                                    ];
                                }
                            }
                        } else {
                            break;
                        }
                    }
                    
                    // Jeśli endpoint /api/powiaty/ nie zwrócił danych, użyj fallback przez placówki
                    if (empty($powiaty)) {
                        return $this->getPowiatyFromRSPOByPlacowki($wojewodztwoNazwa);
                    }
                    
                    // Usuń duplikaty i posortuj
                    $unique = [];
                    $seen = [];
                    foreach ($powiaty as $pow) {
                        $key = strtolower(trim($pow['nazwa'] ?? ''));
                        if ($key && !isset($seen[$key])) {
                            $seen[$key] = true;
                            $unique[] = $pow;
                        }
                    }
                    
                    usort($unique, function($a, $b) {
                        return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                    });
                    
                    return $unique;
                } else {
                    // Fallback: użyj metody przez placówki
                    return $this->getPowiatyFromRSPOByPlacowki($wojewodztwoNazwa);
                }
            } catch (\Exception $e) {
                Log::error('TERYT getPowiatyFromRSPO Error', [
                    'message' => $e->getMessage()
                ]);
                // Fallback: użyj metody przez placówki
                return $this->getPowiatyFromRSPOByPlacowki($wojewodztwoNazwa);
            }
        });
    }

    /**
     * Pobiera powiaty przez przeglądanie placówek (fallback gdy endpoint /api/powiaty/ nie działa)
     */
    private function getPowiatyFromRSPOByPlacowki(string $wojewodztwoNazwa): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                ->timeout(30)
                ->get('https://api-rspo.men.gov.pl/api/placowki/', [
                    'wojewodztwo_nazwa' => $wojewodztwoNazwa,
                    'page' => 1,
                    'itemsPerPage' => 100
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $powiaty = [];
                $totalItems = $data['hydra:totalItems'] ?? 0;
                $pages = min(5, ceil($totalItems / 100)); // Max 5 stron dla wydajności
                
                for ($page = 1; $page <= $pages; $page++) {
                    if ($page > 1) {
                        $response = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                            ->timeout(30)
                            ->get('https://api-rspo.men.gov.pl/api/placowki/', [
                                'wojewodztwo_nazwa' => $wojewodztwoNazwa,
                                'page' => $page,
                                'itemsPerPage' => 100
                            ]);
                        if (!$response->successful()) {
                            break;
                        }
                        $data = $response->json();
                    }
                    
                    if (isset($data['hydra:member'])) {
                        foreach ($data['hydra:member'] as $placowka) {
                            $wojewodztwoPlacowki = $placowka['wojewodztwo'] ?? null;
                            if (strtoupper(trim($wojewodztwoPlacowki)) !== strtoupper(trim($wojewodztwoNazwa))) {
                                continue;
                            }
                            
                            $pow = $placowka['powiat'] ?? null;
                            if ($pow && !isset($powiaty[$pow])) {
                                $powiaty[$pow] = [
                                    'nazwa' => $pow,
                                    'kod' => $placowka['powiatKodTERYT'] ?? null,
                                    'wojewodztwo_kod' => $placowka['wojewodztwoKodTERYT'] ?? null,
                                ];
                            }
                        }
                    }
                }
                
                $result = array_values($powiaty);
                usort($result, function($a, $b) {
                    return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                });
                
                return $result;
            }
        } catch (\Exception $e) {
            Log::error('TERYT getPowiatyFromRSPOByPlacowki Error', [
                'message' => $e->getMessage()
            ]);
        }
        
        return [];
    }

    /**
     * Pobiera listę powiatów dla województwa po nazwie (bezpośrednio z RSPO)
     */
    public function getPowiatyByNazwa(string $wojewodztwoNazwa): array
    {
        return $this->getPowiatyFromRSPO($wojewodztwoNazwa);
    }

    /**
     * Pobiera listę powiatów dla województwa
     */
    public function getPowiaty(string $wojewodztwoKod): array
    {
        $cacheKey = "teryt_powiaty_{$wojewodztwoKod}";
        
        try {
            $result = Cache::remember($cacheKey, 86400 * 7, function () use ($wojewodztwoKod) {
            try {
                $client = $this->getSoapClient();
                
                // Uwierzytelnienie odbywa się przez WS-Security headers w WSSoapClient
                // Pobierz powiaty dla województwa
                $result = $client->PobierzListePowiatow(['Woj' => $wojewodztwoKod]);
                
                // Obsługa różnych formatów odpowiedzi
                $powiaty = [];
                if (is_object($result)) {
                    if (isset($result->Powiat)) {
                        $items = is_array($result->Powiat) ? $result->Powiat : [$result->Powiat];
                    } elseif (isset($result->return)) {
                        $items = is_array($result->return) ? $result->return : [$result->return];
                    } else {
                        $items = [$result];
                    }
                } elseif (is_array($result)) {
                    $items = $result;
                } else {
                    $items = [];
                }
                
                foreach ($items as $pow) {
                    if (is_object($pow)) {
                        $powiaty[] = [
                            'kod' => $pow->POW ?? $pow->kod ?? null,
                            'nazwa' => $pow->NAZWA ?? $pow->nazwa ?? null,
                            'nazwa_dodatkowa' => $pow->NAZWA_DOD ?? $pow->nazwa_dodatkowa ?? null,
                            'rodzaj' => $pow->RODZ ?? $pow->rodzaj ?? null,
                            'wojewodztwo_kod' => $wojewodztwoKod,
                        ];
                    } elseif (is_array($pow)) {
                        $powiaty[] = [
                            'kod' => $pow['POW'] ?? $pow['kod'] ?? null,
                            'nazwa' => $pow['NAZWA'] ?? $pow['nazwa'] ?? null,
                            'nazwa_dodatkowa' => $pow['NAZWA_DOD'] ?? $pow['nazwa_dodatkowa'] ?? null,
                            'rodzaj' => $pow['RODZ'] ?? $pow['rodzaj'] ?? null,
                            'wojewodztwo_kod' => $wojewodztwoKod,
                        ];
                    }
                }
                
                // Sortuj alfabetycznie
                usort($powiaty, function($a, $b) {
                    return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                });
                
                    return $powiaty;
                } catch (SoapFault $e) {
                    Log::error('TERYT API Error - getPowiaty', [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'wojewodztwo_kod' => $wojewodztwoKod
                    ]);
                    return [];
                } catch (Exception $e) {
                    Log::error('TERYT Service Exception - getPowiaty', [
                        'message' => $e->getMessage(),
                        'wojewodztwo_kod' => $wojewodztwoKod
                    ]);
                    return [];
                }
            });
            
            if (!empty($result)) {
                return $result;
            }
        } catch (\Exception $e) {
            Log::warning('TERYT SOAP failed for powiaty, using RSPO API fallback', [
                'message' => $e->getMessage()
            ]);
        }
        
        // Fallback: znajdź nazwę województwa po kodzie i użyj RSPO API
        $wojewodztwa = $this->getWojewodztwa();
        foreach ($wojewodztwa as $woj) {
            if (($woj['kod'] ?? '') === $wojewodztwoKod || ($woj['kod'] ?? null) === null) {
                // Jeśli kod się zgadza lub kod jest null, użyj nazwy
                if (($woj['kod'] ?? '') === $wojewodztwoKod || empty($wojewodztwoKod)) {
                    return $this->getPowiatyFromRSPO($woj['nazwa']);
                }
            }
        }
        
        // Jeśli nie znaleziono po kodzie, spróbuj użyć kodu jako nazwy (dla kompatybilności)
        if (!empty($wojewodztwoKod)) {
            Log::warning('TERYT getPowiaty - Nie znaleziono województwa po kodzie, próba użycia kodu jako nazwy', [
                'wojewodztwo_kod' => $wojewodztwoKod
            ]);
        }
        
        return [];
    }

    /**
     * Pobiera listę gmin dla powiatu po nazwie (bezpośrednio z RSPO)
     */
    public function getGminyByNazwaPowiatu(string $powiatNazwa, ?string $powiatKod = null): array
    {
        return $this->getGminyFromRSPO($powiatNazwa, $powiatKod);
    }

    /**
     * Pobiera listę miejscowości dla powiatu po nazwie (bezpośrednio z RSPO)
     */
    public function getMiejscowosciByNazwaPowiatu(string $powiatNazwa, ?string $powiatKod = null): array
    {
        return $this->getMiejscowosciFromRSPO($powiatNazwa, $powiatKod);
    }

    /**
     * Pobiera listę gmin dla powiatu z API RSPO
     * Używa endpointu /api/placowki/ aby wyciągnąć unikalne gminy
     */
    private function getGminyFromRSPO(string $powiatNazwa, ?string $powiatKod = null): array
    {
        $cacheKey = "rspo_gminy_{$powiatNazwa}_" . ($powiatKod ?? 'no_kod');
        
        return Cache::remember($cacheKey, 86400, function () use ($powiatNazwa, $powiatKod) {
            try {
                // Użyj endpointu /api/placowki/ z filtrem powiatu
                $params = ['page' => 1, 'itemsPerPage' => 100];
                if ($powiatKod) {
                    $params['powiat_kod_teryt'] = $powiatKod;
                } else {
                    $params['powiat_nazwa'] = $powiatNazwa;
                }
                
                $response = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                    ->timeout(30)
                    ->get('https://api-rspo.men.gov.pl/api/placowki/', $params);
                
                if ($response->successful()) {
                    $data = $response->json();
                    $gminy = [];
                    
                    if (isset($data['hydra:member'])) {
                        foreach ($data['hydra:member'] as $placowka) {
                            // Sprawdź czy placówka należy do wybranego powiatu
                            $powiatPlacowki = $placowka['powiat'] ?? null;
                            $powiatKodPlacowki = $placowka['powiatKodTERYT'] ?? null;
                            
                            // Filtruj po kodzie lub nazwie powiatu
                            if ($powiatKod) {
                                if (trim($powiatKodPlacowki) !== trim($powiatKod)) {
                                    continue;
                                }
                            } else {
                                if (strtoupper(trim($powiatPlacowki)) !== strtoupper(trim($powiatNazwa))) {
                                    continue;
                                }
                            }
                            
                            // Pobierz gminę z placówki
                            $gminaNazwa = $placowka['gmina'] ?? null;
                            $gminaKod = $placowka['gminaKodTERYT'] ?? null;
                            
                            if ($gminaNazwa && !isset($gminy[$gminaNazwa])) {
                                $gminy[$gminaNazwa] = [
                                    'nazwa' => $gminaNazwa,
                                    'kod' => $gminaKod,
                                ];
                            }
                        }
                    }
                    
                    // Pobierz więcej stron jeśli potrzeba
                    $totalItems = $data['hydra:totalItems'] ?? 0;
                    $maxPages = min(20, ceil($totalItems / 100)); // Max 20 stron
                    
                    for ($page = 2; $page <= $maxPages; $page++) {
                        $pageParams = ['page' => $page, 'itemsPerPage' => 100];
                        if ($powiatKod) {
                            $pageParams['powiat_kod_teryt'] = $powiatKod;
                        } else {
                            $pageParams['powiat_nazwa'] = $powiatNazwa;
                        }
                        
                        $pageResponse = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                            ->timeout(30)
                            ->get('https://api-rspo.men.gov.pl/api/placowki/', $pageParams);
                        
                        if ($pageResponse->successful()) {
                            $pageData = $pageResponse->json();
                            if (isset($pageData['hydra:member'])) {
                                foreach ($pageData['hydra:member'] as $placowka) {
                                    // Sprawdź czy placówka należy do wybranego powiatu
                                    $powiatPlacowki = $placowka['powiat'] ?? null;
                                    $powiatKodPlacowki = $placowka['powiatKodTERYT'] ?? null;
                                    
                                    // Filtruj po kodzie lub nazwie powiatu
                                    if ($powiatKod) {
                                        if (trim($powiatKodPlacowki) !== trim($powiatKod)) {
                                            continue;
                                        }
                                    } else {
                                        if (strtoupper(trim($powiatPlacowki)) !== strtoupper(trim($powiatNazwa))) {
                                            continue;
                                        }
                                    }
                                    
                                    // Pobierz gminę z placówki
                                    $gminaNazwa = $placowka['gmina'] ?? null;
                                    $gminaKod = $placowka['gminaKodTERYT'] ?? null;
                                    
                                    if ($gminaNazwa && !isset($gminy[$gminaNazwa])) {
                                        $gminy[$gminaNazwa] = [
                                            'nazwa' => $gminaNazwa,
                                            'kod' => $gminaKod,
                                        ];
                                    }
                                }
                            }
                        } else {
                            break;
                        }
                    }
                    
                    // Konwertuj z tablicy asocjacyjnej na zwykłą tablicę i posortuj
                    $result = array_values($gminy);
                    usort($result, function($a, $b) {
                        return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                    });
                    
                    return $result;
                } else {
                    Log::error('TERYT getGminyFromRSPO Error - HTTP', [
                        'status' => $response->status(),
                        'powiat_nazwa' => $powiatNazwa,
                        'powiat_kod' => $powiatKod
                    ]);
                    return [];
                }
            } catch (\Exception $e) {
                Log::error('TERYT getGminyFromRSPO Error', [
                    'message' => $e->getMessage(),
                    'powiat_nazwa' => $powiatNazwa,
                    'powiat_kod' => $powiatKod
                ]);
                return [];
            }
        });
    }

    /**
     * Pobiera listę miejscowości dla powiatu z API RSPO (fallback)
     * Używa endpointu /api/placowki/ zamiast /api/miejscowosci/ bo jest bardziej niezawodne
     */
    private function getMiejscowosciFromRSPO(string $powiatNazwa, ?string $powiatKod = null): array
    {
        $cacheKey = "rspo_miejscowosci_{$powiatNazwa}_" . ($powiatKod ?? 'no_kod');
        
        return Cache::remember($cacheKey, 86400, function () use ($powiatNazwa, $powiatKod) {
            try {
                // Użyj endpointu /api/placowki/ z filtrem powiatu - bardziej niezawodne niż /api/miejscowosci/
                $params = ['page' => 1, 'itemsPerPage' => 100];
                if ($powiatKod) {
                    $params['powiat_kod_teryt'] = $powiatKod;
                } else {
                    $params['powiat_nazwa'] = $powiatNazwa;
                }
                
                $response = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                    ->timeout(30)
                    ->get('https://api-rspo.men.gov.pl/api/placowki/', $params);
                
                if ($response->successful()) {
                    $data = $response->json();
                    $miejscowosci = [];
                    
                    if (isset($data['hydra:member'])) {
                        foreach ($data['hydra:member'] as $placowka) {
                            // Sprawdź czy placówka należy do wybranego powiatu
                            $powiatPlacowki = $placowka['powiat'] ?? null;
                            $powiatKodPlacowki = $placowka['powiatKodTERYT'] ?? null;
                            
                            // Filtruj po kodzie lub nazwie powiatu
                            if ($powiatKod) {
                                if (trim($powiatKodPlacowki) !== trim($powiatKod)) {
                                    continue; // Pomiń jeśli kod nie pasuje
                                }
                            } else {
                                if (strtoupper(trim($powiatPlacowki)) !== strtoupper(trim($powiatNazwa))) {
                                    continue; // Pomiń jeśli nazwa nie pasuje
                                }
                            }
                            
                            // Pobierz miejscowość z placówki
                            $miejscowoscNazwa = $placowka['miejscowosc'] ?? null;
                            $miejscowoscKod = $placowka['miejscowoscKodTERYT'] ?? null;
                            
                            if ($miejscowoscNazwa && !isset($miejscowosci[$miejscowoscNazwa])) {
                                $miejscowosci[$miejscowoscNazwa] = [
                                    'nazwa' => $miejscowoscNazwa,
                                    'kod' => $miejscowoscKod,
                                ];
                            }
                        }
                    }
                    
                    // Pobierz więcej stron jeśli potrzeba (placówek może być dużo)
                    $totalItems = $data['hydra:totalItems'] ?? 0;
                    $maxPages = min(20, ceil($totalItems / 100)); // Max 20 stron (2000 placówek) - powinno wystarczyć
                    
                    for ($page = 2; $page <= $maxPages; $page++) {
                        $pageParams = ['page' => $page, 'itemsPerPage' => 100];
                        if ($powiatKod) {
                            $pageParams['powiat_kod_teryt'] = $powiatKod;
                        } else {
                            $pageParams['powiat_nazwa'] = $powiatNazwa;
                        }
                        
                        $pageResponse = \Illuminate\Support\Facades\Http::accept('application/ld+json')
                            ->timeout(30)
                            ->get('https://api-rspo.men.gov.pl/api/placowki/', $pageParams);
                        
                        if ($pageResponse->successful()) {
                            $pageData = $pageResponse->json();
                            if (isset($pageData['hydra:member'])) {
                                foreach ($pageData['hydra:member'] as $placowka) {
                                    // Sprawdź czy placówka należy do wybranego powiatu
                                    $powiatPlacowki = $placowka['powiat'] ?? null;
                                    $powiatKodPlacowki = $placowka['powiatKodTERYT'] ?? null;
                                    
                                    // Filtruj po kodzie lub nazwie powiatu
                                    if ($powiatKod) {
                                        if (trim($powiatKodPlacowki) !== trim($powiatKod)) {
                                            continue;
                                        }
                                    } else {
                                        if (strtoupper(trim($powiatPlacowki)) !== strtoupper(trim($powiatNazwa))) {
                                            continue;
                                        }
                                    }
                                    
                                    // Pobierz miejscowość z placówki
                                    $miejscowoscNazwa = $placowka['miejscowosc'] ?? null;
                                    $miejscowoscKod = $placowka['miejscowoscKodTERYT'] ?? null;
                                    
                                    if ($miejscowoscNazwa && !isset($miejscowosci[$miejscowoscNazwa])) {
                                        $miejscowosci[$miejscowoscNazwa] = [
                                            'nazwa' => $miejscowoscNazwa,
                                            'kod' => $miejscowoscKod,
                                        ];
                                    }
                                }
                            }
                        } else {
                            break;
                        }
                    }
                    
                    // Konwertuj z tablicy asocjacyjnej na zwykłą tablicę i posortuj
                    $result = array_values($miejscowosci);
                    usort($result, function($a, $b) {
                        return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                    });
                    
                    return $result;
                }
            } catch (\Exception $e) {
                Log::error('TERYT getMiejscowosciFromRSPO Error', [
                    'message' => $e->getMessage()
                ]);
            }
            
            return [];
        });
    }

    /**
     * Pobiera listę miejscowości dla powiatu
     */
    public function getMiejscowosci(string $wojewodztwoKod, string $powiatKod): array
    {
        $cacheKey = "teryt_miejscowosci_{$wojewodztwoKod}_{$powiatKod}";
        
        try {
            $result = Cache::remember($cacheKey, 86400 * 7, function () use ($wojewodztwoKod, $powiatKod) {
                try {
                    $client = $this->getSoapClient();
                    
                    // Uwierzytelnienie odbywa się przez WS-Security headers w WSSoapClient
                    // Pobierz miejscowości dla powiatu
                    $result = $client->PobierzListeMiejscowosciWRodzaju([
                        'Woj' => $wojewodztwoKod,
                        'Pow' => $powiatKod
                    ]);
                    
                    // Obsługa różnych formatów odpowiedzi
                    $miejscowosci = [];
                    if (is_object($result)) {
                        if (isset($result->Miejscowosc)) {
                            $items = is_array($result->Miejscowosc) ? $result->Miejscowosc : [$result->Miejscowosc];
                        } elseif (isset($result->return)) {
                            $items = is_array($result->return) ? $result->return : [$result->return];
                        } else {
                            $items = [$result];
                        }
                    } elseif (is_array($result)) {
                        $items = $result;
                    } else {
                        $items = [];
                    }
                    
                    foreach ($items as $miejsc) {
                        if (is_object($miejsc)) {
                            $miejscowosci[] = [
                                'kod' => $miejsc->SYM ?? $miejsc->kod ?? null,
                                'sympod' => $miejsc->SYMPOD ?? $miejsc->sympod ?? null,
                                'nazwa' => $miejsc->NAZWA ?? $miejsc->nazwa ?? null,
                                'rodzaj' => $miejsc->RODZ ?? $miejsc->rodzaj ?? null,
                                'wojewodztwo_kod' => $wojewodztwoKod,
                                'powiat_kod' => $powiatKod,
                            ];
                        } elseif (is_array($miejsc)) {
                            $miejscowosci[] = [
                                'kod' => $miejsc['SYM'] ?? $miejsc['kod'] ?? null,
                                'sympod' => $miejsc['SYMPOD'] ?? $miejsc['sympod'] ?? null,
                                'nazwa' => $miejsc['NAZWA'] ?? $miejsc['nazwa'] ?? null,
                                'rodzaj' => $miejsc['RODZ'] ?? $miejsc['rodzaj'] ?? null,
                                'wojewodztwo_kod' => $wojewodztwoKod,
                                'powiat_kod' => $powiatKod,
                            ];
                        }
                    }
                    
                    // Sortuj alfabetycznie
                    usort($miejscowosci, function($a, $b) {
                        return strcmp($a['nazwa'] ?? '', $b['nazwa'] ?? '');
                    });
                    
                    return $miejscowosci;
                } catch (SoapFault $e) {
                    Log::error('TERYT API Error - getMiejscowosci', [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'wojewodztwo_kod' => $wojewodztwoKod,
                        'powiat_kod' => $powiatKod
                    ]);
                    return [];
                } catch (Exception $e) {
                    Log::error('TERYT Service Exception - getMiejscowosci', [
                        'message' => $e->getMessage(),
                        'wojewodztwo_kod' => $wojewodztwoKod,
                        'powiat_kod' => $powiatKod
                    ]);
                    return [];
                }
            });
            
            if (!empty($result)) {
                return $result;
            }
        } catch (\Exception $e) {
            Log::warning('TERYT SOAP failed for miejscowosci, using RSPO API fallback', [
                'message' => $e->getMessage()
            ]);
        }
        
        // Fallback: znajdź nazwę powiatu po kodzie i użyj RSPO API
        $wojewodztwa = $this->getWojewodztwa();
        foreach ($wojewodztwa as $woj) {
            if (($woj['kod'] ?? '') === $wojewodztwoKod) {
                $powiaty = $this->getPowiaty($wojewodztwoKod);
                foreach ($powiaty as $pow) {
                    if (($pow['kod'] ?? '') === $powiatKod) {
                        return $this->getMiejscowosciFromRSPO($pow['nazwa'], $powiatKod);
                    }
                }
                break;
            }
        }
        
        return [];
    }

    /**
     * Pobiera miejscowości dla województwa (bez filtrowania po powiecie)
     */
    public function getMiejscowosciByWojewodztwo(string $wojewodztwoKod): array
    {
        $cacheKey = "teryt_miejscowosci_woj_{$wojewodztwoKod}";
        
        return Cache::remember($cacheKey, 86400 * 7, function () use ($wojewodztwoKod) {
            try {
                $client = $this->getSoapClient();
                
                // Uwierzytelnienie
                $authResult = $client->__soapCall('CzyZalogowany', []);
                if (!$authResult) {
                    $client->__soapCall('Loguj', [
                        'nazwaUzytkownika' => $this->username,
                        'hasloUzytkownika' => $this->password
                    ]);
                }

                // Pobierz wszystkie powiaty w województwie
                $powiaty = $this->getPowiaty($wojewodztwoKod);
                $allMiejscowosci = [];
                
                foreach ($powiaty as $powiat) {
                    $miejscowosci = $this->getMiejscowosci($wojewodztwoKod, $powiat['kod']);
                    $allMiejscowosci = array_merge($allMiejscowosci, $miejscowosci);
                }
                
                // Usuń duplikaty po nazwie
                $unique = [];
                $seen = [];
                foreach ($allMiejscowosci as $miejsc) {
                    $key = $miejsc['nazwa'] ?? '';
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $unique[] = $miejsc;
                    }
                }
                
                return $unique;
            } catch (Exception $e) {
                Log::error('TERYT Service Exception - getMiejscowosciByWojewodztwo', [
                    'message' => $e->getMessage(),
                    'wojewodztwo_kod' => $wojewodztwoKod
                ]);
            }
            
            return [];
        });
    }
}





