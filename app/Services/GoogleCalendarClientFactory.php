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
        $credentials = trim((string) config('services.google_calendar.credentials', ''));

        return $calendarId !== ''
            && $credentials !== ''
            && is_readable($credentials);
    }

    public function makeCalendarService(): Calendar
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Calendar API nie jest skonfigurowane (calendar_id / credentials).');
        }

        $client = new Client;
        $client->setApplicationName((string) config('app.name', 'pneadm'));
        $client->setAuthConfig((string) config('services.google_calendar.credentials'));
        $client->setScopes([Calendar::CALENDAR]);

        return new Calendar($client);
    }
}
