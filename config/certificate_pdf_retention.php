<?php

/*
|--------------------------------------------------------------------------
| Retencja plików PDF zaświadczeń (storage/app/public/certificates)
|--------------------------------------------------------------------------
|
| Wygenerowane PDF-y można odtworzyć z danych w bazie, więc po `days` dniach od
| `certificates.generated_at` plik jest kasowany, a `file_path` zerowany.
| Pobranie przez pnedu generuje plik od nowa (self-healing).
|
| Zaplanowane czyszczenie jest domyślnie WYŁĄCZONE (enabled=false). Włącz je
| dopiero po `--dry-run` na produkcji. Kanon: docs/deploy/CERTIFICATE_PDF_RETENTION.md
*/

return [
    'enabled' => (bool) env('CERTIFICATE_PDF_CLEANUP_ENABLED', false),

    'days' => (int) env('CERTIFICATE_PDF_RETENTION_DAYS', 30),

    // Zaplanowane uruchomienie (czas strefy z `timezone`), poza godzinami szczytu.
    'schedule_time' => env('CERTIFICATE_PDF_CLEANUP_TIME', '03:15'),
    'timezone' => env('CERTIFICATE_PDF_CLEANUP_TIMEZONE', 'Europe/Warsaw'),

    // Minimalna wartość --days (ochrona przed pomyłką typu --days=0).
    'min_days' => 7,
];
