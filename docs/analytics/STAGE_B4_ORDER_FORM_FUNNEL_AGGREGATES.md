# Etap B4+ — agregaty lejka formularza (kanały / kurs / kampania / GUS / jakość)

Data: 2026-07-09  
Status: wdrożone lokalnie (`pneadm`)  
Powiązane: Etap 2F (`traffic_channel`, `order_form_attributions`), B3 porzuceń (bez zmian)

## Cel

Nowa warstwa **dziennych agregatów** lejka formularza zamówień, cięta po:

- `traffic_channel` / `conversion_reporting_channel`,
- kursie,
- kampanii,
- GUS (buyer / recipient / all),
- jakości trackingu.

**Nie rozszerza** istniejącego B3 (`analytics_daily_form_abandonment_stats`) — B3 pozostaje kompatybilnym raportem porzuceń.

## Źródła danych

- `analytics_events` (schema v2 + legacy tam, gdzie potrzebne),
- `order_form_attributions` (Etap 2F),
- backendowe eventy zamówień jako źródło prawdy dla `order_created`.

Zasady:

1. Liczone są **unikalne `form_session_id`**, nie surowa liczba eventów.
2. Kohorta wg **dnia pierwszego eventu** sesji (strefa `Europe/Warsaw`, jak B3).
3. Agregacja **idempotentna**: delete + insert per `stat_date`.
4. Brak atrybucji → `traffic_channel = unknown`, liczone w data quality.
5. Agregaty **bez PII** (brak NIP, e-maili, click_id, wartości pól).

## Tabele (`pne_analytics`)

Migracje:
- `database/migrations/2026_07_09_140000_create_analytics_daily_order_form_funnel_tables.php`
- `database/migrations/2026_07_09_150000_add_order_form_funnel_quality_and_submit_metrics.php`

| Tabela | Cel |
|---|---|
| `analytics_daily_channel_funnels` | Dzienny lejek per kanał ruchu |
| `analytics_daily_course_channel_funnels` | Lejek per dzień + kurs + kanał |
| `analytics_daily_campaign_funnels` | Raport kampanii / newsletter / paid social |
| `analytics_daily_gus_channel_funnels` | Wpływ GUS per kanał/kurs (korelacja) |
| `analytics_daily_data_quality` | Kompletność trackingu |

Opcjonalnie **nie wdrożono**: `analytics_daily_internal_promo_funnels` (brak eventów `internal_offer_impression` / `internal_offer_clicked`). Pola diagnostyczne `internal_promo_*` są w agregatach kanałowych.

## `conversion_reporting_channel`

```
conversion_reporting_channel = last_external_touch_channel ?? current_channel ?? 'unknown'
```

- `internal_site` **nie nadpisuje** `last_external_touch`.
- `direct` **nie nadpisuje** znanego `first_touch`.
- W raportach B4 kanał raportowy konwersji to kanał zewnętrzny, jeśli istnieje.

## Kanały (`traffic_channel`)

Z Etapu 2F: `newsletter`, `paid_social`, `organic_search`, `direct`, `referral`, `internal_site`, `paid_search`, `organic_social`, `unknown`, `other`.

## Definicje metryk lejka

| Metryka | Definicja |
|---|---|
| `sessions_total` | Unikalne sesje z `order_form_viewed` / `form_page_viewed` (wejście) |
| `form_visible` | Sesje z `form_visible` |
| `first_interaction` | Sesje z `form_first_interaction` |
| `reached_started` | `form_first_interaction` lub legacy `order_form_started` |
| `reached_submit_clicked` | `form_submit_clicked` lub legacy `order_form_submit_clicked` |
| `server_submit_attempted` | Backendowy submit attempted/post |
| `server_validation_failed` | Backendowa walidacja failed |
| `order_created` | Backendowy `form_order_created` / order snapshot |
| `server_only_conversions` | `order_created` bez frontendowych eventów formularza |
| `frontend_only_abandonments` | Frontendowe eventy bez `server_submit_attempted` i bez `order_created` |

### Porzucenia po submit (z tolerancją czasową)

| Metryka | Definicja |
|---|---|
| `pending_after_submit_clicked` | `form_submit_clicked`, brak `client_validation_failed`, brak `server_submit_attempted`, brak `order_created`, od submit minęło **< 60 min** (i dzień nie jest dojrzały przez lag) |
| `abandoned_after_submit_clicked` | jak wyżej, ale minęło **≥ 60 min** lub dzień statystyki jest dojrzały (`lag=2`) |
| `validation_abandonment` | `form_submit_clicked` + `client_validation_failed`, brak `order_created` |
| `server_validation_abandonment` | `server_submit_attempted` + `server_validation_failed`, brak `order_created` |
| `backend_result_missing` | `server_submit_attempted`, brak `server_validation_failed`, brak `order_create_failed`, brak `order_created` po okresie dojrzałości — **anomalia trackingu/backendu**, nie zwykłe porzucenie |
| `abandoned_after_server_validation_failed` | alias liczony razem z `server_validation_abandonment` (kompatybilność) |

Grace periods (config `analytics.order_form_funnel`):
- `grace_period_soft_minutes` = 15 (bez alarmów),
- `grace_period_final_minutes` = 60 (finalna klasyfikacja porzucenia).

Raporty dzienne finalizowane z `lag=2` — sesje z dnia D+2 są traktowane jako dojrzałe.

### Pozostałe porzucenia

| Metryka | Definicja |
|---|---|
| `abandonment_before_first_interaction` | `sessions_total - first_interaction` (bez `order_created`) |
| `abandonment_after_first_interaction` | `first_interaction - reached_submit_clicked` (bez `order_created`) |

### GUS

| Metryka | Definicja |
|---|---|
| `sessions_with_gus_lookup` | `gus_lookup_clicked` lub `gus_lookup_started` |
| `gus_success_sessions` | `gus_lookup_success` |
| `gus_error_sessions` | `gus_lookup_error` |
| `orders_after_gus_success` | success + `order_created` (success przed zamówieniem) |
| `orders_after_gus_error` | error + `order_created` tylko gdy error był ostatnim stanem GUS przed konwersją |
| `orders_without_gus` | `order_created` bez lookup GUS |
| `abandonment_after_gus_error` | error bez późniejszego `order_created` |
| `recovered_after_gus_error` | error → success → `order_created` |
| `sessions_with_gus_error_then_success` | error, potem success (niezależnie od zamówienia) |
| `gus_conversion_delta` | `conversion_rate_with_gus - conversion_rate_without_gus` |

> **Uwaga:** `gus_conversion_delta` i powiązane współczynniki to **korelacja obserwacyjna**, nie dowód przyczynowy wpływu GUS na konwersję.

### Jakość danych

`tracking_data_quality_status` (główny status):
- `complete`, `partial_frontend_tracking`, `backend_only`, `missing_attribution`,
- `low_volume` (sesje < 30 — bez twardych alertów),
- `warmup_or_deploy_window` (pierwsze 24h po wdrożeniu v2 lub mieszane `tracking_schema_version` w dniu),
- `pre_attribution_historical` (dni przed `attribution_deployed_at`, domyślnie **2026-07-09** — brak retroaktywnej atrybucji 2F; oczekiwane po backfillu).

Dodatkowo:
- `tracking_data_quality_flags` (JSON array, np. `frontend_coverage_low`, `server_only_conversions_elevated`),
- `tracking_data_quality_score` (0–100),
- `schema_v2_event_rate`.

**Direct nie jest `missing_attribution`.** `missing_attribution` = brak rekordu, `unknown`, NULL lub brak `traffic_channel`.

Progi operacyjne (przy `sessions_total >= 30`):
- `complete`: frontend ≥ 95%, traffic_channel ≥ 98%, attribution ≥ 95%, server_only ≤ 3%, orders_without_attribution ≤ 3%, schema_v2 ≥ 95%.
- Alerty CRITICAL/WARNING — patrz `OrderFormFunnelDataQualityEvaluator`.

Współczynniki: `frontend_tracking_coverage_rate`, `attribution_coverage_rate`, `traffic_channel_coverage_rate`, `campaign_coverage_rate`, `schema_v2_event_rate`.

## `internal_promo_placement`

Wymiar **diagnostyczny** — można filtrować / drill-down, **nie** główny wymiar pierwszego dashboardu.

## Komenda agregacji

```bash
sail artisan analytics:aggregate-order-forms
sail artisan analytics:aggregate-order-forms --date=2026-07-01
sail artisan analytics:aggregate-order-forms --from=2026-06-01 --to=2026-06-30
sail artisan analytics:aggregate-order-forms --yesterday
sail artisan analytics:aggregate-order-forms --rebuild --from=2026-06-01 --to=2026-06-30
```

- Domyślnie: dzień z lagiem `aggregation_lag_days` (domyślnie 2).
- Idempotentna — ponowne uruchomienie **nie dubluje** wierszy.
- `--rebuild` / `--force`: alias semantyczny (delete + insert).

### Cron (produkcja — od razu po wdrożeniu)

**03:45 Europe/Warsaw** (po B3 `03:15`):

```cron
45 3 * * * flock -n /tmp/pneadm-analytics-order-forms.lock php artisan analytics:aggregate-order-forms >> storage/logs/analytics-order-forms.log 2>&1
```

W Laravel Scheduler (`routes/console.php`): `analytics:aggregate-order-forms` dailyAt `03:45`.

### Backfill historyczny

**Decyzja biznesowa:** backfill B4 od **pierwszego dnia lejka B1/B2** (nie od schema v2).

```bash
sail artisan analytics:aggregate-order-forms --from=2026-06-25 --to=2026-07-08 --rebuild
```

`--from`: data pierwszego eventu **lejka formularza** (`order_form_viewed` / B1/B2) — prod: **2026-06-25**.  
**Schema v2** (`tracking_schema_version=2`) na prod od **2026-07-09** (wdrożenie `bc6deca`) — osobna data; nie używać jej jako `--from` backfillu historycznego.

## Raporty admin (`adm.pnedu.pl`)

- Route: `analytics.order-form-funnels.index` — menu **Analityka → Lejek formularza (kanały)**.
- Read-only: `OrderFormFunnelDashboardService` czyta wyłącznie tabele `analytics_daily_*_funnels`.
- UI: tabele **kanały**, **kurs × kanał**, **kampanie**, **jakość danych** (score + flagi); baner dla dni przed `attribution_deployed_at`.
- CSV: kanały, kursy, kampanie, GUS, jakość danych.
- Ręczne przeliczenie z UI (limit dni w config).

Klasy:

- `OrderFormFunnelAggregationService` — agregacja
- `OrderFormFunnelSubmitOutcomeClassifier` — pending/abandoned/validation/backend_missing
- `OrderFormFunnelDataQualityEvaluator` — status, flagi, score
- `OrderFormFunnelDashboardService` — odczyt
- `OrderFormFunnelCsvExportService` — eksport
- `AnalyticsOrderFormFunnelController` — UI + CSV + recompute

## Deploy produkcyjny — status 2026-07-09 ✅

**Wdrożone.** Runbook: [`docs/deploy/2026-07-B4-order-form-funnel-production-deploy.md`](../deploy/2026-07-B4-order-form-funnel-production-deploy.md).

1. ~~Wdrożyć `pneadm` z migracjami.~~ ✅ (`5d08134` + hotfixy do `cb4d732`)
2. ~~`php artisan migrate --force`~~ ✅
3. ~~Wdrożyć `pnedu` (2F)~~ ✅ (`bc6deca`)
4. ~~Backfill od pierwszego dnia lejka B1/B2~~ ✅ (`2026-06-25` … `2026-07-08`). Schema v2 na prod od `2026-07-09`.
5. ~~Cron 03:45~~ ✅

### Po wdrożeniu (operacyjne)

- Obserwacja od **2026-07-09** (`attribution_deployed_at` w config).
- Healthcheck na dniach historycznych: `pre_attribution_historical` — nie traktować jako awaria.
- Hotfixy post-deploy: `tracking_schema_version`, polling dashboardu, kolumna Wejście (UTM/direct).

### Kolejność techniczna (dla przyszłych deployów)

1. `pneadm`: `git pull` → `migrate --force` → cache.
2. `pnedu`: `git pull` → cache → `queue:restart`.
3. Smoke: formularz + debug-events + dashboard B4.
4. Backfill tylko przy zmianie logiki agregacji (z prawdziwymi datami `YYYY-MM-DD`).

## Testy

`tests/Feature/AnalyticsOrderFormFunnelAggregationTest.php` — 20 testów + `AnalyticsOrderFormFunnelHealthcheckCommandTest` — 5 testów (kanały, GUS, idempotencja, data quality, grace period submit, regresja B3, pre_attribution).

```bash
sail test --filter=AnalyticsOrderFormFunnelAggregationTest
```

## Healthcheck (osobna komenda)

```bash
sail artisan analytics:order-form-funnel-healthcheck
sail artisan analytics:order-form-funnel-healthcheck --from=2026-06-01 --to=2026-06-30
sail artisan analytics:order-form-funnel-healthcheck --days=14
```

- **NIE** zastępuje `analytics:abandonment-healthcheck` (B3).
- Sprawdza: dopływ eventów v2 (okno 60 min), agregaty `analytics_daily_data_quality`, progi CRITICAL/WARNING z `OrderFormFunnelDataQualityEvaluator`.
- `low_volume`, `warmup_or_deploy_window` i **`pre_attribution_historical`** (dni przed `attribution_deployed_at`, domyślnie 2026-07-09) — pomijają twarde alerty.
- Exit code `1` tylko przy CRITICAL.

Testy: `AnalyticsOrderFormFunnelHealthcheckCommandTest` (4).

## Naprawa brakujących tabel B3/R1

Jeśli migracje B3/R1 są oznaczone jako wykonane, ale tabele nie istnieją na `pne_analytics`:

```bash
sail artisan migrate --path=database/migrations/2026_07_09_160000_repair_missing_analytics_b3_r1_tables.php --force
```

## Czego nie zmieniano

- UI/copywriting formularza (`pnedu`),
- logika zamówień,
- eventy frontendowe v2,
- eventy GUS/NIP,
- klasyfikacja `traffic_channel` (2F),
- legacy B1/B2,
- B3 abandonments (osobna komenda i tabele).
