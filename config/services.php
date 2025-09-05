<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'publigo' => [
        'webhook_url' => env('PUBLIGO_WEBHOOK_URL', 'https://adm.pnedu.pl/api/publigo/webhook'),
        'api_key' => env('PUBLIGO_API_KEY'), // Klucz licencyjny do weryfikacji podpisu
        'api_secret' => env('PUBLIGO_API_SECRET'),
        'instance_url' => env('PUBLIGO_INSTANCE_URL', 'https://nowoczesna-edukacja.pl'),
        'api_version' => env('PUBLIGO_API_VERSION', 'v1'),
        'timeout' => env('PUBLIGO_API_TIMEOUT', 30),
        'api_type' => env('PUBLIGO_API_TYPE', 'wp_idea'), // Typ API: wp_idea, standard
        'wp_idea_endpoint' => env('PUBLIGO_WP_IDEA_ENDPOINT', '/wp-json/wp-idea/v1'),
    ],

    'clickmeeting' => [
        'url' => env('CLICKMEETING_API_URL', 'https://api.clickmeeting.com/v1/'),
        'token' => env('CLICKMEETING_API_TOKEN'),
    ],

];
