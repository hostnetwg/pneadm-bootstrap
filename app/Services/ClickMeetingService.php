<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickMeetingService
{
    public const ACCESS_TYPE_TOKEN = 3;

    public const ACCESS_TYPE_PASSWORD = 2;

    /**
     * @return array{success: bool, error?: string, data?: mixed, status_code?: int}
     */
    public function registerParticipant(string $eventId, string $firstName, string $lastName, string $email): array
    {
        $config = $this->apiConfig();
        if ($config === null) {
            return [
                'success' => false,
                'error' => 'Brak konfiguracji ClickMeeting API token.',
            ];
        }

        try {
            // 1) Preferowany tryb zgodny ze starym, działającym webhookiem:
            //    wysyłka zaproszenia e-mail do wskazanego uczestnika.
            $invitationPayload = [
                'attendees' => [
                    [
                        'email' => $email,
                        'firstname' => $firstName,
                        'lastname' => $lastName,
                    ],
                ],
                'template' => 'advanced',
                'role' => 'listener',
            ];

            $invitationResponse = Http::baseUrl($config['base_url'])
                ->withHeaders(['X-Api-Key' => $config['api_key']])
                ->asForm()
                ->post('conferences/'.urlencode($eventId).'/invitation/email/pl', $invitationPayload);

            if ($invitationResponse->successful()) {
                return [
                    'success' => true,
                    'data' => $invitationResponse->json(),
                    'status_code' => $invitationResponse->status(),
                ];
            }

            Log::warning('ClickMeetingService: invitation failed, trying registration fallback', [
                'event_id' => $eventId,
                'email' => $email,
                'status' => $invitationResponse->status(),
                'body' => $invitationResponse->body(),
            ]);

            // 2) Fallback: klasyczna rejestracja uczestnika.
            $registrationPayload = [
                'registration' => [
                    1 => $firstName,
                    2 => $lastName,
                    3 => $email,
                ],
                'enabled' => 1,
                'lang' => 'pl',
            ];

            $registrationResponse = Http::baseUrl($config['base_url'])
                ->withHeaders(['X-Api-Key' => $config['api_key']])
                ->asForm()
                ->post('conferences/'.urlencode($eventId).'/registration', $registrationPayload);

            if ($registrationResponse->successful()) {
                return [
                    'success' => true,
                    'data' => $registrationResponse->json(),
                    'status_code' => $registrationResponse->status(),
                ];
            }

            return [
                'success' => false,
                'error' => 'ClickMeeting zwrócił błąd. Invitation HTTP '.$invitationResponse->status().', registration HTTP '.$registrationResponse->status().'.',
                'status_code' => $registrationResponse->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('ClickMeetingService: registration exception', [
                'event_id' => $eventId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd komunikacji z ClickMeeting: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, error?: string, access_type?: int|null, conference?: array}
     */
    public function getConference(string $eventId): array
    {
        $config = $this->apiConfig();
        if ($config === null) {
            return [
                'success' => false,
                'error' => 'Brak konfiguracji ClickMeeting API token.',
            ];
        }

        try {
            $response = Http::baseUrl($config['base_url'])
                ->withHeaders(['X-Api-Key' => $config['api_key']])
                ->get('conferences/'.urlencode($eventId));

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'ClickMeeting zwrócił HTTP '.$response->status().' przy pobieraniu wydarzenia.',
                ];
            }

            $conference = $this->extractConferencePayload($response->json());

            return [
                'success' => true,
                'access_type' => isset($conference['access_type']) ? (int) $conference['access_type'] : null,
                'conference' => $conference,
            ];
        } catch (\Throwable $e) {
            Log::error('ClickMeetingService: getConference exception', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd komunikacji z ClickMeeting: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Pobiera token dostępu przypisany do e-maila (tylko dla access_type = 3).
     *
     * @return array{success: bool, error?: string, token?: string}
     */
    public function getAccessTokenForEmail(string $eventId, string $email): array
    {
        $config = $this->apiConfig();
        if ($config === null) {
            return [
                'success' => false,
                'error' => 'Brak konfiguracji ClickMeeting API token.',
            ];
        }

        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return [
                'success' => false,
                'error' => 'Brak adresu e-mail do pobrania tokenu ClickMeeting.',
            ];
        }

        try {
            foreach ([0, 500] as $delayMs) {
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }

                $token = $this->requestTokenForEmail($config, $eventId, $normalizedEmail);
                if ($token !== null) {
                    return [
                        'success' => true,
                        'token' => $token,
                    ];
                }

                $token = $this->findTokenInConferenceTokens($config, $eventId, $normalizedEmail);
                if ($token !== null) {
                    return [
                        'success' => true,
                        'token' => $token,
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Nie znaleziono tokenu dostępu ClickMeeting dla tego uczestnika.',
            ];
        } catch (\Throwable $e) {
            Log::error('ClickMeetingService: getAccessTokenForEmail exception', [
                'event_id' => $eventId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd komunikacji z ClickMeeting: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array{base_url: string, api_key: string}  $config
     */
    private function requestTokenForEmail(array $config, string $eventId, string $normalizedEmail): ?string
    {
        $response = Http::baseUrl($config['base_url'])
            ->withHeaders(['X-Api-Key' => $config['api_key']])
            ->asForm()
            ->post('conferences/'.urlencode($eventId).'/token', [
                'email' => $normalizedEmail,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $this->extractTokenFromResponse($response->json());
    }

    /**
     * @param  array{base_url: string, api_key: string}  $config
     */
    private function findTokenInConferenceTokens(array $config, string $eventId, string $normalizedEmail): ?string
    {
        $response = Http::baseUrl($config['base_url'])
            ->withHeaders(['X-Api-Key' => $config['api_key']])
            ->get('conferences/'.urlencode($eventId).'/tokens');

        if (! $response->successful()) {
            return null;
        }

        $tokens = $response->json('access_tokens');
        if (! is_array($tokens)) {
            return null;
        }

        foreach ($tokens as $tokenRow) {
            if (! is_array($tokenRow)) {
                continue;
            }

            $sentToEmail = strtolower(trim((string) ($tokenRow['sent_to_email'] ?? '')));
            $token = trim((string) ($tokenRow['token'] ?? ''));

            if ($sentToEmail === $normalizedEmail && $token !== '') {
                return $token;
            }
        }

        return null;
    }

    /**
     * @return array{base_url: string, api_key: string}|null
     */
    private function apiConfig(): ?array
    {
        $apiKey = (string) config('services.clickmeeting.token', '');
        if ($apiKey === '') {
            return null;
        }

        return [
            'base_url' => rtrim((string) config('services.clickmeeting.url', 'https://api.clickmeeting.com/v1/'), '/').'/',
            'api_key' => $apiKey,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractConferencePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (isset($payload['conference']) && is_array($payload['conference'])) {
            return $payload['conference'];
        }

        return $payload;
    }

    public function extractTokenFromResponse(mixed $payload): ?string
    {
        if (is_string($payload)) {
            $token = trim($payload);

            return $token !== '' ? $token : null;
        }

        if (! is_array($payload)) {
            return null;
        }

        if (isset($payload['token']) && is_string($payload['token'])) {
            $token = trim($payload['token']);

            return $token !== '' ? $token : null;
        }

        foreach ($payload as $item) {
            if (is_string($item)) {
                $token = trim($item);
                if ($token !== '') {
                    return $token;
                }
            }
        }

        return null;
    }

    public function extractRoomUrl(mixed $conference): ?string
    {
        if (! is_array($conference)) {
            return null;
        }

        $roomUrl = trim((string) ($conference['room_url'] ?? ''));

        return $roomUrl !== '' ? $roomUrl : null;
    }

    public function buildJoinUrl(string $roomUrl, ?string $token = null): string
    {
        $roomUrl = rtrim(trim($roomUrl), '/');
        $token = trim((string) $token);

        if ($token === '') {
            return $roomUrl;
        }

        return $roomUrl.'/'.$token;
    }
}
