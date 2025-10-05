<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PubligoApiService
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $instanceUrl = config('services.publigo.instance_url', 'https://nowoczesna-edukacja.pl');
        $endpoint = config('services.publigo.wp_idea_endpoint', '/wp-json/wp-idea/v1');
        $this->apiUrl = $instanceUrl . $endpoint . '/orders';
        $this->apiKey = config('services.publigo.api_key');
    }

    /**
     * Tworzy zamówienie w Publigo API
     */
    public function createOrder(array $orderData): array
    {
        try {
            // Generowanie nonce i token
            $nonce = uniqid();
            $token = md5($nonce . $this->apiKey);

            // Budowanie URL z parametrami autoryzacji
            $url = $this->apiUrl . '?nonce=' . urlencode($nonce) . '&token=' . urlencode($token);

            // Wysłanie zapytania POST
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $orderData);

            $httpCode = $response->status();
            $responseBody = $response->body();

            // Logowanie zapytania
            Log::info('Publigo API Request', [
                'url' => $url,
                'order_data' => $orderData,
                'http_code' => $httpCode,
                'response' => $responseBody
            ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'error' => 'Błąd API: HTTP ' . $httpCode,
                    'response' => $responseBody,
                    'http_code' => $httpCode
                ];
            }

            // Parsowanie odpowiedzi
            $result = json_decode($responseBody, true);
            
            if (isset($result['status']) && ($result['status'] === 'ok' || $result['status'] === 'already processed')) {
                $message = $result['status'] === 'ok' 
                    ? 'Zamówienie zostało pomyślnie utworzone w Publigo'
                    : 'Zamówienie już istnieje w Publigo (duplikat)';
                    
                return [
                    'success' => true,
                    'message' => $message,
                    'response' => $result,
                    'http_code' => $httpCode
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Błąd podczas tworzenia zamówienia',
                    'response' => $result,
                    'http_code' => $httpCode
                ];
            }

        } catch (Exception $e) {
            Log::error('Publigo API Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Błąd połączenia: ' . $e->getMessage(),
                'response' => null,
                'http_code' => 0
            ];
        }
    }

    /**
     * Przygotowuje dane zamówienia w formacie wymaganym przez Publigo API
     */
    public function prepareOrderData($zamowienie): array
    {
        // Parsowanie imienia i nazwiska
        $fullName = $zamowienie->konto_imie_nazwisko ?? '';
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        return [
            'source' => [
                'platform' => 'Platforma Nowoczesnej Edukacji',
                'id' => $zamowienie->id, // Bezpośrednio ID z bazy - zapobiega duplikatom
                'url' => 'https://adm.pnedu.pl'
            ],
            'options' => [
                'disable_receipt' => false
            ],
            'products' => [
                $zamowienie->idProdPubligo => [
                    'price_id' => (int)$zamowienie->price_idProdPubligo
                ]
            ],
            'customer' => [
                'email' => $zamowienie->konto_email ?? '',
                'first_name' => $firstName,
                'last_name' => $lastName
            ],
            'shipping_address' => [
                'address1' => $zamowienie->odb_adres ?? '',
                'address2' => '',
                'zip_code' => $zamowienie->odb_kod ?? '',
                'city' => $zamowienie->odb_poczta ?? '',
                'country_code' => 'PL'
            ]
        ];
    }
}
