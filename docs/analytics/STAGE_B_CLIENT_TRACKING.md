# Etap B — JS tracking formularza zamówienia + porzucenia

Data utworzenia: 2026-06-25
Status: **Etap B w pełni wdrożony produkcyjnie** (2026-06-26). Prod `pneadm` HEAD: `5526e96`. B1–B6 + recompute + presety + healthcheck + porównanie okresów.

## Cel etapu

Uzupełnić lukę między backendowym `order_form_viewed` a `order_form_submit_attempted` / `form_order_created`:

1. Ilu użytkowników wchodzi w formularz, ale go nie zaczyna?
2. Ilu zaczyna, ale nie klika wysłania?
3. Na których sekcjach formularza najczęściej kończą aktywność?
4. Czy klikają CTA, ale nie tworzą zamówienia?
5. Które kursy / warianty ceny / kampanie generują najwięcej porzuceń?

## Kolejność PR-ów

| PR | Zakres | Status |
|----|--------|--------|
| **B1** | Endpoint batch + enumy + sanitizer + tryby + testy RODO | ✅ ZROBIONE |
| **B1a** | Hardening: same-origin guard + namespacowanie `event_uuid` | ✅ ZROBIONE |
| **B2** | Lekki JS collector na formularzu zamówienia | ✅ ZROBIONE (`bdc74ca`, pushed) |
| B3 | Agregacja porzuceń (komenda, idempotentna) | ✅ ZROBIONE (`b0b4535`, deployed 2026-06-25) |
| B4 | Dashboard porzuceń (read-only, agregaty B3) | ✅ ZROBIONE (`pneadm` `a6ee852`, prod 2026-06-26) |
| B5 | CSV AI-safe export z dashboardu porzuceń | ✅ ZROBIONE (`pneadm` `cb8046a`, prod 2026-06-26) |
| B6 | Wykres trendu dziennego + dzienny CSV | ✅ ZROBIONE (`pneadm` `5a2e2b5`, prod 2026-06-26) |
| — | Przycisk „Przelicz porzucenia” | ✅ ZROBIONE (`pneadm` `69d6e83`, prod 2026-06-26) |
| — | Presety zakresów dat (lejek + porzucenia) | ✅ ZROBIONE (`pneadm` `9f9fd23`, prod 2026-06-26) |
| — | Komenda `analytics:abandonment-healthcheck` | ✅ ZROBIONE (`pneadm` `6608791`, prod 2026-06-26) |
| — | Porównanie okres-do-okresu (oba dashboardy) | ✅ ZROBIONE (`pneadm` `5526e96`, prod 2026-06-26) |

---

## PR B1 — szczegóły wdrożenia

### Endpoint

```
POST /analytics/client-events        (nazwa trasy: analytics.client-events.store)
```

Projekt: **pnedu** (formularz zamówienia żyje w pnedu, baza analityki = `pne_analytics`).

### Kontrakt wejścia (JSON)

```json
{
  "course_id": 524,
  "price_variant_id": 78,
  "events": [
    { "event_name": "order_form_started", "event_uuid": "<uuid>", "trigger": "first_interaction" },
    { "event_name": "order_form_section_interacted", "section_key": "buyer_data" },
    { "event_name": "order_form_cta_clicked", "cta_key": "add_participant" },
    { "event_name": "order_form_submit_clicked" }
  ]
}
```

- `course_id` — wymagany, dodatnia liczba całkowita (inaczej batch jest porzucany).
- `price_variant_id` — opcjonalny; trafia do `metadata.price_variant_id`.
- `events` — tablica, maks. **20** eventów (nadmiar jest ucinany).
- `event_uuid` — opcjonalny; akceptowany **tylko** gdy jest poprawnym UUID (dedup retry), inaczej serwer generuje własny.
- `occurred_at_client` — **ignorowane**; źródłem prawdy jest czas serwera.

### Eventy dozwolone z przeglądarki (MVP)

Tylko te 4 nazwy mogą pochodzić z JS (reszta wyłącznie z backendu):

| event_name | wymaga | kategoria |
|------------|--------|-----------|
| `order_form_started` | (opcjonalnie `trigger`) | `order_form` |
| `order_form_section_interacted` | `section_key` (whitelist) | `order_form` |
| `order_form_cta_clicked` | `cta_key` (whitelist) | `order_form` |
| `order_form_submit_clicked` | — | `order_form` |

Event sekcji/CTA bez whitelistowanego klucza jest **pomijany** (nie zapisujemy tekstu z DOM).

### Whitelisty wartości (kontroler `ClientEventController`)

- `section_key`: `buyer_data`, `recipient_data`, `participants`, `payment_method`, `invoice`, `consents`, `summary`
- `cta_key`: `add_participant`, `remove_participant`, `select_online_payment`, `select_deferred_invoice`, `back_to_course`, `submit_order`
- `trigger`: `first_interaction`, `field_change`, `section_click`, `payment_select`, `cta_click`, `page_focus`

### Tryby analityki a eventy JS

Egzekwowane przez `AnalyticsModeResolver` (jak dla eventów backendowych):

| Tryb | Eventy JS |
|------|-----------|
| `off` | brak |
| `aggregate_only` | brak |
| `light` | tylko `order_form_started` i `order_form_submit_clicked` |
| `standard` | wszystkie 4 (MVP) |
| `full` | wszystkie 4 (MVP) + przyszłe szczegółowe |

Decyzja: **standard obejmuje MVP JS** — inaczej po wdrożeniu na produkcji (która jest na `standard`) nie byłoby żadnych danych.

### Limity i rate limit (konfigurowalne w `config/analytics.php`)

| Ustawienie | Domyślnie | ENV |
|-----------|-----------|-----|
| maks. eventów / batch | 20 | `ANALYTICS_CLIENT_EVENTS_MAX_BATCH` |
| maks. rozmiar payloadu | 10240 B | `ANALYTICS_CLIENT_EVENTS_MAX_PAYLOAD_BYTES` |
| rate limit / min / IP | 60 | `ANALYTICS_CLIENT_EVENTS_RATE_LIMIT` |

Rate limiter: nazwany limiter `analytics-client-events` (per IP) w `AppServiceProvider`.

### Bezpieczeństwo / RODO

- Endpoint **zawsze** odpowiada `204 No Content` — nigdy nie blokuje użytkownika (fail-silent).
- Zapisujemy wyłącznie techniczne klucze z whitelisty (`AnalyticsPayloadSanitizer`). Żadnych wartości pól, e-maili, telefonów, NIP, nazwisk, adresów, danych fakturowych ani tekstu z DOM.
- Ruch wewnętrzny (cookie `pne_skip_analytics` / `pne_skip_funnel`) oraz boty/preview są pomijane.
- Trasa zwolniona z CSRF (`bootstrap/app.php`) — by wspierać `navigator.sendBeacon` (który nie ustawi tokenu). Brak mutacji stanu biznesowego, more-origin web, fail-silent, bez PII.

### Pliki zmienione/dodane (pnedu)

- `app/Enums/Analytics/AnalyticsEventName.php` — 4 nowe eventy + kategorie + `clientJsEvents()` / `isClientJsEvent()`.
- `app/Services/Analytics/AnalyticsModeResolver.php` — `light` dopuszcza `order_form_started` + `order_form_submit_clicked`.
- `app/Services/Analytics/AnalyticsPayloadSanitizer.php` — dodane klucze metadata `cta_key`, `trigger` (`section_key` już był).
- `app/Http/Controllers/Analytics/ClientEventController.php` — **nowy** endpoint batch.
- `app/Providers/AppServiceProvider.php` — rate limiter `analytics-client-events`.
- `config/analytics.php` — sekcja `client_events`.
- `routes/web.php` — trasa `analytics.client-events.store` z throttlem.
- `bootstrap/app.php` — `analytics/client-events` na liście wyjątków CSRF.
- `tests/Feature/AnalyticsClientEventsStageB1Test.php` — **19 testów** (RODO, limity, tryby, fail-silent, skip admina/botów, dedup UUID).

### Testy

`sail artisan test --filter=Analytics` → 94 passed (675 assertions), w tym 19 nowych dla B1.

---

## PR B1a — hardening backendu (2026-06-25)

Dwa zabezpieczenia dodane przed B2. **Bez** zmian zakresu eventów, bez JS, bez porzuceń.

### 1. Same-origin guard

CSRF-exempt dla `POST /analytics/client-events` **zostaje** (wsparcie `sendBeacon`, brak mutacji stanu, fail-silent, bez PII). Dodano lekką kontrolę nagłówków (porównanie po **HOŚCIE**, nie po pełnym URL):

- jest `Origin` i host ≠ host aplikacji → `204`, nie zapisujemy eventów;
- brak `Origin`, jest `Referer` i host ≠ host aplikacji → `204`, nie zapisujemy eventów;
- oba nagłówki puste → best-effort, **nie blokujemy** (przepuszczamy przez pozostałe zabezpieczenia);
- nigdy `403` (żeby nie generować problemów po stronie formularza);
- **nie logujemy** pełnych URL-i ani referrerów; raw `Origin`/`Referer` **nie trafiają** do analityki (zapisujemy tylko `referrer_domain` z istniejącego kontekstu).

Implementacja: `ClientEventController::isCrossOriginRequest()` + `hostMatches()`.

### 2. Namespacowanie `event_uuid` (UUID klienta = tylko seed dedup)

Kolumna `analytics_events.event_uuid` to typ `uuid` (**char(36)**, unique) — `client_js|sha256(...)` by się nie zmieścił. Dlatego klientowski UUID jest **tylko seedem deduplikacji**, a finalny `event_uuid` to deterministyczny **UUIDv5** (jak w `BackendAnalyticsTracker`):

```
event_uuid = Uuid::uuid5(NAMESPACE_URL, "client_js|{order_form_session_id}|{event_name}|{client_event_uuid}")
```

Gwarancje:

- ten sam `client_event_uuid` + ta sama sesja formularza + ten sam `event_name` → **ten sam** `event_uuid` (dedup na unique + insertOrIgnore);
- inna sesja formularza → **brak kolizji**;
- inny `event_name` → **brak kolizji**;
- brak/niepoprawny UUID klienta → serwer generuje własny `event_uuid` (jak dotąd).

`client_event_uuid` **nie jest** zapisywany do metadata (brak potrzeby diagnostycznej, czystość RODO).

Implementacja: `ClientEventController::namespacedEventUuid()` (Ramsey `Uuid::uuid5`).

### Audyt whitelist (bez zmian)

Audyt realnego `resources/views/courses/order-form.blade.php` (pnedu):

- sekcje formularza: buyer (`buyer_data`), odbiorca (`recipient_data`), uczestnik (`participants`), uwagi do faktury (`invoice`), płatność (`payment_method`) — **pokrywają się** z whitelistą `section_key`;
- `cta_key`: submit (`submit_order`), „Powrót do szczegółów szkolenia" (`back_to_course`), wybór płatności online/odroczona (`select_online_payment`/`select_deferred_invoice`) — **pokrywają się**;
- `consents`, `summary`, `add_participant`, `remove_participant` — nie występują w obecnym jednokrokowym formularzu (zostają w whiteliście jako rezerwa na przyszłość, są nieszkodliwe);
- przyciski GUS (buyer/recipient) — **świadomie poza zakresem** (B-future), JS ich nie emituje;
- `invoice` zostaje (NIE dodajemy `invoice_data` — kolidowałoby z zakazanym fragmentem klucza `invoice_data` w sanitizerze).

**Wniosek: whitelisty bez zmian.**

### Batch limit (bez zmian)

> 20 eventów → zapisujemy maks. 20 pierwszych; reszta ucinana. Całego batcha **nie odrzucamy** (best-effort).

### Testy B1a

`sail artisan test --filter=Analytics` → **102 passed** (706 assertions). W tym 27 testów klasy B1/B1a, m.in.: matching/foreign Origin, matching/foreign Referer, brak nagłówków, namespacowanie UUID, dedup w tej samej sesji, brak kolizji między sesjami i między `event_name`, niepoprawny UUID → serwerowy, limit batcha, brak PII.

---

## PR B2 — JS collector na formularzu (2026-06-25, commit `bdc74ca`)

Lekki, fail-silent collector **tylko** na stronie formularza zamówienia (`pnedu`). Podłącza front do istniejącego endpointu B1/B1a. **Bez** porzuceń, bez nowych eventów, bez field-level, bez zmian logiki formularza/płatności/faktur.

**Status:** zacommitowane i wypchnięte (`pnedu` `bdc74ca`). Deploy produkcyjny wg `docs/deploy/2026-06-analytics-production-deploy.md` sekcje 7.2 i 9.1.

### Gdzie i jak się ładuje

- Projekt nie używa `@vite` w layoucie formularza (biblioteki z CDN, własny JS formularza jest **inline**, layout ma `@stack('scripts')`). Dlatego collector to **inline `<script>`** w partialu, dołączany **wyłącznie** na tej stronie — zgodnie ze stylem projektu i zasadą „nie globalnie".
- Pliki:
  - `resources/views/courses/partials/order-form-client-tracking.blade.php` — collector (self-contained IIFE, cały w `try/catch`).
  - `resources/views/courses/order-form.blade.php` — element configu `#order-form-analytics-config` + `@include` partiala, oba w `@if(config('analytics.enabled'))`; dodane `data-analytics-section` / `data-analytics-cta`.
- Config przekazywany do JS tylko jako bezpieczne `data-*`: `data-endpoint`, `data-course-id`, `data-price-variant-id`, `data-max-batch`. **Brak** danych osobowych, pełnego URL, referrera.

### Eventy i triggery

| Event | Trigger (JS) | Klucz |
|-------|--------------|-------|
| `order_form_started` | pierwsza interakcja (input/change/click/submit) | `trigger=first_interaction`, raz na wejście |
| `order_form_section_interacted` | pierwsza interakcja z sekcją | `section_key` z `data-analytics-section`, raz na sekcję |
| `order_form_cta_clicked` | klik/zmiana CTA | `cta_key` z `data-analytics-cta` |
| `order_form_submit_clicked` | zdarzenie `submit` formularza | (bez preventDefault) |

Mapowanie w formularzu (whitelisty B1/B1a):
- sekcje: `buyer_data` (Dane kontaktowe zamawiającego **oraz** „Dane do faktury": nazwa nabywcy/NIP/adres), `invoice` (tylko „Uwagi do faktury"), `recipient_data` (blok odbiorcy), `participants` (Dane uczestników), `payment_method` (sposób rozliczenia/płatność);
- CTA: `select_deferred_invoice`, `select_online_payment` (radia płatności), `submit_order` (przycisk), `back_to_course` (link „Powrót do szczegółów szkolenia").
- `add_participant`/`remove_participant`/`consents`/`summary` nie występują w obecnym jednokrokowym formularzu (rezerwa); przyciski GUS świadomie pominięte (B-future).

> Decyzja 2026-06-25 (deploy B2): `add_participant`/`remove_participant` są **tylko rezerwą w whiteliście backendu**.
> W obecnym B2 NIE dodajemy ich do widoku, ponieważ bieżący formularz nie ma istotnych przycisków
> dodawania/usuwania uczestników w zakresie tego etapu. Pozostają nieszkodliwe (whitelista wartości, nie pól).

### Batch / debounce / flush

- kolejka w pamięci, batch ≤ `data-max-batch` (domyślnie 20);
- debounce wysyłki ~3 s (`setTimeout`), wysyłka `fetch(..., {keepalive:true, credentials:'same-origin'})`;
- flush beaconem (`navigator.sendBeacon`, fallback `fetch keepalive`) przy: `submit`, `visibilitychange` (hidden), `pagehide`;
- każdy event ma klientowski `event_uuid` (crypto.randomUUID + fallback v4; brak crypto → pomijamy, serwer wygeneruje) — backend B1a traktuje go jako **seed** deduplikacji (UUIDv5);
- brak localStorage/sessionStorage, brak agresywnych retry.

### RODO

JS **nigdy** nie czyta wartości pól (`input.value`/`textarea`/`select`, `FormData`, `innerText`, HTML, pełny URL, raw referrer). Payload zawiera wyłącznie: `event_name`, `event_uuid`, `course_id`, `price_variant_id`, `section_key`, `cta_key`, `trigger` — wszystkie z whitelisty. Wartości `section_key/cta_key` pochodzą z autorskich `data-*`, nie z treści DOM.

### Fail-silent

Cały collector w `try/catch`; brak collectora / endpointu / `sendBeacon` / `crypto`, adblock, timeout, błąd `fetch`, tryb `off` → **formularz działa normalnie**. **Brak** `preventDefault()` na submit.

### Tryby analityki

Logiki trybów **nie duplikujemy** w JS (backend B1/B1a egzekwuje): `standard` zbiera 4 MVP; `light` zapisze tylko `order_form_started` + `order_form_submit_clicked`; `aggregate_only`/`off` nic nie zapiszą (endpoint zwraca `204`). Dodatkowo: gdy hard kill switch (`config('analytics.enabled')=false`), collector **nie jest renderowany**.

### Testy B2

`tests/Feature/AnalyticsOrderFormClientTrackingStageB2Test.php` (8 testów): collector obecny na formularzu i nieobecny poza nim; config zawiera endpoint + `course_id` + `max_batch`; `data-analytics-section`/`data-analytics-cta` tylko z whitelisty; config bez PII; formularz nadal się renderuje; collector nieobecny przy hard kill switch.

Wyniki: `--filter=Analytics` → **110 passed** (745 assertions). Sanity formularza (`OrderEntryPlacementTest|FormOrderCheckoutResumeServiceTest|PaymentDisplayOptionOrderFormTestModeTest`) → **15 passed**. `npm run build` → OK.

---

## PR B3 — agregacja porzuceń formularza (2026-06-25)

Porzucenia jako **agregacja po czasie** (NIE nowy event `order_form_abandoned`). Idempotentna
komenda przelicza dzienne statystyki z `analytics_events` grupując po `order_form_session_id` —
analogicznie do `analytics:aggregate-daily` (1C). Zakres B3 wybrany 2026-06-25 (Waldemar): **kurs + kampania**.

**Status:** wdrożone produkcyjnie (2026-06-25). Commit `b0b4535` (`pneadm`). Migracja + catch-up + cron 03:15 Europe/Warsaw.
Wynik catch-up prod (2026-06-25): **9 wierszy kursów**, **6 wierszy kampanii** (jedyny dzień z eventami funnelowymi na starcie).

### Lejek sesji
| Etap | Event | Źródło |
|------|-------|--------|
| viewed | `order_form_viewed` | backend |
| started | `order_form_started` | JS (B2) |
| submit_clicked | `order_form_submit_clicked` | JS (B2) |
| submit_attempted | `order_form_submit_attempted` | backend |
| created (konwersja) | `form_order_created` | backend |

### Kubełki terminalne (rozłączne, sumują się do `sessions_total`)
- `viewed_not_started`, `started_not_submit_clicked`, `submit_clicked_not_attempted`,
  `submit_attempted_not_created`, `converted`.
- Dodatkowo liczniki zasięgu `reached_*` (obecność danego etapu w sesji).

### Decyzje techniczne
- **Atrybucja dnia**: sesja liczona raz, w dniu jej **pierwszego** eventu formularza (Europe/Warsaw) —
  odpowiada pytaniu „co stało się z sesjami rozpoczętymi danego dnia?".
- **Atrybucja kampanii = first-touch**: pierwsza (wg `occurred_at`) **niepusta** kampania w obrębie
  `order_form_session_id`; `campaign_id` z tego samego eventu. Brak kampanii → sesja poza tabelą kampanii.
  (Nie dominanta — kampania ma odpowiadać źródłu wejścia do formularza, nie późniejszej aktywności.)
- **Okno 24 h**: domyślny cel komendy = **2 dni wstecz** (`ANALYTICS_ABANDONMENT_LAG_DAYS=2`) →
  sesje dojrzałe, wynik deterministyczny (zależy tylko od niezmiennych eventów).
- **Idempotencja**: `delete` wierszy `stat_date` + przeliczenie; `--date`/`--from`/`--to` do backfillu.
- **occurred_at czytany jako UTC** (inwariant produkcyjny) — niezależnie od strefy castowania modelu.
- **Bez PII / metadata** — tylko liczniki + snapshoty `course_id`/`campaign_code`/`campaign_id`.
- **Indeksy pod B4**: oprócz `unique(stat_date, course_id)` / `unique(stat_date, campaign_code)` —
  `index(course_id, stat_date)`, `index(campaign_code, stat_date)` oraz `index(campaign_id, stat_date)`
  (B4 będzie linkować/filtrować po `campaign_id` jak dashboard sales-funnel; akceptujemy częste null).

> WAŻNE (interpretacja): `started`/`submit_clicked` to eventy JS (B2). Sesje bez JS/adblock oraz tryby
> `aggregate_only`/`off` nie mają ich → trafiają do `viewed_not_started` lub najgłębszego etapu
> backendowego. Lejek porzuceń miarodajny głównie dla `standard`/`full` z działającym JS.

### Pliki (pneadm, baza `pne_analytics`)
- `database/migrations/2026_06_25_120000_create_analytics_daily_abandonment_stats_tables.php` — dwie tabele.
- `app/Models/Analytics/AnalyticsDailyFormAbandonmentStat.php`, `AnalyticsDailyCampaignAbandonmentStat.php`.
- `app/Services/Analytics/AnalyticsAbandonmentAggregationService.php` — klasyfikacja sesji.
- `app/Console/Commands/AggregateAnalyticsAbandonmentsCommand.php` — `analytics:aggregate-abandonments`.
- `config/analytics.php` — sekcja `abandonment` (timezone, `aggregation_lag_days`).
- `tests/Feature/AnalyticsAbandonmentAggregationTest.php` — 15 testów.

### Testy B3
`--filter=Analytics` → **113 passed** (306 assertions), w tym 15 nowych dla B3
(kubełki, suma = sessions_total, atrybucja do dnia pierwszego eventu, sesja przez północ,
poziom kampanii, **first-touch kampanii (vs dominanta) + pominięcie wiodących eventów bez kampanii**,
idempotencja, domyślny dzień z lagiem, komenda, brak PII).

---

## PR B4 — dashboard porzuceń formularza (2026-06-25)

Pierwszy, prosty dashboard **read-only** czytający WYŁĄCZNIE agregaty B3
(`analytics_daily_form_abandonment_stats`, `analytics_daily_campaign_abandonment_stats`).
**Nie skanuje `analytics_events`.** Tylko `pneadm`. Bez migracji, bez zmian w `pnedu`, bez zmian w B3/cronie.

### Trasa i menu
- `GET /analytics/form-abandonments` → `analytics.form-abandonments.index`.
- Dostęp: ta sama grupa middleware co reszta `Analityka` (`auth`, `verified`, `check.user.status`, `analytics.debug.access` → admin-only).
- Menu: `Analityka → Porzucenia formularza` (pod „Lejek sprzedaży”), za flagą `analytics.form_abandonment_dashboard.enabled`.

### Pliki
- `app/Http/Controllers/Analytics/AnalyticsFormAbandonmentController.php` (read-only `index`, 404 gdy flaga off).
- `app/Services/Analytics/AnalyticsFormAbandonmentDashboardService.php` (filtry, summary, kubełki, tabele kurs/kampania, enrich nazwą kampanii fail-safe).
- `resources/views/analytics/form-abandonments/index.blade.php` (kafelki, paski %, 2 tabele, „Jak czytać dane”).
- `config/analytics.php` → sekcja `form_abandonment_dashboard` (enabled, timezone, default_days=14, max_days=366).
- `routes/web.php`, `resources/views/layouts/navigation.blade.php`.

### Filtry i domyślny zakres
- `date_from`, `date_to`, `course_id` (opc.), `campaign_code` (opc.).
- Domyślnie: ostatnie **14 dni zakończone na dniu dojrzałym** → `date_to = dziś(Europe/Warsaw) − lag(2)`, `date_from = date_to − 13`.
- Limit zakresu: **366 dni** (przycięcie `date_from`).
- Brak danych → pusty stan z komunikatem, nie błąd. Procenty przy `sessions_total=0` → `—` (bez dzielenia przez zero).

### Metryki
- Kafelki: sesje, rozpoczęto, klik submit, próba submitu, zamówienie, porzucono łącznie (`sessions_total − converted`), konwersja (`converted/sessions_total`).
- Kubełki rozłączne (sumują się do `sessions_total`): `viewed_not_started`, `started_not_submit_clicked`, `submit_clicked_not_attempted`, `submit_attempted_not_created`, `converted` — z paskami %.
- Tabela per kurs (grupowanie `course_id`, tytuł = najnowszy niepusty snapshot, sort `sessions_total DESC`, link do kursu jeśli istnieje `courses.show`).
- Tabela per kampania (grupowanie `campaign_code`, link do karty kampanii jeśli jest dopasowanie w `marketing_campaigns`, sort `sessions_total DESC`).

### RODO
Czyta wyłącznie agregaty B3 (liczniki + `course_title_snapshot`/`campaign_code`). Brak emaili, telefonów, NIP, nazw nabywców/szkół, adresów, danych uczestników, wartości pól, raw metadata, raw eventów.

### Testy B4
`--filter=Analytics` → **128 passed**, w tym 15 nowych dla B4 (dostęp admin/nie-admin/gość, pusty stan, summary, sumowanie per kurs/kampania, filtry data/course_id/campaign_code, brak dzielenia przez zero, suma kubełków = `sessions_total`, brak PII/metadata, menu, brak skanowania `analytics_events`, 404 przy fladze off).

### Deploy B4 (bez migracji)
```bash
cd /path/to/adm.pnedu.pl
git pull
php artisan config:cache
php artisan route:cache
php artisan view:cache
# restart PHP-FPM / workerów tylko jeśli obecny proces tego wymaga
```

---

## PR B5 — CSV AI-safe export z dashboardu porzuceń (2026-06-26)

Eksport CSV z dashboardu B4 gotowy do analizy w arkuszu / ChatGPT — **bez danych osobowych**.
Czyta WYŁĄCZNIE agregaty B3/B4 (przez `AnalyticsFormAbandonmentDashboardService`), **nie skanuje `analytics_events`**,
nie eksportuje raw eventów, sesji ani metadata. Tylko `pneadm`, bez migracji.

### Endpointy (admin-only, te same middleware co dashboard)
- `GET /analytics/form-abandonments/export/courses` → `analytics.form-abandonments.export.courses`
- `GET /analytics/form-abandonments/export/campaigns` → `analytics.form-abandonments.export.campaigns`

### Pliki
- `app/Services/Analytics/AnalyticsFormAbandonmentCsvExportService.php` (NOWY) — buduje wiersze i streamuje CSV.
- `app/Http/Controllers/Analytics/AnalyticsFormAbandonmentController.php` — metody `exportCourses`/`exportCampaigns`.
- `app/Services/Analytics/AnalyticsFormAbandonmentDashboardService.php` — wiersze wzbogacone o `reached_viewed` i `abandoned_total` (reużycie filtrów/agregacji).
- `resources/views/analytics/form-abandonments/index.blade.php` — przyciski „Eksport CSV — kursy/kampanie” (zachowują filtry) + info AI-safe.
- `routes/web.php`.

### Filtry i format
- Te same filtry i domyślny zakres co B4 (`date_from`, `date_to`, `course_id`, `campaign_code`; domyślnie 14 dni do `dziś − lag(2)`, max 366 dni) — reużycie `AnalyticsFormAbandonmentDashboardService::build()`.
- Separator `,`, **BOM UTF-8** (standard CSV w `pneadm`, zgodnie z `ActivityLogController`), `Content-Type: text/csv; charset=UTF-8`.
- Nazwy plików: `pne-form-abandonments-courses-YYYY-MM-DD_YYYY-MM-DD.csv`, `pne-form-abandonments-campaigns-YYYY-MM-DD_YYYY-MM-DD.csv`.
- Rates jako ułamek dziesiętny z kropką (np. `0.25`), pusty string przy braku sesji (bez `%`, bez dzielenia przez zero).
- Stream przez `response()->stream()` (`StreamedResponse`).

### Kolumny CSV
- **per kurs**: `date_from, date_to, course_id, course_title_snapshot, sessions_total, reached_viewed, reached_started, reached_submit_clicked, reached_submit_attempted, reached_created, viewed_not_started, started_not_submit_clicked, submit_clicked_not_attempted, submit_attempted_not_created, converted, abandoned_total, conversion_rate, viewed_not_started_rate, started_not_submit_clicked_rate, submit_clicked_not_attempted_rate, submit_attempted_not_created_rate`.
- **per kampania**: jak wyżej, ale `campaign_code, campaign_id, campaign_name` zamiast pól kursu (nazwa z `marketing_campaigns`, fail-safe try/catch jak w B4).

### RODO / AI-safe
CSV NIE zawiera: email, telefon, NIP, nazw nabywców/odbiorców/szkół, adresów, imion i nazwisk, danych uczestników, wartości pól formularza, metadata, raw eventów/URL/referrer, IP, user agent, `analytics_session_id`, `order_form_session_id`, `form_order_id`, `payment_order_id`, `invoice_number`. Tylko: id/tytuł kursu, kod/id/nazwa kampanii, daty agregacji, liczniki, rates.

### Testy B5
`--filter=Analytics` → **141 passed**, w tym 13 nowych dla B5 (pobranie CSV kursy/kampanie, dostęp gość/nie-admin, nagłówki kolumn, filtry data/course_id/campaign_code, poprawność liczników i rates, brak PII/zakazanych identyfikatorów, pusty CSV = sam nagłówek, linki eksportu z aktualnymi filtrami).

### Deploy B5 (bez migracji)
```bash
cd /path/to/adm.pnedu.pl
git pull
php artisan config:cache
php artisan route:cache
php artisan view:cache
# restart PHP-FPM / workerów tylko jeśli obecny proces tego wymaga
```

---

## PR B6 — wykres trendu dziennego + dzienny CSV (2026-06-26)

Dodaje do dashboardu B4 **dzienny trend** (`sessions_total` vs `converted`) oraz **dzienny wariant CSV**
(jeden wiersz na `stat_date`). Tylko `pneadm`, read-only, na agregatach B3, **bez skanowania `analytics_events`**,
bez migracji, bez zmian w `pnedu`/B3/cronie/płatnościach.

### Wykres
- Chart.js (już ładowany globalnie z CDN w `layouts/app.blade.php`, cel `<x-app-layout>`) — **bez nowej biblioteki**.
- Sekcja „Trend dzienny — sesje vs zamówienia”, `<canvas id="abandonmentTrendChart">`, init w `@push('scripts')`.
- Dane do wykresu liczone w PHP (`$trendChart`), przekazane przez `@json` (Blade nie radzi sobie z arrow-fn w `@json`).
- Pusty stan, gdy brak sesji w zakresie.

### Dzienny trend (serwis)
- `AnalyticsFormAbandonmentDashboardService::buildDailyTrend()` — jeden wiersz na każdy dzień zakresu (**wypełnienie brakujących dni zerami**), sort rosnąco.
- Źródło zależne od filtra: `campaign_code` ustawione → tabela kampanii po `stat_date`; w przeciwnym razie tabela kursów po `stat_date` (z filtrem `course_id`). Reużywa już pobranych kolekcji (bez dodatkowego zapytania).
- Zwracane w `build()` jako `trend`.

### Dzienny CSV
- Endpoint: `GET /analytics/form-abandonments/export/daily` → `analytics.form-abandonments.export.daily` (admin-only).
- `AnalyticsFormAbandonmentCsvExportService::streamDaily()` — reużywa `build()['trend']`.
- Kolumny: `stat_date, sessions_total, reached_viewed, reached_started, reached_submit_clicked, reached_submit_attempted, reached_created, viewed_not_started, started_not_submit_clicked, submit_clicked_not_attempted, submit_attempted_not_created, converted, abandoned_total, conversion_rate, viewed_not_started_rate, started_not_submit_clicked_rate, submit_clicked_not_attempted_rate, submit_attempted_not_created_rate`.
- Nazwa pliku: `pne-form-abandonments-daily-YYYY-MM-DD_YYYY-MM-DD.csv`. BOM UTF-8, separator `,`, rates jako ułamki dziesiętne.
- Przycisk „Eksport CSV — dziennie” w UI (zachowuje filtry).

### Pliki
- `app/Services/Analytics/AnalyticsFormAbandonmentDashboardService.php` (+`buildDailyTrend`, `trend` w `build()`).
- `app/Services/Analytics/AnalyticsFormAbandonmentCsvExportService.php` (+`streamDaily`, `DAILY_COLUMNS`).
- `app/Http/Controllers/Analytics/AnalyticsFormAbandonmentController.php` (+`exportDaily`).
- `resources/views/analytics/form-abandonments/index.blade.php` (wykres + przycisk + `@push('scripts')`).
- `routes/web.php`.

### RODO / AI-safe
Dzienny CSV i wykres zawierają wyłącznie daty agregacji, liczniki i rates. Brak PII, identyfikatorów sesji/zamówień/faktur, metadata, raw eventów. Dzienny CSV nie zawiera nawet `course_id`/`campaign_code` (czyste totale dzienne).

### Testy B6
`--filter=Analytics` → **151 passed**, w tym 10 nowych dla B6 (trend wypełnia zakres zerami, trend z tabeli kampanii przy filtrze kampanii, render `<canvas>`, pobranie dziennego CSV + nagłówek, dostęp nie-admin, jeden wiersz na dzień, poprawność rates, brak PII, link eksportu z filtrami).

### Przycisk „Przelicz porzucenia” (ręczna agregacja B3 z panelu)
Analogiczny do „Przelicz teraz” z lejka sprzedaży. Pozwala adminowi przeliczyć agregaty porzuceń (B3)
dla widocznego zakresu dat bez czekania na cron 03:15.
- Endpoint: `POST /analytics/form-abandonments/recompute` → `analytics.form-abandonments.recompute` (admin-only).
- Uruchamia `AnalyticsAbandonmentAggregationService::aggregateForDateRange()` (to samo co komenda `analytics:aggregate-abandonments`).
- Idempotentne (serwis kasuje i liczy per dzień), modal potwierdzenia Bootstrap, komunikaty flash, zachowuje filtry.
- Limit zakresu: `analytics.form_abandonment_dashboard.recompute_max_days` (domyślnie **92 dni**; recompute skanuje `analytics_events`, więc limit ostrożniejszy niż 366 dni dashboardu). Większe zakresy → komenda w konsoli.
- Audyt: `ActivityLog` `analytics_abandonments_recomputed` (fail-safe).
- **To jedyne miejsce w dashboardzie, które pośrednio uruchamia agregację B3 — sam odczyt dashboardu nadal nie skanuje `analytics_events`.**

### Deploy B6 (bez migracji)
```bash
cd /path/to/adm.pnedu.pl
git pull
php artisan config:cache
php artisan route:cache
php artisan view:cache
# restart PHP-FPM / workerów tylko jeśli proces tego wymaga
```

---

## Status etapów — skrót

- **B2**: ✅ wdrożone produkcyjnie (`pnedu` `bdc74ca`).
- **B3**: ✅ wdrożone produkcyjnie (`pneadm` `b0b4535`, 2026-06-25). Migracja, catch-up 2026-06-25 (9 kursów / 6 kampanii), cron 03:15.
- **B4**: ✅ wdrożone produkcyjnie (`pneadm` `a6ee852`, 2026-06-26). Dashboard porzuceń, read-only, agregaty B3.
- **B5**: ✅ wdrożone produkcyjnie (`pneadm` `cb8046a`, 2026-06-26). CSV AI-safe export (per kurs / per kampania).
- **B6**: ✅ wdrożone produkcyjnie (`pneadm` `5a2e2b5`, 2026-06-26). Wykres trendu dzienny + dzienny CSV.
- **Recompute + presety + healthcheck + porównanie okresów**: ✅ wdrożone produkcyjnie (`69d6e83`–`5526e96`, 2026-06-26). Prod HEAD: `5526e96`.

## Smoke test produkcyjny (po B2)

1. Tryb analityki `standard` (lub `full`).
2. Formularz w incognito, bez cookie admina `pne_skip_analytics`.
3. Wejść z UTM, kliknąć pierwsze pole, przejść 2–3 sekcje.
4. Sprawdzić `/analytics/debug-events` — oczekiwane: `order_form_viewed`, `order_form_started`, `order_form_section_interacted`, `order_form_cta_clicked`, ew. `order_form_submit_clicked`.
5. Potwierdzić brak PII w `metadata`. Sprawdzić logi i failed jobs.
