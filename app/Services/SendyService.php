<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SendyService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('sendy.api_key');
        $this->baseUrl = config('sendy.base_url');
    }

    /**
     * Pobiera wszystkie marki (brands) z Sendy
     */
    public function getBrands(): array
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/api/brands/get-brands.php', [
                'api_key' => $this->apiKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data ?? [];
            }

            Log::error('Sendy API Error - getBrands', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Sendy Service Exception - getBrands', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Pobiera listy mailingowe dla określonej marki
     */
    public function getLists(string $brandId, bool $includeHidden = false): array
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/api/lists/get-lists.php', [
                'api_key' => $this->apiKey,
                'brand_id' => $brandId,
                'include_hidden' => $includeHidden ? 'yes' : 'no'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data ?? [];
            }

            Log::error('Sendy API Error - getLists', [
                'status' => $response->status(),
                'body' => $response->body(),
                'brand_id' => $brandId
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Sendy Service Exception - getLists', [
                'message' => $e->getMessage(),
                'brand_id' => $brandId,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Pobiera wszystkie listy ze wszystkich marek
     */
    public function getAllLists(): array
    {
        $allLists = [];
        $brands = $this->getBrands();

        foreach ($brands as $brand) {
            $brandId = $brand['id'] ?? null;
            if ($brandId) {
                $lists = $this->getLists($brandId);
                foreach ($lists as $list) {
                    $list['brand_name'] = $brand['name'] ?? 'Unknown Brand';
                    $list['brand_id'] = $brandId;
                    $allLists[] = $list;
                }
            }
        }

        return $allLists;
    }

    /**
     * Pobiera liczbę aktywnych subskrybentów dla listy
     */
    public function getActiveSubscriberCount(string $listId): int
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/api/subscribers/active-subscriber-count.php', [
                'api_key' => $this->apiKey,
                'list_id' => $listId
            ]);

            if ($response->successful()) {
                $count = (int) $response->body();
                return $count;
            }

            Log::error('Sendy API Error - getActiveSubscriberCount', [
                'status' => $response->status(),
                'body' => $response->body(),
                'list_id' => $listId
            ]);

            return 0;
        } catch (Exception $e) {
            Log::error('Sendy Service Exception - getActiveSubscriberCount', [
                'message' => $e->getMessage(),
                'list_id' => $listId,
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Sprawdza status subskrypcji użytkownika
     */
    public function getSubscriptionStatus(string $email, string $listId): string
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/api/subscribers/subscription-status.php', [
                'api_key' => $this->apiKey,
                'email' => $email,
                'list_id' => $listId
            ]);

            if ($response->successful()) {
                return $response->body();
            }

            Log::error('Sendy API Error - getSubscriptionStatus', [
                'status' => $response->status(),
                'body' => $response->body(),
                'email' => $email,
                'list_id' => $listId
            ]);

            return 'Unknown';
        } catch (Exception $e) {
            Log::error('Sendy Service Exception - getSubscriptionStatus', [
                'message' => $e->getMessage(),
                'email' => $email,
                'list_id' => $listId,
                'trace' => $e->getTraceAsString()
            ]);
            return 'Unknown';
        }
    }

    /**
     * Dodaje subskrybenta do listy
     */
    public function subscribe(string $email, string $listId, array $additionalData = []): bool
    {
        try {
            $data = array_merge([
                'api_key' => $this->apiKey,
                'email' => $email,
                'list' => $listId,
                'boolean' => 'true'
            ], $additionalData);

            $response = Http::asForm()->post($this->baseUrl . '/subscribe', $data);

            if ($response->successful()) {
                $result = trim($response->body());
                
                // Sendy zwraca różne odpowiedzi:
                // 'true' - sukces
                // 'Already subscribed.' - email już jest na liście (to też sukces)
                // 'Bounced email address.' - email odbija (błąd)
                // 'Email is suppressed.' - email zablokowany (błąd)
                // Inne komunikaty błędów
                
                // Loguj wszystkie odpowiedzi dla debugowania (tylko pierwsze 10)
                static $logCount = 0;
                if ($logCount < 10) {
                    Log::info('Sendy API Response - subscribe', [
                        'response' => $result,
                        'response_length' => strlen($result),
                        'email' => $email,
                        'list_id' => $listId
                    ]);
                    $logCount++;
                }
                
                if ($result === 'true' || $result === 'Already subscribed.') {
                    return true;
                }
                
                // Jeśli to błąd, zaloguj szczegóły
                Log::warning('Sendy API Warning - subscribe', [
                    'response' => $result,
                    'email' => $email,
                    'list_id' => $listId,
                    'additional_data' => $additionalData
                ]);
                
                return false;
            }

            Log::error('Sendy API Error - subscribe', [
                'status' => $response->status(),
                'body' => $response->body(),
                'email' => $email,
                'list_id' => $listId
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Sendy Service Exception - subscribe', [
                'message' => $e->getMessage(),
                'email' => $email,
                'list_id' => $listId,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Usuwa subskrybenta z listy
     */
    public function unsubscribe(string $email, string $listId): bool
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/unsubscribe', [
                'email' => $email,
                'list' => $listId,
                'boolean' => 'true'
            ]);

            if ($response->successful()) {
                $result = $response->body();
                return $result === 'true';
            }

            Log::error('Sendy API Error - unsubscribe', [
                'status' => $response->status(),
                'body' => $response->body(),
                'email' => $email,
                'list_id' => $listId
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Sendy Service Exception - unsubscribe', [
                'message' => $e->getMessage(),
                'email' => $email,
                'list_id' => $listId,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Usuwa subskrybenta z listy (pełne usunięcie)
     */
    public function deleteSubscriber(string $email, string $listId): bool
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/api/subscribers/delete.php', [
                'api_key' => $this->apiKey,
                'list_id' => $listId,
                'email' => $email
            ]);

            if ($response->successful()) {
                $result = $response->body();
                return $result === 'true';
            }

            Log::error('Sendy API Error - deleteSubscriber', [
                'status' => $response->status(),
                'body' => $response->body(),
                'email' => $email,
                'list_id' => $listId
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Sendy Service Exception - deleteSubscriber', [
                'message' => $e->getMessage(),
                'email' => $email,
                'list_id' => $listId,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Tworzy nową listę w Sendy
     */
    public function createList(
        string $brandId,
        string $listName,
        string $fromName,
        string $fromEmail,
        string $replyTo,
        string $subject = 'Newsletter'
    ): ?string {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/api/lists/create-list.php', [
                'api_key' => $this->apiKey,
                'brand_id' => $brandId,
                'list_name' => $listName,
                'from_name' => $fromName,
                'from_email' => $fromEmail,
                'reply_to' => $replyTo,
                'subject' => $subject
            ]);

            if ($response->successful()) {
                $result = $response->body();
                
                // Sendy zwraca ID listy jako string lub JSON z błędem
                if (is_numeric($result) || (is_string($result) && strlen($result) > 0 && $result !== 'true' && $result !== 'false')) {
                    return $result;
                }
                
                // Sprawdź czy to JSON z błędem
                $jsonResult = json_decode($result, true);
                if ($jsonResult && isset($jsonResult['error'])) {
                    Log::error('Sendy API Error - createList', [
                        'error' => $jsonResult['error'],
                        'list_name' => $listName,
                        'brand_id' => $brandId
                    ]);
                    return null;
                }
            }

            Log::error('Sendy API Error - createList', [
                'status' => $response->status(),
                'body' => $response->body(),
                'list_name' => $listName,
                'brand_id' => $brandId
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Sendy Service Exception - createList', [
                'message' => $e->getMessage(),
                'list_name' => $listName,
                'brand_id' => $brandId,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Masowe dodawanie subskrybentów do listy
     */
    public function bulkSubscribe(string $listId, array $subscribers): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($subscribers as $subscriber) {
            $email = $subscriber['email'] ?? null;
            $name = $subscriber['name'] ?? null;
            $rspo = $subscriber['rspo'] ?? null;

            if (!$email) {
                $results['failed']++;
                $results['errors'][] = "Brak adresu email dla: " . ($name ?? 'nieznana szkoła');
                continue;
            }

            $additionalData = [];
            if ($name) {
                $additionalData['name'] = $name;
            }
            
            // Dodaj custom field RSPO jeśli istnieje
            // Sendy używa personalization tag jako nazwy parametru
            // Jeśli w Sendy masz custom field z tagiem [RSPO,fallback=], użyj 'RSPO' jako parametru
            if ($rspo) {
                $additionalData['RSPO'] = (string) $rspo;
            }

            if ($this->subscribe($email, $listId, $additionalData)) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Nie udało się dodać: {$email}";
            }

            // Małe opóźnienie aby nie przeciążać API
            // Zmniejszone do 50ms dla szybszego importu (można zwiększyć jeśli API zwraca błędy rate limit)
            usleep(50000); // 0.05 sekundy
        }

        return $results;
    }

    /**
     * Testuje połączenie z API Sendy
     */
    public function testConnection(): array
    {
        try {
            $brands = $this->getBrands();
            return [
                'success' => true,
                'message' => 'Połączenie z Sendy API działa poprawnie',
                'brands_count' => count($brands),
                'brands' => $brands
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd połączenia z Sendy API: ' . $e->getMessage()
            ];
        }
    }
}
