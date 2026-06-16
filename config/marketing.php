<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Publiczny URL sklepu (pnedu.pl) — linki do kopiowania / otwierania w przeglądarce
    |--------------------------------------------------------------------------
    | Lokalnie (Sail): http://localhost:8081 — inaczej generator wskazuje na produkcję.
    */
    'pnedu_public_url' => rtrim(
        env('PNEDU_PUBLIC_URL', env('APP_ENV') === 'local' ? 'http://localhost:8081' : 'https://pnedu.pl'),
        '/'
    ),

    /*
    |--------------------------------------------------------------------------
    | URL pnedu do testu przekierowania z kontenera adm (server-to-server)
    |--------------------------------------------------------------------------
    | W Dockerze: http://pnedu-app (nazwa kontenera pnedu w sieci pne-network).
    */
    'pnedu_internal_url' => rtrim(
        env('PNEDU_INTERNAL_URL', env('APP_ENV') === 'local' ? 'http://pnedu-app' : env('PNEDU_PUBLIC_URL', 'https://pnedu.pl')),
        '/'
    ),

    /*
    |--------------------------------------------------------------------------
    | Okno atrybucji kampanii (dni) – cookie + sesja
    |--------------------------------------------------------------------------
    */
    'attribution_days' => (int) env('MARKETING_ATTRIBUTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Domyślny zakres statystyk lejka na liście kursów (dni)
    |--------------------------------------------------------------------------
    */
    'funnel_stats_days' => (int) env('MARKETING_FUNNEL_STATS_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Dozwolone wartości utm_medium w formularzach
    |--------------------------------------------------------------------------
    */
    'utm_medium_options' => [
        'paid' => 'Płatna reklama',
        'cpc' => 'Płatne kliknięcia (Google)',
        'social' => 'Social organiczny',
        'email' => 'E-mail / newsletter',
        'sms' => 'SMS',
        'banner' => 'Baner na stronie',
        'content' => 'Treść (blog, artykuł)',
        'referral' => 'Polecenie / partner',
        'offline' => 'Offline / druk / QR',
        'webinar' => 'Webinar',
        'rad-ped' => 'Rada pedagogiczna',
        'direct-sales' => 'Sprzedaż bezpośrednia',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sugestie utm_content (wariant / taktyka w GA4)
    |--------------------------------------------------------------------------
    */
    'utm_content_presets' => [
        'prospecting' => 'Ruch zimny — nowi odbiorcy (FB/TikTok paid)',
        'remarketing' => 'Retargeting — osoby, które już były na stronie',
        'organic' => 'Post organiczny bez płatnej promocji',
        'webinar' => 'Zaproszenie na webinar / szkolenie na żywo',
        'cta-hero' => 'Główny przycisk CTA (np. w mailu)',
        'cta-stopka' => 'Link w stopce newslettera',
        'video-description' => 'Opis filmu na YouTube',
        'live-event' => 'Wydarzenie / transmisja na żywo na YouTube',
        'pinned-comment' => 'Przypięty komentarz pod filmem',
        'community-post' => 'Post w zakładce Społeczność YouTube',
    ],

    /*
    |--------------------------------------------------------------------------
    | Miejsca konwersji na zamówieniu (osobno od fb_source / kampanii)
    |--------------------------------------------------------------------------
    */
    'conversion_placements' => [
        'dashboard_sidebar' => 'Panel klienta → Aktualna oferta',
    ],

    /*
    |--------------------------------------------------------------------------
    | Opt-out lejka na pnedu.pl (linki pomocnicze w panelu adm)
    |--------------------------------------------------------------------------
    */
    'funnel_skip_cookie' => env('MARKETING_FUNNEL_SKIP_COOKIE', 'pne_skip_funnel'),
    'funnel_skip_until_cookie' => env('MARKETING_FUNNEL_SKIP_UNTIL_COOKIE', 'pne_skip_funnel_until'),
    'funnel_skip_query_param' => env('MARKETING_FUNNEL_SKIP_QUERY_PARAM', 'pne_skip_funnel'),
    'funnel_skip_token_param' => env('MARKETING_FUNNEL_SKIP_TOKEN_PARAM', 'token'),
    'funnel_skip_token' => env('MARKETING_FUNNEL_SKIP_TOKEN'),
    'funnel_skip_cookie_days' => (int) env('MARKETING_FUNNEL_SKIP_COOKIE_DAYS', 365),
    'funnel_skip_cookie_domain' => env(
        'MARKETING_FUNNEL_SKIP_COOKIE_DOMAIN',
        env('APP_ENV') === 'production' ? '.pnedu.pl' : null
    ),

];
