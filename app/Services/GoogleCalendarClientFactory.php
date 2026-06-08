<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use RuntimeException;

class GoogleCalendarClientFactory
{
    public function isConfigured(): bool
    {
        $calendarId = trim((string) config('services.google_calendar.calendar_id', ''));

        return $calendarId !== '' && $this->credentialsPath() !== '';
    }

    /**
     * Zwraca pierwszą czytelną ścieżkę do pliku credentials (absolutną lub względem katalogu projektu).
     */
    public function credentialsPath(): string
    {
        $configured = trim((string) config('services.google_calendar.credentials', ''));

        if ($configured === '') {
            return '';
        }

        foreach ($this->credentialPathCandidates($configured) as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function credentialPathCandidates(string $configured): array
    {
        if (str_starts_with($configured, DIRECTORY_SEPARATOR)) {
            return [$configured];
        }

        // Względna ścieżka — najpierw od katalogu projektu (WWW ma inny CWD niż CLI).
        return [
            base_path($configured),
            $configured,
        ];
    }

    public function makeCalendarService(): Calendar
    {
        $credentials = $this->credentialsPath();

        if ($credentials === '') {
            throw new RuntimeException('Google Calendar API nie jest skonfigurowane (calendar_id / credentials).');
        }

        $client = new Client;
        $client->setApplicationName((string) config('app.name', 'pneadm'));
        $client->setAuthConfig($credentials);
        $client->setScopes([Calendar::CALENDAR]);

        return new Calendar($client);
    }
}
