<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * IfirmaApiService - Serwis do integracji z API iFirma.pl
 * 
 * Dokumentacja: https://api.ifirma.pl/
 * Autoryzacja: HMAC-SHA1
 */
class IfirmaApiService
{
    private string $login;
    private string $baseUrl;
    private string $apiEndpoint;
    private array $keys;
    private int $timeout;

    public function __construct()
    {
        $this->login = config('services.ifirma.login', '');
        $this->baseUrl = config('services.ifirma.base_url', 'https://www.ifirma.pl');
        $this->apiEndpoint = config('services.ifirma.api_endpoint', '/iapi');
        $this->keys = config('services.ifirma.keys', []);
        $this->timeout = config('services.ifirma.timeout', 30);
    }

    /**
     * Generuje nagłówek autoryzacji zgodnie z dokumentacją iFirma
     * 
     * @param string $url Pełny URL żądania (może zawierać parametry query)
     * @param string $keyName Nazwa klucza (faktura, rachunek, abonent, wydatek, mobilny)
     * @param string $requestContent Zawartość żądania w JSON (dla POST) lub pusty string (dla GET)
     * @return string Nagłówek autoryzacji
     */
    private function generateAuthHeader(string $url, string $keyName, string $requestContent = ''): string
    {
        $key = $this->keys[$keyName] ?? '';
        
        if (empty($key)) {
            throw new Exception("Brak klucza autoryzacji dla: {$keyName}");
        }

        // Usuń parametry query z URL dla hash'a (zgodnie z dokumentacją)
        // API iFirma oczekuje URL bez parametrów query dla obliczenia hash'a
        $parsedUrl = parse_url($url);
        $urlForHash = '';
        
        if (isset($parsedUrl['scheme'])) {
            $urlForHash .= $parsedUrl['scheme'] . '://';
        }
        if (isset($parsedUrl['host'])) {
            $urlForHash .= $parsedUrl['host'];
        }
        if (isset($parsedUrl['port'])) {
            $urlForHash .= ':' . $parsedUrl['port'];
        }
        if (isset($parsedUrl['path'])) {
            $urlForHash .= $parsedUrl['path'];
        }
        // NIE dodajemy query (parametrów GET) ani fragment do hash'a

        // Konwertuj klucz hex na binarny
        $keyBinary = hex2bin($key);
        
        // Generuj hash HMAC-SHA1 zgodnie z dokumentacją:
        // hashWiadomosci = hmac(klucz, url + nazwaUsera + nazwaKlucza + requestContent)
        // Gdzie url jest BEZ parametrów query
        $dataToHash = $urlForHash . $this->login . $keyName . $requestContent;
        
        // Logowanie dla debugowania (tylko w trybie debug)
        Log::debug('iFirma Auth Hash Calculation', [
            'url_for_hash' => $urlForHash,
            'login' => $this->login,
            'key_name' => $keyName,
            'request_content_length' => strlen($requestContent),
            'data_to_hash_length' => strlen($dataToHash)
        ]);
        
        $hash = hash_hmac('sha1', $dataToHash, $keyBinary);
        
        // Format nagłówka: IAPIS user=LOGIN, hmac-sha1=HASH
        return 'IAPIS user=' . $this->login . ', hmac-sha1=' . $hash;
    }

    /**
     * Wykonuje żądanie GET do API iFirma
     * 
     * @param string $endpoint Endpoint API (np. 'fakturakraj/list.json')
     * @param string $keyName Nazwa klucza autoryzacji
     * @param array $params Parametry zapytania
     * @return array Wynik żądania
     */
    public function get(string $endpoint, string $keyName = 'faktura', array $params = []): array
    {
        try {
            $fullUrl = $this->baseUrl . $this->apiEndpoint . '/' . $endpoint;
            
            // Dodaj parametry do URL
            if (!empty($params)) {
                $fullUrl .= '?' . http_build_query($params);
            }

            // Generuj nagłówek autoryzacji
            $authHeader = $this->generateAuthHeader($fullUrl, $keyName, '');

            Log::info('iFirma API Request (GET)', [
                'url' => $fullUrl,
                'key_name' => $keyName,
                'endpoint' => $endpoint
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authentication' => $authHeader,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get($fullUrl);

            $statusCode = $response->status();
            $body = $response->body();
            
            // Próba parsowania JSON
            $jsonData = null;
            if ($response->successful()) {
                $jsonData = $response->json();
            }

            if ($response->successful()) {
                Log::info('iFirma API Response (Success)', [
                    'status' => $statusCode,
                    'endpoint' => $endpoint
                ]);

                return [
                    'status' => 'success',
                    'status_code' => $statusCode,
                    'data' => $jsonData,
                    'raw_response' => $body
                ];
            } else {
                Log::error('iFirma API Response (Error)', [
                    'status' => $statusCode,
                    'endpoint' => $endpoint,
                    'response' => $body
                ]);

                return [
                    'status' => 'error',
                    'status_code' => $statusCode,
                    'message' => $this->parseErrorMessage($body),
                    'raw_response' => $body
                ];
            }
        } catch (Exception $e) {
            Log::error('iFirma API Exception', [
                'message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'trace' => substr($e->getTraceAsString(), 0, 1000)
            ]);

            return [
                'status' => 'exception',
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Wykonuje żądanie POST do API iFirma
     * 
     * @param string $endpoint Endpoint API
     * @param array $data Dane do wysłania
     * @param string $keyName Nazwa klucza autoryzacji
     * @return array Wynik żądania
     */
    public function post(string $endpoint, array $data, string $keyName = 'faktura'): array
    {
        try {
            // Jeśli endpoint nie ma rozszerzenia, dodaj .json
            if (!str_ends_with($endpoint, '.json')) {
                $endpoint .= '.json';
            }
            $fullUrl = $this->baseUrl . $this->apiEndpoint . '/' . $endpoint;
            
            // JSON dla hash'a - ważne: musi być identyczny z tym wysyłanym w body
            // API iFirma oczekuje dokładnie takiego samego JSON w hash'u jak w body
            // Używamy JSON_UNESCAPED_UNICODE dla polskich znaków i JSON_UNESCAPED_SLASHES
            // JSON_PRESERVE_ZERO_FRACTION - zachowuje 1.0 zamiast 1 (ważne dla API iFirma)
            // BEZ JSON_PRETTY_PRINT - ważne dla hash'a
            $requestContent = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
            
            // Normalizuj znaki nowej linii w polach tekstowych dla zgodności
            // API iFirma może oczekiwać konkretnego formatu
            $requestContent = str_replace(["\r\n", "\r"], "\n", $requestContent);
            
            Log::debug('iFirma POST Request Content', [
                'url' => $fullUrl,
                'request_content' => $requestContent,
                'request_length' => strlen($requestContent),
                'request_content_hash' => md5($requestContent)
            ]);

            // Generuj nagłówek autoryzacji (z zawartością żądania)
            $authHeader = $this->generateAuthHeader($fullUrl, $keyName, $requestContent);

            Log::info('iFirma API Request (POST)', [
                'url' => $fullUrl,
                'key_name' => $keyName,
                'endpoint' => $endpoint,
                'auth_header' => $authHeader
            ]);

            // Wysyłamy JSON jako surowy string, aby był identyczny z użytym w hash'u
            // Laravel Http->post() z array może modyfikować JSON, więc używamy body()
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authentication' => $authHeader,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->withBody($requestContent, 'application/json')
                ->post($fullUrl);

            $statusCode = $response->status();
            $body = $response->body();
            
            $jsonData = null;
            if ($response->successful()) {
                $jsonData = $response->json();
            }

            // Sprawdź czy odpowiedź zawiera błąd w strukturze JSON (iFirma zwraca 200 nawet przy błędach)
            // WAŻNE: iFirma może zwrócić Kod=200 nawet przy błędach walidacji!
            // Musimy sprawdzać również pole 'Informacja' - jeśli zawiera błąd, traktujemy to jako błąd
            $hasErrorInResponse = false;
            $errorMessage = null;
            
            if ($jsonData !== null && isset($jsonData['response'])) {
                $apiResponse = $jsonData['response'];
                
                // Sprawdź kod błędu (różny od 0 i 200)
                if (isset($apiResponse['Kod']) && $apiResponse['Kod'] != 0 && $apiResponse['Kod'] != 200) {
                    $hasErrorInResponse = true;
                    $errorMessage = $apiResponse['Informacja'] ?? 'Błąd API iFirma';
                }
                // Sprawdź czy w Informacja jest komunikat błędu (nawet jeśli Kod=200)
                elseif (isset($apiResponse['Informacja']) && 
                        (stripos($apiResponse['Informacja'], 'błąd') !== false || 
                         stripos($apiResponse['Informacja'], 'niepoprawna') !== false ||
                         stripos($apiResponse['Informacja'], 'nie można') !== false)) {
                    $hasErrorInResponse = true;
                    $errorMessage = $apiResponse['Informacja'];
                }
            }

            if ($response->successful() && !$hasErrorInResponse) {
                Log::info('iFirma API Response (Success)', [
                    'status' => $statusCode,
                    'endpoint' => $endpoint,
                    'response_data' => $jsonData
                ]);

                return [
                    'status' => 'success',
                    'status_code' => $statusCode,
                    'data' => $jsonData,
                    'raw_response' => $body
                ];
            } else {
                // Jeśli HTTP 200 ale błąd w odpowiedzi JSON lub błąd HTTP
                $errorMsg = $hasErrorInResponse ? $errorMessage : $this->parseErrorMessage($body);
                $logStatus = $hasErrorInResponse ? 'warning' : 'error';
                
                Log::$logStatus('iFirma API Response (Error in JSON or HTTP)', [
                    'status' => $statusCode,
                    'endpoint' => $endpoint,
                    'error_in_json' => $hasErrorInResponse,
                    'response' => $body,
                    'parsed_json' => $jsonData
                ]);

                return [
                    'status' => 'error',
                    'status_code' => $hasErrorInResponse ? ($jsonData['response']['Kod'] ?? $statusCode) : $statusCode,
                    'message' => $errorMsg,
                    'raw_response' => $body,
                    'parsed_data' => $jsonData
                ];
            }
        } catch (Exception $e) {
            Log::error('iFirma API Exception', [
                'message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'trace' => substr($e->getTraceAsString(), 0, 1000)
            ]);

            return [
                'status' => 'exception',
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Testuje połączenie z API iFirma poprzez próbę pobrania listy faktur
     * 
     * @return array Wynik testu połączenia
     */
    public function testConnection(): array
    {
        // Sprawdź konfigurację
        if (empty($this->login)) {
            return [
                'status' => 'config_error',
                'message' => 'Brak skonfigurowanego loginu w konfiguracji'
            ];
        }

        if (empty($this->keys['faktura'])) {
            return [
                'status' => 'config_error',
                'message' => 'Brak skonfigurowanego klucza autoryzacji dla faktur'
            ];
        }

        // Test: pobierz listę faktur (limit=1 dla testu)
        // Uwaga: sprawdzamy różne formaty endpointu - w zależności od wersji API może być z lub bez .json
        $result = $this->get('fakturakraj/list', 'faktura', ['limit' => 1]);
        
        // Jeśli pierwsza próba nie powiodła się, spróbuj z .json
        if ($result['status'] !== 'success' && $result['status_code'] === 404) {
            $result = $this->get('fakturakraj/list.json', 'faktura', ['limit' => 1]);
        }

        if ($result['status'] === 'success') {
            return [
                'status' => 'success',
                'message' => 'Połączenie z API iFirma.pl działa poprawnie',
                'status_code' => $result['status_code'],
                'data' => $result['data'] ?? null
            ];
        } else {
            return [
                'status' => $result['status'],
                'message' => $result['message'] ?? 'Nie udało się połączyć z API iFirma.pl',
                'status_code' => $result['status_code'] ?? null,
                'details' => $result['raw_response'] ?? null
            ];
        }
    }

    /**
     * Pobiera listę faktur krajowych
     * 
     * @param int $limit Limit wyników
     * @param int $offset Offset
     * @return array Wynik żądania
     */
    public function getInvoices(int $limit = 10, int $offset = 0): array
    {
        return $this->get('fakturakraj/list.json', 'faktura', [
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Pobiera szczegóły faktury
     * 
     * @param int $invoiceId ID faktury
     * @return array Wynik żądania
     */
    public function getInvoice(int $invoiceId): array
    {
        return $this->get("fakturakraj/{$invoiceId}.json", 'faktura');
    }

    /**
     * Wystawia fakturę pro forma
     * 
     * @param array $invoiceData Dane faktury (Kontrahent, Pozycje, etc.)
     * @return array Wynik żądania
     */
    public function createProFormaInvoice(array $invoiceData): array
    {
        // Sprawdź konfigurację
        if (empty($this->keys['faktura'])) {
            return [
                'status' => 'config_error',
                'message' => 'Brak skonfigurowanego klucza autoryzacji dla faktur'
            ];
        }

        // Endpoint dla faktury pro forma krajowej
        // API iFirma używa endpointu: fakturaproformakraj.json dla faktur pro forma
        // Uwaga: może też działać fakturaproforma.json (bez "kraj") - w zależności od typu
        
        // Próba 1: fakturaproformakraj.json (dla faktur pro forma krajowych)
        $result = $this->post('fakturaproformakraj.json', $invoiceData, 'faktura');
        
        // Jeśli otrzymaliśmy błąd o niepoprawnej zawartości, spróbuj alternatywny endpoint
        if ($result['status'] === 'error' && 
            isset($result['parsed_data']['response']['Kod']) && 
            $result['parsed_data']['response']['Kod'] == 200 &&
            str_contains($result['message'] ?? '', 'Niepoprawna zawartość')) {
            
            Log::info('iFirma: Próba alternatywnego endpointu dla faktury pro forma');
            // Próba 2: fakturaproforma.json (bez "kraj")
            $result = $this->post('fakturaproforma.json', $invoiceData, 'faktura');
        }
        
        return $result;
    }

    /**
     * Pobiera szczegóły faktury pro forma po Identyfikatorze
     * 
     * @param string|int $invoiceId Identyfikator faktury (z odpowiedzi po wystawieniu)
     * @return array Wynik żądania zawierający m.in. PelnyNumer faktury
     */
    public function getProFormaInvoice($invoiceId): array
    {
        return $this->get("fakturaproformakraj/{$invoiceId}.json", 'faktura');
    }

    /**
     * Wystawia fakturę krajową (nie pro-forma) przez API iFirma
     * Dokumentacja: https://api.ifirma.pl/wystawianie-faktury-krajowej/
     * 
     * @param array $invoiceData Dane faktury
     * @return array Wynik żądania
     */
    public function createInvoice(array $invoiceData): array
    {
        return $this->post('fakturakraj.json', $invoiceData, 'faktura');
    }

    /**
     * Wysyła fakturę pro forma e-mailem do kontrahenta
     * 
     * @param string|int $invoiceId Identyfikator faktury
     * @param string $recipientEmail Adres e-mail odbiorcy
     * @param string $invoiceNumber Numer faktury (dla tekstu wiadomości)
     * @param string $orderNumber Numer zamówienia (dla tekstu wiadomości)
     * @return array Wynik żądania
     */
    public function sendProFormaByEmail($invoiceId, string $recipientEmail, string $invoiceNumber = '', string $orderNumber = ''): array
    {
        return $this->sendInvoiceByEmail($invoiceId, $recipientEmail, $invoiceNumber, $orderNumber, 'proforma');
    }

    /**
     * Wysyła fakturę krajową e-mailem do kontrahenta
     * 
     * @param string|int $invoiceId Identyfikator faktury
     * @param string $recipientEmail Adres e-mail odbiorcy
     * @param string $invoiceNumber Numer faktury (dla tekstu wiadomości)
     * @param string $orderNumber Numer zamówienia (dla tekstu wiadomości)
     * @return array Wynik żądania
     */
    public function sendInvoiceByEmail($invoiceId, string $recipientEmail, string $invoiceNumber = '', string $orderNumber = '', string $type = 'invoice'): array
    {
        // Sprawdź konfigurację
        if (empty($this->keys['faktura'])) {
            return [
                'status' => 'config_error',
                'message' => 'Brak skonfigurowanego klucza autoryzacji dla faktur'
            ];
        }

        // Sprawdź czy jest skonfigurowany adres nadawcy (WYMAGANE przez iFirma API)
        $senderEmail = config('services.ifirma.sender_email', '');
        if (empty($senderEmail)) {
            return [
                'status' => 'config_error',
                'message' => 'Brak skonfigurowanego adresu e-mail nadawcy (IFIRMA_SENDER_EMAIL w .env). Adres musi być wcześniej dodany w ustawieniach iFirma.'
            ];
        }

        // Walidacja adresu e-mail odbiorcy
        $recipientEmail = strtolower(trim($recipientEmail));
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'error',
                'message' => "Nieprawidłowy adres e-mail odbiorcy: {$recipientEmail}"
            ];
        }

        // Przygotowanie tekstu wiadomości
        $invoiceType = $type === 'proforma' ? 'fakturę pro forma' : 'fakturę';
        $messageText = "W załączeniu przesyłamy {$invoiceType}";
        if (!empty($invoiceNumber)) {
            $messageText .= " nr {$invoiceNumber}";
        }
        if (!empty($orderNumber)) {
            $messageText .= " dotyczącą zamówienia nr {$orderNumber}";
        }
        $messageText .= ".";

        // Przygotowanie danych do wysyłki zgodnie z dokumentacją iFirma API
        // https://api.ifirma.pl/wysylanie-faktur-poczta-tradycyjna-oraz-na-adres-e-mail-kontrahenta/
        $emailData = [
            'Tekst' => $messageText,
            'Przelew' => true,
            'Pobranie' => false,
            'SkrzynkaEmail' => $senderEmail, // WYMAGANE - adres skonfigurowany w iFirma
            'SkrzynkaEmailOdbiorcy' => $recipientEmail
        ];

        Log::info("iFirma: Wysyłanie faktury ({$type}) e-mailem", [
            'invoice_id' => $invoiceId,
            'sender' => $senderEmail,
            'recipient' => $recipientEmail,
            'invoice_number' => $invoiceNumber,
            'type' => $type
        ]);

        // Endpoint do wysyłki faktury
        $endpoint = $type === 'proforma' 
            ? "fakturaproformakraj/send/{$invoiceId}.json"
            : "fakturakraj/send/{$invoiceId}.json";
        
        return $this->post($endpoint, $emailData, 'faktura');
    }

    /**
     * Wysyła fakturę do KSeF (Krajowy System e-Faktur)
     * 
     * @param string|int $invoiceId Identyfikator faktury
     * @param string $invoiceType Typ faktury (domyślnie 'fakturakraj')
     * @return array Wynik żądania zawierający m.in. numer KSeF
     */
    public function sendInvoiceToKsef($invoiceId, string $invoiceType = 'fakturakraj'): array
    {
        // Sprawdź konfigurację
        if (empty($this->keys['faktura'])) {
            return [
                'status' => 'config_error',
                'message' => 'Brak skonfigurowanego klucza autoryzacji dla faktur'
            ];
        }

        // Mapowanie typów faktur do endpointów KSeF zgodnie z dokumentacją API iFirma
        $ksefEndpoints = [
            'fakturakraj' => 'fakturakraj',
            'fakturakraj/korekta' => 'fakturakraj',
            'fakturaproformakraj' => 'fakturaproformakraj',
            'fakturawysylka' => 'fakturawysylka',
            'fakturawaluta' => 'fakturawaluta',
            'fakturaeksporttowarow' => 'fakturaeksporttowarow',
            'fakturaeksportuslug' => 'fakturaeksportuslug',
            'fakturaeksportuslugue' => 'fakturaeksportuslugue',
            'fakturawdt' => 'fakturawdt',
            'fakturakoncowa' => 'fakturakoncowa',
            'fakturazaliczka' => 'fakturazaliczka',
            'fakturakoncowawaluta' => 'fakturakoncowawaluta',
            'fakturazaliczkowawaluta' => 'fakturazaliczkowawaluta',
            'fakturaparagon' => 'fakturaparagon',
            'fakturametodakasowa' => 'fakturametodakasowa',
            'fakturasrodektrwaly' => 'fakturasrodektrwaly',
            'fakturawyposazenie' => 'fakturawyposazenie',
            'fakturaszczegolnyobowiazek' => 'fakturaszczegolnyobowiazek',
            'fakturaoss' => 'fakturaoss',
        ];

        // Użyj domyślnego typu jeśli nie znaleziono w mapowaniu
        $endpointType = $ksefEndpoints[$invoiceType] ?? 'fakturakraj';

        // Przygotowanie danych do wysyłki zgodnie z dokumentacją iFirma API
        // https://api.ifirma.pl/wysylanie-faktury-do-ksef/
        $ksefData = [
            'DataWysylki' => null
        ];

        // Konwersja identyfikatora - jeśli zawiera ukośniki, zamień na podkreślenia
        $ksefInvoiceId = is_string($invoiceId) && str_contains($invoiceId, '/') 
            ? str_replace('/', '_', $invoiceId) 
            : $invoiceId;

        Log::info('iFirma: Wysyłanie faktury do KSeF', [
            'invoice_id' => $invoiceId,
            'ksef_invoice_id' => $ksefInvoiceId,
            'invoice_type' => $invoiceType,
            'endpoint_type' => $endpointType,
            'ksef_data' => $ksefData
        ]);

        // Endpoint do wysyłki faktury do KSeF
        $endpoint = "{$endpointType}/ksef/send/{$ksefInvoiceId}.json";
        
        $result = $this->post($endpoint, $ksefData, 'faktura');

        // Logowanie odpowiedzi z pełną strukturą
        if ($result['status'] === 'success') {
            Log::info('iFirma: Faktura przesłana do KSeF - pełna odpowiedź', [
                'invoice_id' => $invoiceId,
                'status' => $result['status'],
                'status_code' => $result['status_code'] ?? null,
                'response_data' => $result['data'] ?? null,
                'response_data_json' => isset($result['data']) ? json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
                'raw_response' => $result['raw_response'] ?? null,
            ]);
        } else {
            Log::error('iFirma: Błąd przesyłania faktury do KSeF', [
                'invoice_id' => $invoiceId,
                'error' => $result['message'] ?? 'Nieznany błąd',
                'status' => $result['status'],
                'status_code' => $result['status_code'] ?? null,
                'response' => $result
            ]);
        }

        return $result;
    }

    /**
     * Parsuje komunikat błędu z odpowiedzi API
     * 
     * @param string $response Raw response body
     * @return string Przetworzony komunikat błędu
     */
    private function parseErrorMessage(string $response): string
    {
        // Próba parsowania JSON
        $json = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($json['response'])) {
            if (isset($json['response']['Kod'])) {
                return $json['response']['Kod'] . ': ' . ($json['response']['Informacja'] ?? 'Brak szczegółów');
            }
            
            if (isset($json['response']['Informacja'])) {
                return $json['response']['Informacja'];
            }
        }

        return 'Błąd komunikacji z API iFirma.pl';
    }
}

