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

    // Etap R1 — agregaty rozliczeń (zamówienia / płatności online / faktury odroczone).
    // Metryki liczone wg DATY EVENTU w strefie biznesowej. Domyślny cel komendy (bez dat,
    // np. z crona o 03:30): wczoraj (lag=1) — dzień jest już domknięty.
    'revenue' => [
        'timezone' => env('ANALYTICS_REVENUE_TIMEZONE', env('ANALYTICS_AGGREGATION_TIMEZONE', 'Europe/Warsaw')),
        'aggregation_lag_days' => (int) env('ANALYTICS_REVENUE_LAG_DAYS', 1),
    ],

    // Etap R2 — dashboard rozliczeń (read-only, czyta wyłącznie agregaty R1).
    'revenue_dashboard' => [
        'enabled' => (bool) env('ANALYTICS_REVENUE_DASHBOARD_ENABLED', true),
        'timezone' => env('ANALYTICS_REVENUE_DASHBOARD_TIMEZONE', env('ANALYTICS_REVENUE_TIMEZONE', env('ANALYTICS_AGGREGATION_TIMEZONE', 'Europe/Warsaw'))),
        'default_days' => (int) env('ANALYTICS_REVENUE_DASHBOARD_DEFAULT_DAYS', 14),
        'max_days' => (int) env('ANALYTICS_REVENUE_DASHBOARD_MAX_DAYS', 366),
        // Maksymalny zakres dni dla przycisku "Przelicz rozliczenia" (ręczna agregacja R1 z panelu).
        // Chroni request HTTP przed timeoutem. Do większych przeliczeń użyj komendy konsolowej.
        'recompute_max_days' => (int) env('ANALYTICS_REVENUE_RECOMPUTE_MAX_DAYS', 92),
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

    // Etap B4 — dashboard porzuceń formularza (read-only, czyta wyłącznie agregaty B3).
    'form_abandonment_dashboard' => [
        'enabled' => (bool) env('ANALYTICS_FORM_ABANDONMENT_DASHBOARD_ENABLED', true),
        'timezone' => env('ANALYTICS_FORM_ABANDONMENT_DASHBOARD_TIMEZONE', 'Europe/Warsaw'),
        'default_days' => (int) env('ANALYTICS_FORM_ABANDONMENT_DASHBOARD_DEFAULT_DAYS', 14),
        // Maksymalny zakres dni filtra (ochrona przed zbyt dużym zapytaniem). Domyślnie ~rok.
        'max_days' => (int) env('ANALYTICS_FORM_ABANDONMENT_DASHBOARD_MAX_DAYS', 366),
        // Maksymalny zakres dni dla przycisku "Przelicz porzucenia" (ręczna agregacja B3 z panelu).
        // Chroni request HTTP przed timeoutem. Do większych przeliczeń użyj komendy konsolowej.
        'recompute_max_days' => (int) env('ANALYTICS_FORM_ABANDONMENT_RECOMPUTE_MAX_DAYS', 92),
    ],

    // Etap B4+ — agregaty lejka formularza per traffic_channel / kurs / kampania / GUS / jakość danych.
    'order_form_funnel' => [
        'timezone' => env('ANALYTICS_ORDER_FORM_FUNNEL_TIMEZONE', env('ANALYTICS_AGGREGATION_TIMEZONE', 'Europe/Warsaw')),
        'aggregation_lag_days' => (int) env('ANALYTICS_ORDER_FORM_FUNNEL_LAG_DAYS', 2),
        'grace_period_soft_minutes' => (int) env('ANALYTICS_ORDER_FORM_FUNNEL_GRACE_SOFT_MINUTES', 15),
        'grace_period_final_minutes' => (int) env('ANALYTICS_ORDER_FORM_FUNNEL_GRACE_FINAL_MINUTES', 60),
        'data_quality_min_sessions' => (int) env('ANALYTICS_ORDER_FORM_FUNNEL_DQ_MIN_SESSIONS', 30),
        'warmup_hours' => (int) env('ANALYTICS_ORDER_FORM_FUNNEL_WARMUP_HOURS', 24),
        // Data pierwszego wdrożenia trackingu v2 — okno warmup_or_deploy_window (Europe/Warsaw).
        'tracking_deployed_at' => env('ANALYTICS_ORDER_FORM_V2_DEPLOYED_AT', '2026-07-01'),
        // Pełna atrybucja 2F (order_form_attributions + TrafficChannelClassifier) — dni wcześniejsze
        // mają oczekiwanie brak kanałów; kalendarzowy dzień wdrożenia = warmup_or_deploy_window w healthchecku.
        'attribution_deployed_at' => env('ANALYTICS_ORDER_FORM_ATTRIBUTION_DEPLOYED_AT', '2026-07-09'),
        'healthcheck_v2_window_minutes' => (int) env('ANALYTICS_ORDER_FORM_FUNNEL_HC_V2_WINDOW_MINUTES', 60),
    ],

    'order_form_funnel_dashboard' => [
        'enabled' => (bool) env('ANALYTICS_ORDER_FORM_FUNNEL_DASHBOARD_ENABLED', true),
        'timezone' => env('ANALYTICS_ORDER_FORM_FUNNEL_DASHBOARD_TIMEZONE', env('ANALYTICS_ORDER_FORM_FUNNEL_TIMEZONE', 'Europe/Warsaw')),
        'default_days' => (int) env('ANALYTICS_ORDER_FORM_FUNNEL_DASHBOARD_DEFAULT_DAYS', 14),
        'max_days' => (int) env('ANALYTICS_ORDER_FORM_FUNNEL_DASHBOARD_MAX_DAYS', 366),
        'recompute_max_days' => (int) env('ANALYTICS_ORDER_FORM_FUNNEL_RECOMPUTE_MAX_DAYS', 92),
    ],

    // Blok „Aktywni teraz” na dashboardzie zamówień (polling z analytics_events, lejek sprzedaży).
    'live_visitors_dashboard' => [
        'enabled' => (bool) env('ANALYTICS_LIVE_VISITORS_DASHBOARD_ENABLED', true),
        'timezone' => env('ANALYTICS_LIVE_VISITORS_DASHBOARD_TIMEZONE', env('ANALYTICS_AGGREGATION_TIMEZONE', 'Europe/Warsaw')),
        'active_window_minutes' => (int) env('ANALYTICS_LIVE_VISITORS_ACTIVE_WINDOW_MINUTES', 30),
        'poll_interval_seconds' => (int) env('ANALYTICS_LIVE_VISITORS_POLL_INTERVAL_SECONDS', 15),
        'max_listed' => (int) env('ANALYTICS_LIVE_VISITORS_MAX_LISTED', 12),
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
