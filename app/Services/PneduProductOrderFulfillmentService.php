<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PneduProductOrderFulfillmentService
{
    /**
     * @return array<string, mixed>
     */
    public function fulfill(int $formOrderId, ?int $recipientId = null): array
    {
        return $this->postInternal(
            '/api/internal/form-orders/'.$formOrderId.'/fulfill-product',
            $recipientId !== null ? ['recipient_id' => $recipientId] : [],
            'Pnedu product fulfillment request failed',
            $formOrderId
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function revoke(int $formOrderId, ?int $recipientId = null): array
    {
        return $this->postInternal(
            '/api/internal/form-orders/'.$formOrderId.'/revoke-product',
            $recipientId !== null ? ['recipient_id' => $recipientId] : [],
            'Pnedu product revoke request failed',
            $formOrderId
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postInternal(string $path, array $payload, string $logMessage, int $formOrderId): array
    {
        $baseUrl = rtrim((string) config('services.pnedu.internal_url'), '/');
        $token = (string) config('services.pnedu.internal_api_token');

        if ($baseUrl === '' || $token === '') {
            return [
                'success' => false,
                'error' => 'Brak konfiguracji PNEDU_INTERNAL_URL lub PNEDU_INTERNAL_API_TOKEN.',
                'http_code' => 500,
            ];
        }

        $url = $baseUrl.$path;

        try {
            $response = Http::timeout(60)
                ->withToken($token)
                ->acceptJson()
                ->post($url, $payload);
            $body = $response->json();

            if (! is_array($body)) {
                return [
                    'success' => false,
                    'error' => 'Nieprawidłowa odpowiedź aplikacji pnedu.',
                    'http_code' => 502,
                ];
            }

            $body['http_code'] = $response->status();

            return $body;
        } catch (\Throwable $exception) {
            Log::warning($logMessage, [
                'form_order_id' => $formOrderId,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd połączenia z pnedu.pl: '.$exception->getMessage(),
                'http_code' => 502,
            ];
        }
    }
}
