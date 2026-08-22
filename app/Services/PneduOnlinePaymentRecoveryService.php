<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PneduOnlinePaymentRecoveryService
{
    /**
     * @return array{success: bool, error?: string, code?: string, to?: string, emails?: list<string>, subject?: string, body_html?: string, body?: string, hint?: string, http_code?: int}
     */
    public function previewRecoveryEmail(int $formOrderId): array
    {
        return $this->requestInternal(
            'GET',
            '/api/internal/form-orders/'.$formOrderId.'/preview-online-payment-recovery',
            [],
            $formOrderId,
            'preview'
        );
    }

    /**
     * @return array{success: bool, error?: string, code?: string, emails?: list<string>, sent_at?: string, http_code?: int}
     */
    public function sendRecoveryEmail(int $formOrderId, bool $allowResend = true): array
    {
        return $this->requestInternal(
            'POST',
            '/api/internal/form-orders/'.$formOrderId.'/send-online-payment-recovery',
            ['allow_resend' => $allowResend],
            $formOrderId,
            'send'
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestInternal(
        string $method,
        string $path,
        array $payload,
        int $formOrderId,
        string $action
    ): array {
        $baseUrl = rtrim((string) config('services.pnedu.internal_url'), '/');
        $token = (string) config('services.pnedu.internal_api_token');

        if ($baseUrl === '' || $token === '') {
            return [
                'success' => false,
                'error' => 'Brak konfiguracji PNEDU_INTERNAL_URL lub PNEDU_INTERNAL_API_TOKEN.',
                'code' => 'misconfigured',
                'http_code' => 500,
            ];
        }

        $url = $baseUrl.$path;

        try {
            $pending = Http::timeout(15)
                ->withToken($token)
                ->acceptJson();

            $response = strtoupper($method) === 'GET'
                ? $pending->get($url)
                : $pending->post($url, $payload);

            $body = $response->json();
            if (! is_array($body)) {
                $body = [
                    'success' => false,
                    'error' => 'Nieprawidłowa odpowiedź serwera pnedu.',
                    'code' => 'invalid_response',
                ];
            }

            $body['http_code'] = $response->status();

            if (! $response->successful() && ! isset($body['error'])) {
                $body['success'] = false;
                $body['error'] = 'Nie udało się wykonać akcji recovery e-mail (HTTP '.$response->status().').';
            }

            return $body;
        } catch (\Throwable $exception) {
            Log::warning("Pnedu online payment recovery {$action} request failed", [
                'form_order_id' => $formOrderId,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd połączenia z pnedu.pl: '.$exception->getMessage(),
                'code' => 'connection_error',
                'http_code' => 502,
            ];
        }
    }
}
