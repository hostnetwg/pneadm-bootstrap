<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sendy Configuration
    |--------------------------------------------------------------------------
    |
    | Konfiguracja dla integracji z Sendy API
    |
    */

    'api_key' => env('SENDY_API_KEY', 'QWVN3gYyibFsPWh39Til'),
    
    'base_url' => env('SENDY_BASE_URL', 'https://sendyhost.net'),
    
    'license_key' => env('SENDY_LICENSE_KEY', '1ZmYIrF8HC93FNIcfkHCSKAcM0Tx3iVV'),
    
    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Domyślne ustawienia dla operacji Sendy
    |
    */
    
    'defaults' => [
        'include_hidden_lists' => false,
        'gdpr_compliant' => true,
        'silent_subscription' => false,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Ustawienia cache dla danych z Sendy API
    |
    */
    
    'cache' => [
        'enabled' => env('SENDY_CACHE_ENABLED', true),
        'ttl' => env('SENDY_CACHE_TTL', 300), // 5 minut
        'prefix' => 'sendy_',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Ustawienia logowania dla operacji Sendy
    |
    */
    
    'logging' => [
        'enabled' => env('SENDY_LOGGING_ENABLED', true),
        'level' => env('SENDY_LOG_LEVEL', 'info'),
        'channel' => env('SENDY_LOG_CHANNEL', 'daily'),
    ],
];
