<?php

return [
    'enabled' => (bool) env('ANALYTICS_ENABLED', true),

    'connection' => env('ANALYTICS_DB_CONNECTION', 'analytics'),

    'default_mode' => env('ANALYTICS_DEFAULT_MODE', 'standard'),

    'sample_rate' => (int) env('ANALYTICS_SAMPLE_RATE', 100),

    'debug_panel' => [
        'enabled' => (bool) env('ANALYTICS_DEBUG_PANEL_ENABLED', false),
        'timezone' => env('ANALYTICS_DEBUG_PANEL_TIMEZONE', 'Europe/Warsaw'),
    ],

    'aggregation' => [
        'timezone' => env('ANALYTICS_AGGREGATION_TIMEZONE', 'Europe/Warsaw'),
    ],

    'abandonment' => [
        'timezone' => env('ANALYTICS_ABANDONMENT_TIMEZONE', env('ANALYTICS_AGGREGATION_TIMEZONE', 'Europe/Warsaw')),
        // Domyślny cel komendy (gdy bez dat, np. z crona): ile dni wstecz przeliczać.
        // 2 dni dają sesjom okno ~24-48 h dojrzałości — użytkownik raczej nie wróci do
        // porzuconego formularza, więc klasyfikacja jest stabilna. Backfill: --date/--from/--to.
        'aggregation_lag_days' => (int) env('ANALYTICS_ABANDONMENT_LAG_DAYS', 2),
    ],

    'sales_funnel_dashboard' => [
        'enabled' => (bool) env('ANALYTICS_SALES_FUNNEL_DASHBOARD_ENABLED', true),
        'timezone' => env('ANALYTICS_SALES_FUNNEL_DASHBOARD_TIMEZONE', 'Europe/Warsaw'),
        'default_days' => (int) env('ANALYTICS_SALES_FUNNEL_DASHBOARD_DEFAULT_DAYS', 14),
        // Maksymalny zakres dni dla przycisku "Przelicz teraz" (ręczna agregacja z panelu).
        // Chroni request HTTP przed timeoutem przy dużym wolumenie eventów. Domyślnie ~rok.
        // Do bardzo dużych przeliczeń historycznych użyj komendy konsolowej (bez limitu).
        'recompute_max_days' => (int) env('ANALYTICS_SALES_FUNNEL_RECOMPUTE_MAX_DAYS', 366),
    ],

    'queue' => [
        'connection' => env('ANALYTICS_QUEUE_CONNECTION', 'redis'),
        'name' => env('ANALYTICS_QUEUE', 'analytics'),
        'tries' => (int) env('ANALYTICS_QUEUE_TRIES', 2),
        'timeout' => (int) env('ANALYTICS_QUEUE_TIMEOUT', 30),
    ],

    'retention_days' => [
        'raw_events' => (int) env('ANALYTICS_RETENTION_RAW_EVENTS_DAYS', 180),
        'order_form_sessions' => (int) env('ANALYTICS_RETENTION_ORDER_FORM_SESSIONS_DAYS', 365),
        'ai_safe_exports' => (int) env('ANALYTICS_RETENTION_AI_SAFE_EXPORTS_DAYS', 365),
    ],
];
