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

    /**
     * @return array{success: bool, error?: string, autologin_hash?: string}
     */
    public function generateAutologinHash(
        string $eventId,
        string $email,
        string $nickname,
        string $role = 'listener',
        ?string $token = null,
        ?string $password = null
    ): array {
        $config = $this->apiConfig();
        if ($config === null) {
            return [
                'success' => false,
                'error' => 'Brak konfiguracji ClickMeeting API token.',
            ];
        }

        $email = strtolower(trim($email));
        $nickname = trim($nickname);
        if ($email === '' || ! str_contains($email, '@')) {
            return [
                'success' => false,
                'error' => 'Brak prawidłowego adresu e-mail do auto-login.',
            ];
        }

        if ($nickname === '') {
            return [
                'success' => false,
                'error' => 'Brak nicku uczestnika do auto-login.',
            ];
        }

        $payload = [
            'email' => $email,
            'nickname' => $nickname,
            'role' => $role,
        ];

        $token = trim((string) $token);
        if ($token !== '') {
            $payload['token'] = $token;
        }

        $password = trim((string) $password);
        if ($password !== '') {
            $payload['password'] = $password;
        }

        try {
            $response = Http::baseUrl($config['base_url'])
                ->withHeaders(['X-Api-Key' => $config['api_key']])
                ->asForm()
                ->post('conferences/'.urlencode($eventId).'/room/autologin_hash', $payload);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'ClickMeeting zwrócił HTTP '.$response->status().' przy generowaniu auto-login hash.',
                ];
            }

            $hash = trim((string) $response->json('autologin_hash'));
            if ($hash === '') {
                return [
                    'success' => false,
                    'error' => 'ClickMeeting nie zwrócił autologin_hash.',
                ];
            }

            return [
                'success' => true,
                'autologin_hash' => $hash,
            ];
        } catch (\Throwable $e) {
            Log::error('ClickMeetingService: generateAutologinHash exception', [
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

    public function buildAutologinUrl(string $roomUrl, string $autologinHash): string
    {
        return $this->appendUrlQueryParam(trim($roomUrl), 'l', trim($autologinHash));
    }

    public function extractEmbedRoomUrl(mixed $conference): ?string
    {
        if (! is_array($conference)) {
            return null;
        }

        $embedUrl = trim((string) ($conference['embed_room_url'] ?? ''));

        return $embedUrl !== '' ? $embedUrl : null;
    }

    /**
     * Oficjalny embed_room_url to adres skryptu (application/javascript), nie HTML do iframe.
     * Skrypt wstrzykuje iframe na {host}/{room_pin}?popup=off&lang=pl.
     */
    public function extractRoomPin(mixed $conference): ?string
    {
        if (! is_array($conference)) {
            return null;
        }

        $pin = trim((string) ($conference['room_pin'] ?? ''));

        return $pin !== '' ? $pin : null;
    }

    /**
     * URL pokoju do bezpośredniego osadzenia w <iframe> (jak robi oficjalny skrypt CM).
     */
    public function buildPinEmbedUrl(string $roomUrl, string $roomPin, array $query = []): ?string
    {
        $roomUrl = trim($roomUrl);
        $roomPin = trim($roomPin);
        if ($roomUrl === '' || $roomPin === '') {
            return null;
        }

        $parts = parse_url($roomUrl);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $base = $parts['scheme'].'://'.$parts['host'].'/'.$roomPin;
        $query = array_merge([
            'popup' => 'off',
            'lang' => 'pl',
        ], $query);

        return $base.'?'.http_build_query($query);
    }

    public function appendUrlQueryParam(string $url, string $name, string $value): string
    {
        $url = trim($url);
        $name = trim($name);
        $value = trim($value);

        if ($url === '' || $name === '' || $value === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.rawurlencode($name).'='.rawurlencode($value);
    }

    /**
     * Dezaktywuje wskazane tokeny dostępu (access_type = 3).
     *
     * @param  list<string>  $tokens
     * @return array{success: bool, error?: string, status_code?: int, data?: mixed}
     */
    public function deactivateTokens(string $eventId, array $tokens): array
    {
        $config = $this->apiConfig();
        if ($config === null) {
            return [
                'success' => false,
                'error' => 'Brak konfiguracji ClickMeeting API token.',
            ];
        }

        $normalized = [];
        foreach ($tokens as $token) {
            $value = trim((string) $token);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }
        $normalized = array_values(array_unique($normalized));

        if ($normalized === []) {
            return [
                'success' => false,
                'error' => 'Brak tokenów do unieważnienia.',
            ];
        }

        try {
            $response = Http::baseUrl($config['base_url'])
                ->withHeaders(['X-Api-Key' => $config['api_key']])
                ->asForm()
                ->send('DELETE', 'conferences/'.urlencode($eventId).'/tokens', [
                    'form_params' => [
                        'tokens' => $normalized,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('ClickMeetingService: deactivateTokens failed', [
                    'event_id' => $eventId,
                    'tokens' => $normalized,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'ClickMeeting zwrócił HTTP '.$response->status().' przy unieważnianiu tokenu.',
                    'status_code' => $response->status(),
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
                'status_code' => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('ClickMeetingService: deactivateTokens exception', [
                'event_id' => $eventId,
                'tokens' => $normalized,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd komunikacji z ClickMeeting: '.$e->getMessage(),
            ];
        }
    }

    public function buildPostTrainingThankYouUrl(int $courseId): string
    {
        $base = rtrim((string) config('services.pnedu_frontend_url', 'https://pnedu.pl'), '/');

        return $base.'/po-szkoleniu?course='.rawurlencode((string) $courseId);
    }

    /**
     * Ustawia URL strony podziękowania po spotkaniu (Follow-up w ustawieniach wydarzenia CM).
     *
     * @return array{success: bool, error?: string, status_code?: int}
     */
    public function updateThankYouPageUrl(string $eventId, string $thankYouPageUrl): array
    {
        $config = $this->apiConfig();
        if ($config === null) {
            return [
                'success' => false,
                'error' => 'Brak konfiguracji ClickMeeting API token.',
            ];
        }

        $eventId = trim($eventId);
        $thankYouPageUrl = trim($thankYouPageUrl);

        if ($eventId === '' || ! preg_match('/^\d{1,20}$/', $eventId)) {
            return [
                'success' => false,
                'error' => 'Nieprawidłowe ID wydarzenia ClickMeeting.',
            ];
        }

        if ($thankYouPageUrl === '' || ! filter_var($thankYouPageUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'Nieprawidłowy adres strony podziękowania.',
            ];
        }

        try {
            $response = Http::baseUrl($config['base_url'])
                ->withHeaders(['X-Api-Key' => $config['api_key']])
                ->asForm()
                ->put('conferences/'.urlencode($eventId), [
                    'settings[thank_you_page_url]' => $thankYouPageUrl,
                ]);

            if (! $response->successful()) {
                Log::warning('ClickMeetingService: updateThankYouPageUrl failed', [
                    'event_id' => $eventId,
                    'thank_you_page_url' => $thankYouPageUrl,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'ClickMeeting zwrócił HTTP '.$response->status().' przy ustawianiu strony podziękowania.',
                    'status_code' => $response->status(),
                ];
            }

            return [
                'success' => true,
                'status_code' => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('ClickMeetingService: updateThankYouPageUrl exception', [
                'event_id' => $eventId,
                'thank_you_page_url' => $thankYouPageUrl,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Błąd komunikacji z ClickMeeting: '.$e->getMessage(),
            ];
        }
    }
}
