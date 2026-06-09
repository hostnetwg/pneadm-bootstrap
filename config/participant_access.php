<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Przypomnienia o wygaśnięciu dostępu (nagrania / materiały)
    |--------------------------------------------------------------------------
    |
    | Automatyczne maile do uczestników płatnych szkoleń z ograniczonym dostępem
    | (participants.access_expires_at). Wysyłane raz na każdy offset (np. 7 i 1
    | dzień przed datą wygaśnięcia, liczone wg kalendarza Europe/Warsaw).
    |
    | Pobieranie zaświadczeń (link tokenowy) NIE wygasa — treść maila to podkreśla.
    |
    */

    'expiry_reminder' => [
        'enabled' => env('PARTICIPANT_ACCESS_EXPIRY_REMINDERS_ENABLED', true),

        /** @var list<int> Dni przed wygaśnięciem (kalendarzowe, strefa timezone). */
        'days_before' => array_values(array_filter(array_map(
            'intval',
            explode(',', env('PARTICIPANT_ACCESS_EXPIRY_REMINDER_DAYS', '7,1'))
        ))),

        'timezone' => env('PARTICIPANT_ACCESS_EXPIRY_REMINDER_TIMEZONE', 'Europe/Warsaw'),

        /** Godzina uruchomienia schedulera (patrz routes/console.php). */
        'schedule_time' => env('PARTICIPANT_ACCESS_EXPIRY_REMINDER_TIME', '08:00'),
    ],

];
