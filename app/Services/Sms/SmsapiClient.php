<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klient HTTP do SMSAPI.pl (pojedynczy SMS).
 *
 * Dokumentacja API: https://www.smsapi.pl/docs/#dokumentacja-sms-api
 * Kontekst windykacji: docs/WINDYKACJA.md (sprawa → przypomnienie SMS).
 */
class SmsapiClient
{
    /**
     * @return array{ok: bool, message: string, message_id: ?string, points: ?float, raw: mixed}
     */
    public function sendSms(string $toE164Digits, string $message, ?string $from = null): array
    {
        if (! config('services.smsapi.enabled', true)) {
            return [
                'ok' => false,
                'message' => 'Wysyłka SMSAPI jest wyłączona (SMSAPI_ENABLED=false).',
                'message_id' => null,
                'points' => null,
                'raw' => null,
            ];
        }

        $token = trim((string) config('services.smsapi.token', ''));
        if ($token === '') {
            return [
                'ok' => false,
                'message' => 'Brak tokenu SMSAPI (SMSAPI_TOKEN).',
                'message_id' => null,
                'points' => null,
                'raw' => null,
            ];
        }

        $params = [
            'to' => $toE164Digits,
            'message' => $message,
            'format' => 'json',
            'encoding' => 'utf-8',
        ];

        $from = trim((string) ($from ?? config('services.smsapi.from', '')));
        if ($from !== '') {
            $params['from'] = $from;
        }

        $timeout = max(5, (int) config('services.smsapi.timeout', 20));
        $primary = rtrim((string) config('services.smsapi.base_url', 'https://api.smsapi.pl'), '/').'/sms.do';
        $backup = rtrim((string) config('services.smsapi.backup_base_url', 'https://api2.smsapi.pl'), '/').'/sms.do';

        $result = $this->postSms($primary, $token, $params, $timeout);
        if (! $result['ok'] && $this->shouldRetryBackup($result)) {
            $result = $this->postSms($backup, $token, $params, $timeout);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, message: string, message_id: ?string, points: ?float, raw: mixed}
     */
    private function postSms(string $url, string $token, array $params, int $timeout): array
    {
        try {
            $response = Http::withToken($token)
                ->asForm()
                ->acceptJson()
                ->timeout($timeout)
                ->post($url, $params);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            // Sukces SMSAPI: {"count":1,"list":[{"id":"...","points":0.16,...}]}
            // Błąd: {"error":13,"message":"..."}
            if ($response->successful() && empty($json['error']) && (isset($json['list']) || isset($json['count']))) {
                $first = is_array($json['list'][0] ?? null) ? $json['list'][0] : [];

                return [
                    'ok' => true,
                    'message' => 'SMS wysłany.',
                    'message_id' => isset($first['id']) ? (string) $first['id'] : null,
                    'points' => isset($first['points']) ? (float) $first['points'] : null,
                    'raw' => $json,
                ];
            }

            if (! empty($json['error']) || (isset($json['message']) && ! isset($json['list']))) {
                $code = $json['error'] ?? $json['code'] ?? $response->status();
                $msg = (string) ($json['message'] ?? 'Błąd SMSAPI');

                return [
                    'ok' => false,
                    'message' => sprintf('SMSAPI: %s (kod %s)', $msg, $code),
                    'message_id' => null,
                    'points' => null,
                    'raw' => $json,
                ];
            }

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'SMSAPI HTTP '.$response->status().': '.$response->body(),
                    'message_id' => null,
                    'points' => null,
                    'raw' => $json,
                ];
            }

            return [
                'ok' => false,
                'message' => 'Nieoczekiwana odpowiedź SMSAPI.',
                'message_id' => null,
                'points' => null,
                'raw' => $json,
            ];
        } catch (Throwable $e) {
            Log::warning('SMSAPI request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Błąd połączenia z SMSAPI: '.$e->getMessage(),
                'message_id' => null,
                'points' => null,
                'raw' => null,
            ];
        }
    }

    /**
     * @param  array{ok: bool, message: string, raw: mixed}  $result
     */
    private function shouldRetryBackup(array $result): bool
    {
        if ($result['ok']) {
            return false;
        }

        $message = strtolower($result['message'] ?? '');

        return str_contains($message, 'http 5')
            || str_contains($message, 'połączenia')
            || str_contains($message, 'connection')
            || str_contains($message, 'timeout');
    }
}
