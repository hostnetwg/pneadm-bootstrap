<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PneduOnlinePaymentRecoveryService
{
    /**
     * @return array{success: bool, error?: string, code?: string, emails?: list<string>, sent_at?: string, http_code?: int}
     */
    public function sendRecoveryEmail(int $formOrderId, bool $allowResend = true): array
    {
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

        $url = $baseUrl.'/api/internal/form-orders/'.$formOrderId.'/send-online-payment-recovery';

        try {
            $response = Http::timeout(15)
                ->withToken($token)
                ->acceptJson()
                ->post($url, [
                    'allow_resend' => $allowResend,
                ]);

            $payload = $response->json();
            if (! is_array($payload)) {
                $payload = [
                    'success' => false,
                    'error' => 'Nieprawidłowa odpowiedź serwera pnedu.',
                    'code' => 'invalid_response',
                ];
            }

            $payload['http_code'] = $response->status();

            if (! $response->successful() && ! isset($payload['error'])) {
                $payload['success'] = false;
                $payload['error'] = 'Nie udało się wysłać recovery e-mail (HTTP '.$response->status().').';
            }

            return $payload;
        } catch (\Throwable $exception) {
            Log::warning('Pnedu online payment recovery email request failed', [
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
