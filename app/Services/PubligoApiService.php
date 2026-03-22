<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PubligoApiService
{
    private string $apiUrl;

    private string $apiKey;

    public function __construct()
    {
        $instanceUrl = config('services.publigo.instance_url', 'https://nowoczesna-edukacja.pl');
        $endpoint = config('services.publigo.wp_idea_endpoint', '/wp-json/wp-idea/v1');
        $this->apiUrl = $instanceUrl.$endpoint.'/orders';
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
            $token = md5($nonce.$this->apiKey);

            // Budowanie URL z parametrami autoryzacji
            $url = $this->apiUrl.'?nonce='.urlencode($nonce).'&token='.urlencode($token);

            // Wysłanie zapytania POST
            $response = Http::withOptions([
                'verify' => false, // Wyłączenie weryfikacji SSL dla Publigo API
                'timeout' => config('services.publigo.timeout', 30),
            ])->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $orderData);

            $httpCode = $response->status();
            $responseBody = $response->body();

            // Logowanie zapytania
            Log::info('Publigo API Request', [
                'url' => $url,
                'order_data' => $orderData,
                'http_code' => $httpCode,
                'response' => $responseBody,
            ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'error' => 'Błąd API: HTTP '.$httpCode,
                    'response' => $responseBody,
                    'http_code' => $httpCode,
                ];
            }

            // Parsowanie odpowiedzi (Publigo / WP REST bywa niespójny: wielkość liter, code zamiast status)
            $result = json_decode($responseBody, true);
            $interpreted = $this->interpretPubligoSuccess($result, $responseBody, $httpCode);

            if ($interpreted !== null) {
                return [
                    'success' => true,
                    'message' => $interpreted['message'],
                    'response' => $this->buildSuccessResponsePayload($result, $responseBody, $httpCode),
                    'http_code' => $httpCode,
                ];
            }

            // Publigo / wtyczka WP często zwraca HTTP 200 z „dziwnym” JSON (np. tylko ID zamówienia, pusty string, true).
            // Jeśli nie ma wyraźnych sygnałów błędu — traktujemy jak sukces, żeby nie mylić użytkownika i nie dublować wysyłki.
            if ($httpCode >= 200 && $httpCode < 300 && ! $this->responseIndicatesFailure($result, $responseBody)) {
                Log::info('Publigo API: HTTP 2xx bez typowego pola status/success — uznano za powodzenie (kompatybilność z API)', [
                    'http_code' => $httpCode,
                    'response_preview' => mb_substr($responseBody, 0, 500),
                    'json_decoded_type' => gettype($result),
                ]);

                return [
                    'success' => true,
                    'message' => 'Zamówienie zostało pomyślnie utworzone w Publigo',
                    'response' => $this->buildSuccessResponsePayload($result, $responseBody, $httpCode),
                    'http_code' => $httpCode,
                ];
            }

            Log::warning('Publigo API: HTTP sukces, ale odpowiedź wygląda na błąd lub jest nieczytelna', [
                'http_code' => $httpCode,
                'response_preview' => mb_substr($responseBody, 0, 2000),
                'json_decoded_type' => gettype($result),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd podczas tworzenia zamówienia (odpowiedź API niepotwierdzona). Sprawdź logi i Publigo.',
                'response' => is_array($result) ? $result : $responseBody,
                'http_code' => $httpCode,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Publigo API Connection Exception', [
                'message' => $e->getMessage(),
                'url' => $this->apiUrl,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd połączenia z Publigo API: '.$e->getMessage(),
                'response' => null,
                'http_code' => 0,
            ];
        } catch (Exception $e) {
            Log::error('Publigo API Exception', [
                'message' => $e->getMessage(),
                'url' => $this->apiUrl,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd połączenia: '.$e->getMessage(),
                'response' => null,
                'http_code' => 0,
            ];
        }
    }

    /**
     * Rozpoznaje sukces odpowiedzi Publigo (różne warianty pól / wielkość liter).
     *
     * @param  mixed  $result  Wynik json_decode(..., true) lub null przy błędzie JSON
     * @return array{message: string}|null null = traktuj jako błąd po stronie integracji
     */
    private function interpretPubligoSuccess(mixed $result, string $responseBody, int $httpCode): ?array
    {
        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        // JSON: true / liczba (np. ID zamówienia) / krótki string
        if ($result === false) {
            return null;
        }

        if ($result === true) {
            return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
        }

        if (is_int($result) || is_float($result)) {
            if ((float) $result > 0) {
                return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
            }

            return null;
        }

        if (is_string($result)) {
            $t = trim($result);
            if ($t === '') {
                return null;
            }
            $kind = $this->classifyPubligoStatusValue($t);
            if ($kind === 'ok') {
                return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
            }
            if ($kind === 'duplicate') {
                return ['message' => 'Zamówienie już istnieje w Publigo (duplikat)'];
            }
            if (is_numeric($t) && (float) $t > 0) {
                return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
            }

            return null;
        }

        if (is_array($result)) {
            // WP REST: { "code": "success", ... } lub podobne
            if (isset($result['code']) && is_string($result['code'])) {
                $code = strtolower(trim($result['code']));
                if (in_array($code, ['success', 'ok', 'rest_success', 'created'], true)) {
                    return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
                }
            }

            if (array_key_exists('success', $result) && $result['success'] === false) {
                return null;
            }

            if (array_key_exists('success', $result) && filter_var($result['success'], FILTER_VALIDATE_BOOLEAN)) {
                return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
            }

            // Pola z ID zamówienia (unikamy uznania dowolnego stringa za sukces)
            foreach (['order_id', 'orderId', 'id', 'wc_order_id'] as $idKey) {
                if ($this->looksLikePositiveOrderId($result[$idKey] ?? null)) {
                    return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
                }
            }

            foreach (['status', 'state', 'result'] as $key) {
                if (! array_key_exists($key, $result)) {
                    continue;
                }
                $kind = $this->classifyPubligoStatusValue($result[$key]);
                if ($kind === 'ok') {
                    return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
                }
                if ($kind === 'duplicate') {
                    return ['message' => 'Zamówienie już istnieje w Publigo (duplikat)'];
                }
            }

            // Zagnieżdżone: data.status
            if (isset($result['data']) && is_array($result['data'])) {
                foreach (['status', 'state'] as $key) {
                    if (! array_key_exists($key, $result['data'])) {
                        continue;
                    }
                    $kind = $this->classifyPubligoStatusValue($result['data'][$key]);
                    if ($kind === 'ok') {
                        return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
                    }
                    if ($kind === 'duplicate') {
                        return ['message' => 'Zamówienie już istnieje w Publigo (duplikat)'];
                    }
                }

                foreach (['order_id', 'orderId', 'id'] as $idKey) {
                    if ($this->looksLikePositiveOrderId($result['data'][$idKey] ?? null)) {
                        return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
                    }
                }
            }
        }

        // Pusty body HTTP 200 — norma dla części wdrożeń Publigo / WP (zamówienie i tak powstaje)
        if (trim($responseBody) === '') {
            return ['message' => 'Zamówienie zostało pomyślnie utworzone w Publigo'];
        }

        return null;
    }

    /**
     * Odpowiedź do panelu (np. „Pokaż szczegóły”) — bez mylącego {"raw":""}.
     *
     * @param  mixed  $result  json_decode(..., true)
     */
    private function buildSuccessResponsePayload(mixed $result, string $responseBody, int $httpCode): array
    {
        if (is_array($result) && $result !== []) {
            return $result;
        }

        if (trim($responseBody) === '') {
            return [
                'http_status' => $httpCode,
                'informacja' => 'Serwer Publigo zwrócił pustą treść odpowiedzi (HTTP '.$httpCode.'). To jest typowe dla tego endpointu — zamówienie zostało przyjęte.',
            ];
        }

        return ['surowa_odpowiedz' => $responseBody];
    }

    /**
     * Czy odpowiedź wygląda na błąd (wtedy NIE stosujemy optymistycznego HTTP 2xx).
     */
    private function responseIndicatesFailure(mixed $result, string $responseBody): bool
    {
        if ($result === false) {
            return true;
        }

        $trim = trim($responseBody);
        if ($trim !== '' && (stripos($trim, '<html') !== false || stripos($trim, '<!doctype') !== false)) {
            return true;
        }

        $trimLower = strtolower($trim);
        if ($trimLower === 'null' || $trimLower === 'false') {
            return true;
        }

        if (is_array($result)) {
            if (array_key_exists('success', $result) && $result['success'] === false) {
                return true;
            }

            if (! empty($result['error']) && is_string($result['error'])) {
                return true;
            }

            if (isset($result['errors']) && is_array($result['errors']) && count($result['errors']) > 0) {
                return true;
            }

            // WordPress REST: błąd z kodem HTTP w data.status (np. 400, 403)
            if (isset($result['data']['status']) && is_numeric($result['data']['status']) && (int) $result['data']['status'] >= 400) {
                return true;
            }

            // Typowy kształt błędu WP: code zaczyna się od rest_
            if (isset($result['code']) && is_string($result['code'])) {
                $c = strtolower($result['code']);
                if (str_starts_with($c, 'rest_') && ! in_array($c, ['rest_success'], true)) {
                    return true;
                }
            }
        }

        // Nie-JSON lub uszkodzony JSON przy niepustym ciele — ostrożnie
        if ($result === null && $trim !== '') {
            if (str_starts_with($trim, '{') || str_starts_with($trim, '[')) {
                return true;
            }
        }

        return false;
    }

    private function looksLikePositiveOrderId(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value > 0;
        }

        if (is_string($value)) {
            return (bool) preg_match('/^[1-9]\d*$/', trim($value));
        }

        return false;
    }

    /**
     * @return 'ok'|'duplicate'|null
     */
    private function classifyPubligoStatusValue(mixed $value): ?string
    {
        if ($value === true || $value === 1) {
            return 'ok';
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $s = strtolower(trim(str_replace(['_', '-'], ' ', (string) $value)));

        $okTokens = ['ok', 'success', 'successful', 'completed', 'complete', 'done', 'created'];
        foreach ($okTokens as $token) {
            if ($s === $token) {
                return 'ok';
            }
        }

        $duplicateTokens = [
            'already processed',
            'alreadyprocessed',
            'duplicate',
            'exists',
            'order exists',
            'already exists',
        ];
        foreach ($duplicateTokens as $token) {
            if ($s === $token || str_contains($s, $token)) {
                return 'duplicate';
            }
        }

        // np. "Already Processed" → już znormalizowane spacje
        if (str_contains($s, 'already') && str_contains($s, 'process')) {
            return 'duplicate';
        }

        return null;
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
                'url' => 'https://adm.pnedu.pl',
            ],
            'options' => [
                'disable_receipt' => false,
            ],
            'products' => [
                $zamowienie->idProdPubligo => [
                    'price_id' => (int) $zamowienie->price_idProdPubligo,
                ],
            ],
            'customer' => [
                'email' => $zamowienie->konto_email ?? '',
                'first_name' => $firstName,
                'last_name' => $lastName,
            ],
            'shipping_address' => [
                'address1' => $zamowienie->odb_adres ?? '',
                'address2' => '',
                'zip_code' => $zamowienie->odb_kod ?? '',
                'city' => $zamowienie->odb_poczta ?? '',
                'country_code' => 'PL',
            ],
        ];
    }
}
