# Plan Schematu Bazy `pne_analytics`

Data utworzenia/aktualizacji: 2026-06-24  
Status: wersja robocza, do potwierdzenia przez właściciela

## Cel Bazy

`pne_analytics` ma być osobną bazą danych do analityki eventowej i agregatów. Nie zastępuje `pneadm` ani `pnedu`. Nie przechowuje danych osobowych potrzebnych operacyjnie do zamówień, faktur ani uczestników.

## Decyzja O Relacjach Do `pneadm`

Rekomendacja: nie stosować twardych foreign key do tabel w `pneadm`.

Zamiast tego przechowywać:

- identyfikatory (`course_id`, `campaign_id`, `form_order_id`),
- kody (`campaign_code`),
- snapshoty bez danych osobowych (`course_title_snapshot`, `campaign_name_snapshot`),
- metadane techniczne i biznesowe bez PII.

Uzasadnienie:

- mniejsze ryzyko blokowania transakcji,
- łatwiejsza retencja i archiwizacja,
- zachowanie historii po zmianie/usunięciu danych źródłowych,
- prostsza praca na dev,
- brak cross-database FK.

## Etap 1 — Tabele MVP

### `analytics_sessions`

Cel: pseudonimowa sesja użytkownika.

Przykładowe kolumny:

- `id`,
- `analytics_session_id`,
- `first_seen_at`,
- `last_seen_at`,
- `app_source`,
- `tracking_mode`,
- `sample_rate`,
- `utm_source`,
- `utm_medium`,
- `utm_campaign`,
- `utm_content`,
- `utm_term`,
- `campaign_id`,
- `campaign_code`,
- `landing_target`,
- `device_type`,
- `browser_family`,
- `landing_url`,
- `referrer_host`,
- `created_at`,
- `updated_at`.

Indeksy:

- unique `analytics_session_id`,
- index `campaign_code`,
- index `first_seen_at`,
- index `tracking_mode`.

Dane osobowe: nie, pod warunkiem że nie zapisujemy IP i pełnego user agenta.

Retencja: 6-12 miesięcy.

### `analytics_events`

Cel: centralny raw event log.

Przykładowe kolumny:

- `id`,
- `event_uuid`,
- `event_name`,
- `event_category`,
- `occurred_at`,
- `app_source`,
- `analytics_session_id`,
- `course_id`,
- `course_title_snapshot`,
- `campaign_id`,
- `campaign_code`,
- `landing_target`,
- `campaign_content_depth`,
- `campaign_channel`,
- `cta_type`,
- `utm_source`,
- `utm_medium`,
- `utm_campaign`,
- `utm_content`,
- `utm_term`,
- `order_form_session_id`,
- `form_order_id`,
- `payment_order_id`,
- `ab_test_id`,
- `ab_variant_id`,
- `metadata_json`,
- `created_at`.

Indeksy:

- unique `event_uuid`,
- index `event_name`,
- index `event_category`,
- index `occurred_at`,
- index `analytics_session_id`,
- index `course_id`,
- index `campaign_code`,
- index `form_order_id`,
- index `order_form_session_id`.

Dane osobowe: nie, jeśli `metadata_json` jest filtrowany.

Retencja raw: 3-6 miesięcy. Agregaty dłużej.

### `landing_page_views`

Cel: widoki opisu szkolenia i landingów.

Przykładowe kolumny:

- `id`,
- `analytics_session_id`,
- `occurred_at`,
- `course_id`,
- `course_title_snapshot`,
- `campaign_id`,
- `campaign_code`,
- `landing_target`,
- `url_path`,
- `referrer_host`,
- `device_type`,
- `browser_family`,
- `created_at`.

Indeksy:

- index `course_id`,
- index `campaign_code`,
- index `occurred_at`,
- index `landing_target`.

Dane osobowe: nie.

Retencja: 6-12 miesięcy.

### `order_form_sessions`

Cel: jedna sesja pracy użytkownika z formularzem zamówienia.

Przykładowe kolumny:

- `id`,
- `order_form_session_id`,
- `analytics_session_id`,
- `course_id`,
- `course_title_snapshot`,
- `campaign_id`,
- `campaign_code`,
- `landing_target`,
- `tracking_mode`,
- `started_at`,
- `first_interaction_at`,
- `last_event_at`,
- `submitted_at`,
- `completed_at`,
- `abandoned_at`,
- `form_order_id`,
- `payment_order_id`,
- `buyer_type`,
- `payment_type`,
- `payment_gateway`,
- `participant_count`,
- `has_recipient`,
- `gus_lookup_used`,
- `ksef_option_selected`,
- `invoice_path_type`,
- `created_at`,
- `updated_at`.

Indeksy:

- unique `order_form_session_id`,
- index `analytics_session_id`,
- index `course_id`,
- index `campaign_code`,
- index `form_order_id`,
- index `started_at`,
- index `abandoned_at`.

Dane osobowe: nie.

Retencja: 12 miesięcy.

### `order_form_events`

Cel: interakcje w formularzu.

Przykładowe kolumny:

- `id`,
- `event_uuid`,
- `order_form_session_id`,
- `analytics_session_id`,
- `event_name`,
- `occurred_at`,
- `section_key`,
- `field_key`,
- `field_state`,
- `buyer_type`,
- `payment_type`,
- `metadata_json`,
- `created_at`.

Indeksy:

- unique `event_uuid`,
- index `order_form_session_id`,
- index `event_name`,
- index `section_key`,
- index `field_key`,
- index `occurred_at`.

Dane osobowe: nie, nie zapisywać wartości pól.

Retencja raw: 3-6 miesięcy.

### `validation_error_events`

Cel: analiza błędów walidacji.

Przykładowe kolumny:

- `id`,
- `event_uuid`,
- `order_form_session_id`,
- `analytics_session_id`,
- `course_id`,
- `form_order_id`,
- `field_key`,
- `section_key`,
- `rule_key`,
- `error_group`,
- `occurred_at`,
- `created_at`.

Indeksy:

- unique `event_uuid`,
- index `field_key`,
- index `rule_key`,
- index `course_id`,
- index `occurred_at`.

Dane osobowe: nie.

Retencja: 6-12 miesięcy.

### `conversion_events`

Cel: zdarzenia biznesowe kończące etapy lejka.

Przykładowe kolumny:

- `id`,
- `event_uuid`,
- `event_name`,
- `occurred_at`,
- `analytics_session_id`,
- `order_form_session_id`,
- `course_id`,
- `campaign_id`,
- `campaign_code`,
- `form_order_id`,
- `payment_order_id`,
- `amount_snapshot`,
- `payment_status`,
- `invoice_path_type`,
- `ab_test_id`,
- `ab_variant_id`,
- `created_at`.

Indeksy:

- unique `event_uuid`,
- index `event_name`,
- index `course_id`,
- index `campaign_code`,
- index `form_order_id`,
- index `occurred_at`.

Dane osobowe: nie.

Retencja: 24 miesiące lub dłużej.

### `analytics_daily_course_stats`

Cel: dzienne agregaty per szkolenie.

Przykładowe kolumny:

- `id`,
- `stat_date`,
- `course_id`,
- `course_title_snapshot`,
- `views_course_description`,
- `views_order_form`,
- `form_starts`,
- `submit_attempts`,
- `validation_failures`,
- `orders_created`,
- `payment_orders_created`,
- `paid_orders`,
- `invoiced_orders`,
- `revenue_snapshot`,
- `created_at`,
- `updated_at`.

Indeksy:

- unique `stat_date, course_id`,
- index `stat_date`,
- index `course_id`.

Dane osobowe: nie.

Retencja: 3+ lata.

### `analytics_daily_campaign_stats`

Cel: dzienne agregaty per kampania.

Przykładowe kolumny:

- `id`,
- `stat_date`,
- `campaign_id`,
- `campaign_code`,
- `campaign_name_snapshot`,
- `campaign_channel`,
- `campaign_content_depth`,
- `landing_target`,
- `cta_type`,
- `link_entries`,
- `course_description_views`,
- `order_form_views`,
- `form_starts`,
- `orders_created`,
- `paid_orders`,
- `invoiced_orders`,
- `revenue_snapshot`,
- `created_at`,
- `updated_at`.

Indeksy:

- unique `stat_date, campaign_code, landing_target, campaign_content_depth, cta_type`,
- index `campaign_code`,
- index `stat_date`,
- index `landing_target`.

Dane osobowe: nie.

Retencja: 3+ lata.

## Etap 2 — Porzucenia I Lepszy Formularz

Tabele:

- `order_form_abandonments`,
- `payment_attempt_events`,
- `cta_click_events`.

`order_form_abandonments` powinno zawierać:

- `order_form_session_id`,
- `course_id`,
- `campaign_code`,
- `last_event_name`,
- `last_section_key`,
- `last_field_key`,
- `time_spent_seconds`,
- `abandoned_at`,
- `reason_guess`.

## Etap 3 — Testy A/B

Tabele:

- `ab_tests`,
- `ab_test_variants`,
- `ab_test_assignments`,
- `ab_test_events`,
- `ab_test_results`.

Przypisanie użytkownika do wariantu powinno używać `analytics_session_id`.

## Etap 4 — AI I Raporty

Tabele:

- `analytics_snapshots`,
- `ai_reports`,
- `ai_interactions`,
- `ai_usage_logs`,
- `ai_report_exports`.

W MVP nie wdrażać AI, tylko przygotować strukturę danych i eksportów AI-safe.

## Zasady Retencji

Rekomendacja robocza:

- raw eventy JS: 3-6 miesięcy,
- sesje formularza: 12 miesięcy,
- conversion events: 24 miesiące,
- agregaty dzienne: 3+ lata,
- eksporty AI-safe: 12-24 miesiące,
- logi AI w przyszłości: do osobnej decyzji i DPIA.

## Zasady Backupu

Do potwierdzenia:

- czy `pne_analytics` będzie backupowana razem z `pneadm`,
- jak długo trzymać backupy,
- czy raw eventy powinny być backupowane tak długo jak agregaty.

Rekomendacja:

- backupować agregaty i snapshoty,
- raw eventy mogą mieć krótszą retencję i krótszy backup.

## Zasady Pracy Na Dev

Rekomendacja:

- lokalna baza dev `pne_analytics` w tym samym kontenerze MySQL co `pneadm`,
- dane syntetyczne albo zanonimizowane,
- nie kopiować raw danych produkcyjnych z identyfikatorami sesji bez potrzeby,
- jeśli kopiować, usuwać potencjalnie identyfikujące `url`, `referrer`, pełne user agenty.

## Plan Implementacji Etapu 0/1

Status: plan migracji MVP, bez utworzonych migracji.  
Data dopisania: 2026-06-24.

### Środowiska

Produkcja:

- baza istnieje jako produkcyjna baza analityczna,
- host: `localhost`,
- database: `srv66127_pne_analytics`,
- username: `srv66127_pne_analytics`,
- hasło wyłącznie w `.env` produkcji, nigdy w repozytorium ani dokumentacji.

Dev:

- baza: `pne_analytics`,
- host z `pneadm`: `mysql`,
- host z `pnedu`: do potwierdzenia testem, prawdopodobnie `pneadm-mysql` albo alias sieciowy,
- użytkownik: `sail`,
- hasło: lokalne hasło Sail.

### Reguły Migracji

- Migracje dla `pne_analytics` powinny powstać w projekcie `pneadm`, ponieważ `pneadm` pełni rolę backoffice i właściciela danych analitycznych.
- Każda migracja musi używać `Schema::connection('analytics')`.
- Nie stosować twardych FK do `pneadm`.
- Używać `unsignedBigInteger` dla ID z innych baz.
- Przechowywać snapshoty tekstowe bez danych osobowych.
- Używać `json('metadata')->nullable()` tylko po przejściu przez sanitizer.

### Tabele MVP — Dokładny Plan Kolumn

#### `analytics_sessions`

- Cel: pseudonimowa sesja analityczna użytkownika.
- Dane osobowe: nie, pod warunkiem że nie zapisujemy IP ani pełnego user agenta.
- Retencja: 365 dni, do potwierdzenia.
- Relacje logiczne: `campaign_id`, `campaign_code`; bez FK.

Kolumny:

- `id()`,
- `uuid('analytics_session_id')->unique()`,
- `timestamp('first_seen_at')->index()`,
- `timestamp('last_seen_at')->nullable()->index()`,
- `string('app_source', 32)->index()`,
- `string('tracking_mode', 32)->default('standard')->index()`,
- `unsignedTinyInteger('sample_rate')->default(100)`,
- `string('utm_source', 100)->nullable()->index()`,
- `string('utm_medium', 100)->nullable()`,
- `string('utm_campaign', 150)->nullable()->index()`,
- `string('utm_content', 150)->nullable()`,
- `string('utm_term', 150)->nullable()`,
- `unsignedBigInteger('campaign_id')->nullable()->index()`,
- `string('campaign_code', 100)->nullable()->index()`,
- `string('landing_target', 50)->nullable()->index()`,
- `string('device_type', 32)->nullable()`,
- `string('browser_family', 64)->nullable()`,
- `string('landing_path', 500)->nullable()`,
- `string('referrer_host', 255)->nullable()`,
- `timestamps()`.

Przykład:

```json
{"analytics_session_id":"uuid","app_source":"pnedu","campaign_code":"DYR-2026-06","tracking_mode":"standard","landing_target":"course_description"}
```

Ryzyka: pełny URL może zawierać dane w query string, dlatego zapisywać `landing_path`, nie pełny `landing_url`.

#### `analytics_events`

- Cel: centralny raw event log.
- Dane osobowe: nie, jeśli `metadata` jest filtrowane whitelistą.
- Retencja: 180 dni raw eventów, do potwierdzenia.
- Relacje logiczne: `course_id`, `campaign_id`, `form_order_id`, `payment_order_id`; bez FK.

Kolumny:

- `id()`,
- `uuid('event_uuid')->unique()`,
- `string('event_name', 100)->index()`,
- `string('event_category', 50)->index()`,
- `timestamp('occurred_at')->index()`,
- `string('app_source', 32)->index()`,
- `uuid('analytics_session_id')->nullable()->index()`,
- `unsignedBigInteger('course_id')->nullable()->index()`,
- `string('course_title_snapshot', 255)->nullable()`,
- `unsignedBigInteger('campaign_id')->nullable()->index()`,
- `string('campaign_code', 100)->nullable()->index()`,
- `string('landing_target', 50)->nullable()->index()`,
- `string('campaign_content_depth', 50)->nullable()->index()`,
- `string('campaign_channel', 50)->nullable()->index()`,
- `string('cta_type', 50)->nullable()->index()`,
- `string('utm_source', 100)->nullable()`,
- `string('utm_medium', 100)->nullable()`,
- `string('utm_campaign', 150)->nullable()`,
- `string('utm_content', 150)->nullable()`,
- `string('utm_term', 150)->nullable()`,
- `uuid('order_form_session_id')->nullable()->index()`,
- `unsignedBigInteger('form_order_id')->nullable()->index()`,
- `unsignedBigInteger('payment_order_id')->nullable()->index()`,
- `unsignedBigInteger('ab_test_id')->nullable()->index()`,
- `unsignedBigInteger('ab_variant_id')->nullable()->index()`,
- `json('metadata')->nullable()`,
- `timestamp('created_at')->nullable()->useCurrent()`.

Indeksy dodatkowe:

- `['event_name', 'occurred_at']`,
- `['campaign_code', 'occurred_at']`,
- `['course_id', 'occurred_at']`.

Przykład:

```json
{"event_name":"order_form_viewed","event_category":"order_form","course_id":123,"campaign_code":"DYR-2026-06","metadata":{"payment_type":null}}
```

Ryzyka: `metadata` jest największym ryzykiem RODO; wymagana whitelist i testy sanitizera.

#### `landing_page_views`

- Cel: zoptymalizowany zapis widoków opisu/landingu.
- Dane osobowe: nie.
- Retencja: 365 dni.
- Relacje logiczne: `course_id`, `campaign_id`, `campaign_code`; bez FK.

Kolumny:

- `id()`,
- `uuid('analytics_session_id')->nullable()->index()`,
- `timestamp('occurred_at')->index()`,
- `unsignedBigInteger('course_id')->nullable()->index()`,
- `string('course_title_snapshot', 255)->nullable()`,
- `unsignedBigInteger('campaign_id')->nullable()->index()`,
- `string('campaign_code', 100)->nullable()->index()`,
- `string('landing_target', 50)->nullable()->index()`,
- `string('url_path', 500)->nullable()`,
- `string('referrer_host', 255)->nullable()`,
- `string('device_type', 32)->nullable()`,
- `string('browser_family', 64)->nullable()`,
- `timestamp('created_at')->nullable()->useCurrent()`.

Przykład:

```json
{"course_id":123,"landing_target":"course_description","url_path":"/courses/123","referrer_host":"facebook.com"}
```

Ryzyka: nie zapisywać query string, jeśli może zawierać dane.

#### `order_form_sessions`

- Cel: jedna sesja pracy z formularzem.
- Dane osobowe: nie.
- Retencja: 365 dni.
- Relacje logiczne: `course_id`, `campaign_code`, `form_order_id`, `payment_order_id`; bez FK.

Kolumny:

- `id()`,
- `uuid('order_form_session_id')->unique()`,
- `uuid('analytics_session_id')->nullable()->index()`,
- `unsignedBigInteger('course_id')->nullable()->index()`,
- `string('course_title_snapshot', 255)->nullable()`,
- `unsignedBigInteger('campaign_id')->nullable()->index()`,
- `string('campaign_code', 100)->nullable()->index()`,
- `string('landing_target', 50)->nullable()->index()`,
- `string('tracking_mode', 32)->default('standard')->index()`,
- `timestamp('started_at')->nullable()->index()`,
- `timestamp('first_interaction_at')->nullable()`,
- `timestamp('last_event_at')->nullable()->index()`,
- `timestamp('submitted_at')->nullable()->index()`,
- `timestamp('completed_at')->nullable()->index()`,
- `timestamp('abandoned_at')->nullable()->index()`,
- `unsignedBigInteger('form_order_id')->nullable()->index()`,
- `unsignedBigInteger('payment_order_id')->nullable()->index()`,
- `string('buyer_type', 50)->nullable()->index()`,
- `string('payment_type', 50)->nullable()->index()`,
- `string('payment_gateway', 50)->nullable()->index()`,
- `unsignedSmallInteger('participant_count')->nullable()`,
- `boolean('has_recipient')->nullable()`,
- `boolean('gus_lookup_used')->default(false)`,
- `boolean('gus_lookup_success')->nullable()`,
- `string('ksef_option_selected', 50)->nullable()`,
- `string('invoice_path_type', 50)->nullable()->index()`,
- `timestamps()`.

Przykład:

```json
{"order_form_session_id":"uuid","course_id":123,"payment_type":"deferred","buyer_type":"school","participant_count":1}
```

Ryzyka: nie zapisywać nazw szkół, NIP ani wartości pól formularza.

#### `conversion_events`

- Cel: konwersje biznesowe: zamówienie, płatność, faktura.
- Dane osobowe: nie.
- Retencja: minimum 24 miesiące, rekomendowane dłużej.
- Relacje logiczne: `form_order_id`, `payment_order_id`, `course_id`, `campaign_code`; bez FK.

Kolumny:

- `id()`,
- `uuid('event_uuid')->unique()`,
- `string('event_name', 100)->index()`,
- `timestamp('occurred_at')->index()`,
- `uuid('analytics_session_id')->nullable()->index()`,
- `uuid('order_form_session_id')->nullable()->index()`,
- `unsignedBigInteger('course_id')->nullable()->index()`,
- `unsignedBigInteger('campaign_id')->nullable()->index()`,
- `string('campaign_code', 100)->nullable()->index()`,
- `unsignedBigInteger('form_order_id')->nullable()->index()`,
- `unsignedBigInteger('payment_order_id')->nullable()->index()`,
- `decimal('amount_snapshot', 10, 2)->nullable()`,
- `string('payment_status', 50)->nullable()->index()`,
- `string('payment_type', 50)->nullable()->index()`,
- `string('payment_gateway', 50)->nullable()->index()`,
- `string('invoice_path_type', 50)->nullable()->index()`,
- `boolean('has_recipient')->nullable()`,
- `string('ksef_option_selected', 50)->nullable()`,
- `unsignedBigInteger('ab_test_id')->nullable()->index()`,
- `unsignedBigInteger('ab_variant_id')->nullable()->index()`,
- `timestamp('created_at')->nullable()->useCurrent()`.

Przykład:

```json
{"event_name":"form_order_created","form_order_id":987,"course_id":123,"amount_snapshot":"299.00","payment_type":"online"}
```

Ryzyka: nie zapisywać numerów faktur, danych płatnika ani danych bramki.

#### `validation_error_events`

- Cel: analiza błędów walidacji bez wartości pól.
- Dane osobowe: nie.
- Retencja: 365 dni.
- Relacje logiczne: `course_id`, `form_order_id`, `order_form_session_id`; bez FK.

Kolumny:

- `id()`,
- `uuid('event_uuid')->unique()`,
- `uuid('order_form_session_id')->nullable()->index()`,
- `uuid('analytics_session_id')->nullable()->index()`,
- `unsignedBigInteger('course_id')->nullable()->index()`,
- `unsignedBigInteger('form_order_id')->nullable()->index()`,
- `string('field_key', 100)->index()`,
- `string('section_key', 100)->nullable()->index()`,
- `string('rule_key', 100)->nullable()->index()`,
- `string('error_group', 100)->nullable()->index()`,
- `timestamp('occurred_at')->index()`,
- `timestamp('created_at')->nullable()->useCurrent()`.

Przykład:

```json
{"field_key":"buyer_nip","section_key":"invoice","rule_key":"required","error_group":"missing_required"}
```

Ryzyka: `field_key` i `rule_key` nie mogą zawierać wartości pola.

#### `analytics_daily_course_stats`

- Cel: agregaty dzienne per szkolenie.
- Dane osobowe: nie.
- Retencja: minimum 3 lata albo bezterminowo, do potwierdzenia.
- Relacje logiczne: `course_id`; bez FK.

Kolumny:

- `id()`,
- `date('stat_date')->index()`,
- `unsignedBigInteger('course_id')->index()`,
- `string('course_title_snapshot', 255)->nullable()`,
- `unsignedInteger('views_course_description')->default(0)`,
- `unsignedInteger('views_order_form')->default(0)`,
- `unsignedInteger('form_starts')->default(0)`,
- `unsignedInteger('submit_attempts')->default(0)`,
- `unsignedInteger('validation_failures')->default(0)`,
- `unsignedInteger('orders_created')->default(0)`,
- `unsignedInteger('payment_orders_created')->default(0)`,
- `unsignedInteger('paid_orders')->default(0)`,
- `unsignedInteger('invoiced_orders')->default(0)`,
- `decimal('revenue_snapshot', 12, 2)->default(0)`,
- `timestamps()`,
- unique `['stat_date', 'course_id']`.

Przykład:

```json
{"stat_date":"2026-06-24","course_id":123,"views_order_form":40,"orders_created":8,"revenue_snapshot":"2392.00"}
```

Ryzyka: agregaty mogą różnić się od obecnych tabel `course_page_stats_daily` do czasu ujednolicenia dashboardu.

Status Etapu 1C (wdrożone w `adm.pnedu.pl`):

- komenda `analytics:aggregate-daily` przelicza eventy z `analytics_events` dla `stat_date` w strefie `Europe/Warsaw`,
- `submit_attempts` = liczba eventów `order_form_submit_attempted` (próby submitu, nie skuteczne zamówienia),
- `orders_created` = liczba eventów `form_order_created`,
- `revenue_snapshot` = suma `metadata.amount_gross` z eventów `form_order_created`,
- brak kolumn `unique_sessions` / `unique_order_form_sessions` w schemacie MVP — nie liczone w 1C.

#### `analytics_daily_campaign_stats`

- Cel: agregaty dzienne per kampania i wariant ścieżki.
- Dane osobowe: nie.
- Retencja: minimum 3 lata albo bezterminowo, do potwierdzenia.
- Relacje logiczne: `campaign_id`, `campaign_code`; bez FK.

Kolumny:

- `id()`,
- `date('stat_date')->index()`,
- `unsignedBigInteger('campaign_id')->nullable()->index()`,
- `string('campaign_code', 100)->index()`,
- `string('campaign_name_snapshot', 255)->nullable()`,
- `string('campaign_channel', 50)->nullable()->index()`,
- `string('campaign_content_depth', 50)->nullable()->index()`,
- `string('landing_target', 50)->nullable()->index()`,
- `string('cta_type', 50)->nullable()->index()`,
- `unsignedInteger('link_entries')->default(0)`,
- `unsignedInteger('course_description_views')->default(0)`,
- `unsignedInteger('order_form_views')->default(0)`,
- `unsignedInteger('form_starts')->default(0)`,
- `unsignedInteger('submit_attempts')->default(0)`,
- `unsignedInteger('validation_failures')->default(0)`,
- `unsignedInteger('orders_created')->default(0)`,
- `unsignedInteger('paid_orders')->default(0)`,
- `unsignedInteger('invoiced_orders')->default(0)`,
- `decimal('revenue_snapshot', 12, 2)->default(0)`,
- `timestamps()`,
- unique `['stat_date', 'campaign_code', 'landing_target', 'campaign_content_depth', 'cta_type']`.

Przykład:

```json
{"stat_date":"2026-06-24","campaign_code":"DYR-2026-06","landing_target":"order_form_direct","link_entries":120,"orders_created":10}
```

Ryzyka: `campaign_code` musi wystarczyć jako snapshot, gdy kampania zostanie później zmieniona lub usunięta.

Status Etapu 1C (wdrożone w `adm.pnedu.pl`):

- rollup po `stat_date + campaign_code`; wymiary `landing_target`, `campaign_content_depth`, `cta_type` w rollupie = `NULL`,
- `link_entries` z `campaign_short_link_visit`; brak kolumn na `campaign_redirect_resolved` i `utm_captured` w schemacie MVP,
- eventy bez `campaign_code` są pomijane,
- `submit_attempts` = `order_form_submit_attempted`, `orders_created` = `form_order_created`.

### Decyzje Nadal Wymagające Potwierdzenia

- Retencja raw eventów: rekomendacja 180 dni.
- Retencja sesji formularza: rekomendacja 365 dni.
- Retencja agregatów: minimum 3 lata albo bezterminowo.
- Retencja eksportów AI-safe: 180-365 dni.
- Backup raw eventów: czy tak samo długo jak agregaty.
- Czy lokalna baza ma nazywać się `pne_analytics` czy `pne_analytics_dev`. Rekomendacja: `pne_analytics`, żeby oba projekty lokalne widziały tę samą bazę.

## Status Wdrożenia Etapu 0

Data aktualizacji: 2026-06-24.  
Status: migracja MVP wdrożona lokalnie w projekcie `pneadm`.

Utworzono migrację:

- `database/migrations/2026_06_24_120000_create_pne_analytics_mvp_tables.php`.

Migracja:

- używa `Schema::connection('analytics')`,
- znajduje się tylko w projekcie `pneadm`,
- tworzy tabele MVP,
- nie tworzy twardych FK do `pneadm`,
- nie przechowuje raw `url` ani raw `referrer`,
- używa pól `route_name`, `path`, `referrer_domain`,
- pozwala zapisać `form_order_id`, ale dokumentacja eksportów AI-safe nadal zakazuje eksportowania tego identyfikatora.

Tabele utworzone lokalnie:

- `analytics_sessions`,
- `analytics_events`,
- `landing_page_views`,
- `order_form_sessions`,
- `conversion_events`,
- `validation_error_events`,
- `analytics_daily_course_stats`,
- `analytics_daily_campaign_stats`.

Lokalna konfiguracja:

- baza: `pne_analytics`,
- MySQL: wspólny kontener `pneadm-mysql`,
- użytkownik dev: `sail`,
- oba projekty widzą connection `analytics`.

Produkcja:

- baza produkcyjna istnieje,
- production secret nie został zapisany w repozytorium,
- hasło powinno być wpisane wyłącznie w `.env` produkcji i zrotowane po zakończeniu konfiguracji.

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalne migracje.
- Dopisać realne typy kolumn.
- Dopisać finalne indeksy po testach wydajności.
- Dopisać procedurę retencji i archiwizacji.
