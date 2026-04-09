<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickMeetingService
{
    /**
     * @return array{success: bool, error?: string, data?: mixed, status_code?: int}
     */
    public function registerParticipant(string $eventId, string $firstName, string $lastName, string $email): array
    {
        $baseUrl = rtrim((string) config('services.clickmeeting.url', 'https://api.clickmeeting.com/v1/'), '/').'/';
        $apiKey = (string) config('services.clickmeeting.token', '');

        if ($apiKey === '') {
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

            $invitationResponse = Http::baseUrl($baseUrl)
                ->withHeaders(['X-Api-Key' => $apiKey])
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

            $registrationResponse = Http::baseUrl($baseUrl)
                ->withHeaders(['X-Api-Key' => $apiKey])
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
}
