# Plan Implementacji Trackingu

Data utworzenia/aktualizacji: 2026-06-24  
Status: Etap 0, 1A, 1A-Debug, 1B-1, 1B-2, 1C i 1D wdrożone lokalnie

## Cel Dokumentu

Dokument opisuje, gdzie i jak w przyszłości wdrożyć backend tracking, JS tracking, kolejkę Redis `analytics` oraz fail-silent zapis do `pne_analytics`.

Kod Etapu 0, 1A, 1A-Debug, 1B-1, 1B-2, 1C, 1D, 2A-1, 2A-2, 2B-1 i 2C-1 został wdrożony lokalnie. Etap 1C obejmuje ręczną komendę agregacji dziennej; Etap 1D — pierwszy dashboard lejka sprzedaży w `adm.pnedu.pl`; Etap 2C-1 — event `invoice_created` (observer w `pneadm`). Nadal nie wdrożono JS trackingu, iFirma/KSeF trackingu, agregatów/dashboardu faktur, AI ani eksportów AI-safe.

## Zasada Nadrzędna

Analityka nie może blokować:

- wyświetlenia strony,
- formularza zamówienia,
- zapisu zamówienia,
- płatności online,
- faktury,
- certyfikatów.

Każdy błąd analityki powinien być obsłużony cicho lub zapisany jako techniczny warning bez danych osobowych.

## Docelowe Komponenty

Planowane klasy i elementy:

- `AnalyticsService`,
- `AnalyticsModeResolver`,
- `AnalyticsPayloadSanitizer`,
- `StoreAnalyticsEventJob`,
- `AggregateAnalyticsDailyStatsJob`,
- endpoint JS, np. `POST /analytics/events`,
- Redis queue `analytics`,
- connection database `pne_analytics`.

Nazwy są robocze i mogą zostać zmienione podczas implementacji.

## Backend Tracking

### `pnedu.pl`

Miejsca do późniejszego trackingu:

| Miejsce | Planowany event | Uwagi |
|---|---|---|
| `MarketingCampaignShortLinkController` | `campaign_short_link_visit` | wejście przez `/l/{campaign_code}` |
| `MarketingCampaignLinkResolver` | `campaign_redirect_resolved` | zapis `landing_target` |
| `CaptureMarketingSource` | `utm_captured` | tylko bezpieczne UTM |
| `CourseController@show` | `course_description_viewed` | backendowy odpowiednik obecnego agregatu |
| `CourseController@orderForm` | `order_form_viewed` | utworzenie/powiązanie `order_form_session_id` |
| `CourseController@storeOrderForm` | `order_form_submit_attempted` | przed walidacją lub na początku metody |
| walidacja formularza | `order_form_validation_failed` | bez wartości pól |
| po zapisie `FormOrder` | `form_order_created` | wdrożone w 1B-2, powiązane z `form_order_id` |
| płatność online | `online_payment_selected`, `payment_order_created` | bez danych płatnika |
| faktura odroczona | `deferred_invoice_selected` | typ ścieżki |
| `PaymentController` webhook/return | `payment_status_changed` | status, gateway, ID |

### `adm.pnedu.pl`

Miejsca do późniejszego trackingu:

| Miejsce | Planowany event | Uwagi |
|---|---|---|
| `MarketingCampaignController@store` | `campaign_created` | opcjonalnie do audytu analitycznego |
| `MarketingCampaignController@update` | `campaign_updated` | zmiana `landing_target`, UTM |
| `FormOrdersController` akcje iFirma | `invoice_created` | bez danych faktury |
| `FormOrdersController` provision PNEDU | `pnedu_access_provisioned` | raczej operacyjne, nie MVP |
| dashboard analityczny | brak raw eventów | tylko odczyt agregatów |

## JS Tracking Formularza

Główne miejsce:

- `pnedu.pl/resources/views/courses/order-form.blade.php`.

W formularzu istnieje już JavaScript do:

- przełączania typu klienta,
- przełączania płatności,
- obsługi sekcji odbiorcy,
- kopiowania danych,
- lookupu uczestnika po e-mailu,
- przycisku danych testowych.

W przyszłości tracker powinien być wydzielony do osobnego pliku JS albo lekkiego modułu, żeby nie zwiększać chaosu w widoku Blade.

## Eventy JS MVP

- `order_form_js_loaded`,
- `order_form_started`,
- `buyer_type_selected`,
- `payment_type_selected`,
- `payment_gateway_selected`,
- `section_interacted`,
- `cta_clicked`,
- `time_checkpoint`,
- `before_unload_form_dirty`.

## Eventy JS Przyszłe

- `gus_lookup_started`,
- `gus_lookup_success`,
- `gus_lookup_failed`,
- `gus_autofill_applied`,
- `invoice_role_option_selected`,
- `ksef_option_selected`,
- `recipient_section_opened`,
- `recipient_data_completed`,
- `participant_added`,
- `participant_removed`,
- `participant_count_changed`,
- `price_recalculated`,
- `saved_billing_profile_used`,
- `saved_billing_profile_created`,
- `saved_billing_profile_updated`.

## Zasady Dla JS Payloadów

Wysyłać:

- `order_form_session_id`,
- `analytics_session_id`,
- `course_id`,
- `campaign_code`,
- `event_name`,
- `section_key`,
- `field_key`,
- `buyer_type`,
- `payment_type`,
- `payment_gateway`,
- `participant_count`,
- `has_recipient`,
- `time_spent_seconds`.

Nie wysyłać:

- wartości e-maila,
- wartości NIP,
- telefonu,
- adresu,
- imienia,
- nazwiska,
- nazwy szkoły,
- treści uwag do faktury.

## Endpoint JS

Planowany endpoint:

```text
POST /analytics/events
```

Założenia:

- przyjmuje batch eventów,
- waliduje tylko strukturę,
- filtruje metadane,
- wrzuca job do kolejki,
- zwraca szybko `204` lub `202`,
- nie zapisuje synchronicznie do `pne_analytics`.

Do potwierdzenia:

- czy endpoint ma istnieć tylko w `pnedu.pl`,
- czy `adm.pnedu.pl` również będzie wysyłać eventy JS.

## Redis Queue `analytics`

Rekomendacja:

- osobna kolejka `analytics`,
- osobny worker,
- krótki timeout,
- niska liczba retry,
- fail-silent względem użytkownika.

Przykładowy docelowy worker:

```text
sail artisan queue:work redis --queue=analytics --timeout=30 --tries=2
```

Na hostingu bez Supervisora może być konieczne podejście zgodne z istniejącym `docs/QUEUE_SEOHOST.md`, czyli uruchamianie przez scheduler.

## `AnalyticsService`

Planowana odpowiedzialność:

- przyjęcie eventu z backendu,
- pobranie trybu analityki,
- sampling,
- sanitizacja payloadu,
- nadanie `event_uuid`,
- dispatch joba,
- obsługa wyjątków bez przerwania procesu.

## `StoreAnalyticsEventJob`

Planowana odpowiedzialność:

- zapis eventu do `pne_analytics`,
- zapis do tabel specjalistycznych, jeśli dotyczy,
- deduplikacja po `event_uuid`,
- ewentualne aktualizacje `order_form_sessions`,
- brak dostępu do danych osobowych.

## Fail-Silent

Każdy tracker powinien działać w trybie:

```text
try tracking
catch error
    log technical warning without PII
    continue business flow
```

Nie wolno:

- rzucać wyjątku do formularza,
- blokować płatności,
- wyświetlać użytkownikowi błędów analityki,
- robić synchronicznych ciężkich insertów w krytycznej ścieżce.

## Fallback

Jeżeli Redis nie działa:

- nie blokować requestu,
- opcjonalnie zapisać licznik awarii w logu technicznym,
- pominąć event.

Jeżeli `pne_analytics` nie działa:

- job retry,
- po wyczerpaniu prób failed job,
- brak wpływu na użytkownika.

Jeżeli JS tracker nie działa:

- backend tracking nadal mierzy zdarzenia krytyczne.

## Kolejność Wdrożenia

1. Dodać connection `pne_analytics`.
2. Utworzyć tabele MVP.
3. Utworzyć `AnalyticsService`.
4. Utworzyć `StoreAnalyticsEventJob`.
5. Podłączyć backend eventy minimalne.
6. Uruchomić kolejkę `analytics`.
7. Dodać JS endpoint.
8. Dodać JS tracker formularza.
9. Dodać agregaty dzienne.
10. Dodać dashboard.

## Plan Implementacji Etapu 0/1

Status: Etap 0, 1A, 1A-Debug, 1B-1 i 1B-2 wdrożone lokalnie.  
Data dopisania: 2026-06-24.

### Etap 0 — Fundament Techniczny

Etap 0 ma przygotować konfigurację i strukturę pod analitykę, ale jeszcze bez podłączania trackingu do produkcyjnych procesów.

Zakres:

- dodać connection `analytics` w `config/database.php` w obu projektach,
- dodać zmienne do `.env.example` w obu projektach,
- przygotować lokalną bazę `pne_analytics` w tym samym kontenerze MySQL co `pneadm`,
- opisać produkcyjne zmienne `.env` dla utworzonej bazy analitycznej,
- przygotować konfigurację Redis queue `analytics`,
- przygotować katalogi `app/Services/Analytics`, `app/Jobs/Analytics`, `app/Models/Analytics`, `app/Enums/Analytics`,
- przygotować migracje MVP w projekcie `pneadm`, ponieważ baza analityczna jest wspólną bazą techniczną/backoffice,
- przygotować testy sanitizacji i fail-silent.

Nie robić w Etapie 0:

- nie podpinać eventów do formularza,
- nie modyfikować płatności,
- nie modyfikować faktur,
- nie modyfikować KSeF,
- nie uruchamiać JS trackingu.

### Etap 1 — Minimalny Backend Tracking

Etap 1 ma wdrożyć backend tracking krytycznych zdarzeń, najpierw w `pnedu.pl`, potem w wybranych miejscach `adm.pnedu.pl`.

Rekomendowana kolejność:

1. `campaign_short_link_visit` i `campaign_redirect_resolved`.
2. `utm_captured`.
3. `course_description_viewed`.
4. `order_form_viewed`.
5. `order_form_submit_attempted`.
6. `order_form_validation_failed`.
7. `form_order_created` — wdrożone w 1B-2.
8. `online_payment_selected` i `deferred_invoice_selected` — wdrożone w 2A-1.
9. `payment_order_created` — wdrożone w 2A-2.
10. `payment_status_changed` — wdrożone w 2B-1.
11. `invoice_created`.

Dotychczas wdrożono backendowe eventy Etapu 1A, 1B (`order_form_submit_attempted`, `order_form_validation_failed`, `form_order_created`), 2A-1 (`online_payment_selected`, `deferred_invoice_selected`), 2A-2 (`payment_order_created`), 2B-1 (`payment_status_changed`) oraz 2C-1 (`invoice_created`, observer w `pneadm`). Nie wdrożono iFirma/KSeF trackingu, agregatów/dashboardu faktur, JS trackingu, porzuceń, A/B testów, dashboardu/agregatów płatności, AI ani eksportów AI-safe.

### Konfiguracja Produkcyjna

Na produkcji baza analityczna została utworzona przez właściciela. W dokumentacji i repozytorium nie zapisywać hasła.

Docelowe zmienne `.env` produkcji w obu aplikacjach:

```dotenv
DB_ANALYTICS_CONNECTION=mysql
DB_ANALYTICS_HOST=localhost
DB_ANALYTICS_PORT=3306
DB_ANALYTICS_DATABASE=srv66127_pne_analytics
DB_ANALYTICS_USERNAME=srv66127_pne_analytics
DB_ANALYTICS_PASSWORD=secret-only-in-production-env
ANALYTICS_ENABLED=true
ANALYTICS_DEFAULT_MODE=standard
ANALYTICS_QUEUE_CONNECTION=redis
ANALYTICS_QUEUE=analytics
ANALYTICS_SAMPLE_RATE=100
```

Po wpisaniu rzeczywistego hasła na produkcji należy wykonać czyszczenie konfiguracji właściwą komendą dla środowiska produkcyjnego. W Laravel Sail lokalnie byłoby to `sail artisan config:clear`, ale na produkcji użyć komendy zgodnej z hostingiem.

### Konfiguracja Lokalna Laravel Sail

Lokalnie `pnedu` korzysta ze wspólnego MySQL z projektu `pneadm`, czyli kontenera `pneadm-mysql`.

Rekomendacja:

- nie dodawać drugiego kontenera MySQL do `pnedu`,
- utworzyć bazę `pne_analytics` w istniejącym MySQL `pneadm-mysql`,
- oba projekty mają używać connection `analytics` do tej samej bazy.

Planowane zmienne lokalne w obu projektach:

```dotenv
DB_ANALYTICS_CONNECTION=mysql
DB_ANALYTICS_HOST=mysql
DB_ANALYTICS_PORT=3306
DB_ANALYTICS_DATABASE=pne_analytics
DB_ANALYTICS_USERNAME=sail
DB_ANALYTICS_PASSWORD=password
ANALYTICS_ENABLED=true
ANALYTICS_DEFAULT_MODE=standard
ANALYTICS_QUEUE_CONNECTION=redis
ANALYTICS_QUEUE=analytics
ANALYTICS_SAMPLE_RATE=100
```

W `pnedu` host może wymagać wartości `pneadm-mysql` zamiast `mysql`, jeżeli request idzie z kontenera `pnedu-app` przez wspólną sieć `pne-network`. Na WSL2/Docker oba hosty (`mysql` i `pneadm-mysql`) zwykle wskazują na ten sam kontener `pneadm-mysql` w sieci `pne-network`.

### Lokalna konfiguracja `pne_analytics` na nowym komputerze developerskim

Checklist po sklonowaniu repozytoriów (bez wdrażania nowych funkcji biznesowych):

1. Uruchomić Sail w `pneadm` (tworzy `pneadm-mysql`, sieć `pne-network`, port aplikacji `8083`).
2. Uruchomić Sail w `pnedu` (bez własnego MySQL; korzysta ze wspólnego `pneadm-mysql`).
3. Skopiować `.env` z `.env.example` lub uzupełnić brakujące zmienne `DB_ANALYTICS_*` i `ANALYTICS_*` w obu projektach (patrz sekcja powyżej).
4. Utworzyć bazę, jeśli nie istnieje:

```bash
docker exec pneadm-mysql mysql -usail -ppassword \
  -e "CREATE DATABASE IF NOT EXISTS pne_analytics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

5. Uruchomić migracje analityczne **tylko z `pneadm`**:

```bash
cd pneadm
./vendor/bin/sail artisan migrate --force
```

Migracje `2026_06_24_*` używają wewnętrznie `Schema::connection('analytics')`, więc tabele powstają w `pne_analytics`, a wpisy trafiają do `pneadm.migrations`. **Nie** uruchamiać `migrate --database=analytics` bez `--path` — uruchomiłoby wszystkie migracje adm na bazie analitycznej.

6. Zweryfikować połączenie w obu projektach:

```bash
./vendor/bin/sail artisan tinker --execute="DB::connection('analytics')->getPdo(); echo 'OK';"
./vendor/bin/sail artisan test --filter=Analytics
```

7. Panel debug: `http://localhost:8083/analytics/debug-events` (wymaga logowania jako admin; `ANALYTICS_DEBUG_PANEL_ENABLED=true`).

### Redis Queue `analytics`

Plan:

- nie zmieniać globalnego `QUEUE_CONNECTION` na potrzeby analityki,
- job analityczny dispatchować jawnie na connection/queue z configu,
- użyć Redis connection `default`,
- użyć queue name `analytics`.

Planowane `.env.example`:

```dotenv
ANALYTICS_QUEUE_CONNECTION=redis
ANALYTICS_QUEUE=analytics
ANALYTICS_QUEUE_TRIES=2
ANALYTICS_QUEUE_TIMEOUT=30
```

Docelowy worker lokalny:

```text
sail artisan queue:work redis --queue=analytics --timeout=30 --tries=2
```

Na hostingu bez stałego workera konieczne może być użycie strategii zgodnej z `docs/QUEUE_SEOHOST.md`.

### Planowane Klasy

| Klasa | Projekt | Odpowiedzialność | Nie powinna robić |
|---|---|---|---|
| `App\Services\Analytics\AnalyticsService` | oba | publiczne `track()`, fail-silent, dispatch joba | zapisywać synchronicznie ciężkich danych |
| `App\Services\Analytics\AnalyticsContextService` | oba | budowa kontekstu z requestu, kampanii, kursu | czytać danych osobowych z formularza |
| `App\Services\Analytics\AnalyticsModeResolver` | oba | tryb, sampling, opt-out, bot guard | znać szczegółów tabel |
| `App\Services\Analytics\AnalyticsPayloadSanitizer` | oba | whitelist metadanych i blokada PII | logować pełnego payloadu |
| `App\Jobs\Analytics\StoreAnalyticsEventJob` | oba | zapis eventu do `pne_analytics` | przerywać proces sprzedaży |
| `App\Jobs\Analytics\AggregateAnalyticsDailyStatsJob` | `pneadm` | agregaty dzienne | działać w requestach użytkownika |
| `App\Models\Analytics\AnalyticsEvent` | oba | model tabeli `analytics_events` | mieć FK do `pneadm` |
| `App\Models\Analytics\AnalyticsSession` | oba | model sesji analitycznej | przechowywać e-mail/IP |
| `App\Models\Analytics\OrderFormSession` | oba | sesja formularza | przechowywać wartości pól |
| `App\Models\Analytics\ConversionEvent` | oba | konwersje | przechowywać dane płatnika |
| `App\Models\Analytics\ValidationErrorEvent` | oba | błędy walidacji po `field_key` | przechowywać wartości pól |

Enumy/stałe:

- `AnalyticsEventName`,
- `AnalyticsMode`,
- `AnalyticsCategory`,
- `LandingTarget`,
- `CampaignContentDepth`,
- `CtaType`.

Ponieważ klasy będą potrzebne w obu projektach, na Etapie 0/1 rekomendacja jest taka sama struktura namespace i kontraktu w obu repozytoriach. Wspólny package można rozważyć później, ale nie wdrażać go teraz.

### Miejsca W Kodzie Do Późniejszego Podłączenia

`pnedu.pl`:

| Plik / symbol | Eventy |
|---|---|
| `routes/web.php` route `/l/{campaign_code}` | `campaign_short_link_visit` |
| `MarketingCampaignShortLinkController::__invoke()` | `campaign_short_link_visit`, `campaign_redirect_resolved` |
| `MarketingCampaignLinkResolver::resolveRedirectPath()` | kontekst `landing_target`, `utm_*` |
| `MarketingCampaignLinkTracker::trackCampaignCode()` | obecny agregat, nie duplikować |
| `CaptureMarketingSource::handle()` | `utm_captured` |
| `TrackCoursePageView::handle()` | `course_description_viewed`, `order_form_viewed` |
| `CoursePageViewTracker::trackCourseShow()` | istniejący agregat opisu |
| `CoursePageViewTracker::trackOrderForm()` | istniejący agregat formularza |
| `CourseController::show()` | kontekst kursu |
| `CourseController::orderForm()` | `order_form_viewed` |
| `CourseController::storeOrderForm()` | submit, validation failed, order created |
| `CourseController::storeDeferredOrder()` | deferred invoice path |
| `CourseController::storePayOnline()` | legacy online payment |
| `CourseController::processOrderFormOnlinePayment()` | online payment selected, payment order created |
| `PaymentController::payuNotify()` | `payment_status_changed` |
| `PaymentController::paynowNotify()` | `payment_status_changed` |
| `PaymentController::syncLinkedFormOrderPaymentStatus()` | powiązanie `form_order_id` |

`adm.pnedu.pl`:

| Plik / symbol | Eventy |
|---|---|
| `MarketingCampaignController::store()` | `campaign_created` poza MVP sprzedażowym |
| `MarketingCampaignController::update()` | `campaign_updated` poza MVP sprzedażowym |
| `FormOrdersController::store()` | `form_order_created` dla zamówienia ręcznego |
| `App\Observers\FormOrderObserver` (`created`/`updated`) — detekcja przejścia `invoice_number` empty→present | `invoice_created` (WDROŻONE w 2C-1) |
| `FormOrdersController` — metody iFirma (faktura krajowa, WithReceiver, WithKsef) ustawiają `invoice_number = PelnyNumer` | wskazówka `InvoiceAnalyticsTracker::hintSource('ifirma')` przed zapisem → `invoice_path_type=ifirma` |
| `FormOrdersController::update()` — ręczne wpisanie/edycja `invoice_number` | wskazówka `InvoiceAnalyticsTracker::hintSource('manual')` → `invoice_path_type=manual` |

> Status (Etap 2C-1, wdrożone w `pneadm`): event `invoice_created` powstaje przy **pierwszym** ustawieniu poprawnego `invoice_number` (≠ `''`, ≠ `'0'`) przez observer `FormOrderObserver` (model-level), niezależnie od ścieżki (iFirma lub ręcznie). Pro-forma NIE liczy się (trafia do `notes`). Idempotencja: `event_uuid = invoice_created|{form_order_id}` + `insertOrIgnore`. Payload bezpieczny: top-level `form_order_id`, `course_id`, `amount_snapshot`; metadata `order_flow` (z `FormOrder.payment_mode`), `invoice_path_type`, `payment_type`, `amount_gross` (z `product_price`). ZAKAZANE: numer faktury, NIP, nazwy/adresy, dane uczestników, dane/raw iFirma, KSeF, raw request, `toArray()`. Fail-silent. Ograniczenie: observer łapie tylko zapisy przez Eloquent (bezpośrednie `UPDATE` SQL / importy poza zakresem; ewentualna rekonsyliacja = przyszły etap). Wykrywanie `deferred_invoiced` w przyszłych agregatach: po jednoznacznym polu `FormOrder` (`payment_mode` / `order_flow`), NIE po braku `OnlinePaymentOrder`; `invoice_created` z `order_flow=online` to tylko znacznik księgowy (nie zwiększa `settled_orders_total`). Poza zakresem (NIE teraz): rekonsyliacja przelewów, częściowe płatności, zaliczki, korekty/anulowanie faktur, zwroty, chargebacki, wiele faktur, KSeF — patrz ADR-005. Testy: `tests/Feature/AnalyticsInvoiceCreatedStage2C1Test.php`. Szczegóły: `docs/decisions/ADR-005-invoice-number-means-invoiced-not-paid.md`.

Nie używać `activity_logs` jako źródła analityki, ponieważ mogą zawierać dane osobowe.

### Ryzyka Techniczne

- `pnedu/public/l.php` może omijać Laravel route i nie wykonać trackingu.
- `CourseController` ma rozproszoną walidację i wiele `back()->withErrors()`.
- `PaymentController` loguje webhooki z pełnymi payloadami; analytics musi używać wyłącznie bezpiecznego snapshotu.
- Obecne dashboardy czytają `pneadm` agregaty, więc po Etapie 1 liczby z `pne_analytics` i `pneadm` mogą się różnić do czasu Etapu 3.

## Status Wdrożenia Etapu 0

Data aktualizacji: 2026-06-24.  
Status: wdrożone lokalnie, bez podłączania trackingu do procesów biznesowych.

Wdrożono w `adm.pnedu.pl`:

- `config/analytics.php`,
- connection `analytics` w `config/database.php`,
- zmienne bez sekretów w `.env.example`,
- migrację MVP `database/migrations/2026_06_24_120000_create_pne_analytics_mvp_tables.php`,
- enumy w `app/Enums/Analytics`,
- modele w `app/Models/Analytics`,
- `AnalyticsPayloadSanitizer`,
- `AnalyticsModeResolver`,
- `AnalyticsContextService`,
- `AnalyticsService`,
- `StoreAnalyticsEventJob`,
- testy jednostkowe analityki.

Wdrożono w `pnedu.pl`:

- `config/analytics.php`,
- connection `analytics` w `config/database.php`,
- zmienne bez sekretów w `.env.example`,
- enumy w `app/Enums/Analytics`,
- modele w `app/Models/Analytics`,
- `AnalyticsPayloadSanitizer`,
- `AnalyticsModeResolver`,
- `AnalyticsContextService`,
- `AnalyticsService`,
- `StoreAnalyticsEventJob`,
- testy jednostkowe analityki.

Nie wdrożono w Etapie 0:

- podłączenia eventów do formularza,
- podłączenia eventów do zamówień,
- podłączenia eventów do płatności,
- podłączenia eventów do faktur,
- JS trackingu,
- testów A/B,
- dashboardu,
- AI.

Lokalnie wykonano:

- utworzenie bazy `pne_analytics` w MySQL `pneadm-mysql`,
- nadanie uprawnień użytkownikowi `sail`,
- migrację MVP na connection `analytics`,
- weryfikację, że `pneadm` i `pnedu` widzą tabelę `analytics_events`.

Testy:

- `pneadm`: `sail artisan test --filter=Analytics` — 10 testów, 28 asercji, wynik pozytywny.
- `pnedu`: `sail artisan test --filter=Analytics` — 18 testów, 55 asercji, wynik pozytywny.

Uwaga: w `pneadm` podczas testów występuje istniejące ostrzeżenie o braku uprawnień do zapisu `.phpunit.result.cache`; testy przechodzą.

## Plan Etapu 1 — Do Implementacji

Data dopisania: 2026-06-24.  
Status: Etap 1A wdrożony lokalnie; Etap 1A-Debug wdrożony lokalnie w `adm.pnedu.pl`; Etap 1B-1 i 1B-2 wdrożone lokalnie; Etap 1C wdrożony lokalnie w `adm.pnedu.pl`.

### Etap 1A-Debug — Techniczny Panel Podglądu Eventów

Status: wdrożone lokalnie w `adm.pnedu.pl`.

Cel:

- sprawdzić, czy eventy Etapu 1A zapisują się w `pne_analytics.analytics_events`,
- udostępnić prosty, read-only panel diagnostyczny dla administratora,
- nie tworzyć jeszcze dashboardu biznesowego.

Route:

- `GET /analytics/debug-events`,
- nazwa route: `analytics.debug-events.index`,
- menu: `Marketing i reklama -> Debug eventów`.

Dostęp:

- `auth`,
- `verified`,
- `check.user.status`,
- `analytics.debug.access`,
- rola `admin` albo `super_admin`,
- flaga `ANALYTICS_DEBUG_PANEL_ENABLED=true`.

Panel pokazuje:

- ostatnie eventy z `analytics_events`, domyślnie 100 rekordów na stronę,
- filtry: `event_name`, `campaign_code`, `course_id`, `analytics_session_id`, `order_form_session_id`, `date_from`, `date_to`,
- kolumny techniczne eventu, w tym `occurred_at`, `event_name`, `event_category`, sesje, kurs, kampanię, UTM, route/path/referrer domain, `device_type`, `created_at`,
- `metadata_json` w czytelnej formie, z redakcją wartości dla niedozwolonych kluczy.

Kontrola bezpieczeństwa:

- panel ostrzega przy wykryciu kluczy typu `email`, `phone`, `telefon`, `nip`, `name`, `surname`, `address`, `raw_url`, `raw_referrer`, `raw_request`, `raw_input`,
- wartości takich kluczy w podglądzie są ukrywane,
- panel nie służy do edycji ani kasowania eventów.

Zmiana pomocnicza:

- dodano migrację `2026_06_24_130000_add_device_fields_to_analytics_events_table.php`, która dodaje `device_type` i `browser_family` do `analytics_events`,
- uzupełniono `StoreAnalyticsEventJob` w `pnedu.pl` i `adm.pnedu.pl`, aby zapisywał te pola, jeśli są w payloadzie.

Etap 1 obejmuje minimalny backend tracking bez JS. Tracking ma działać równolegle do istniejących agregatów i nie może zmieniać obecnego liczenia `marketing_campaign_stats_daily` ani `course_page_stats_daily`.

### Zakres Etapu 1A — Kampanie I Wejścia

Pierwszy, najbezpieczniejszy commit Etapu 1 powinien objąć:

- `campaign_short_link_visit`,
- `campaign_redirect_resolved`,
- `utm_captured`,
- `course_description_viewed`,
- `order_form_viewed`.

Status: wdrożone lokalnie w `pnedu.pl`.

Pliki prawdopodobnie do zmiany:

- `pnedu/app/Http/Controllers/MarketingCampaignShortLinkController.php`,
- `pnedu/app/Services/MarketingCampaignLinkResolver.php`,
- `pnedu/app/Http/Middleware/CaptureMarketingSource.php`,
- `pnedu/app/Http/Middleware/TrackCoursePageView.php`,
- opcjonalnie nowy `pnedu/app/Services/Analytics/AnalyticsSessionService.php`,
- opcjonalnie nowy `pnedu/app/Services/Analytics/OrderFormSessionService.php`,
- testy w `pnedu/tests/Feature` i `pnedu/tests/Unit`.

Pliki faktycznie zmienione w `pnedu.pl`:

- `config/analytics.php`,
- `.env.example`,
- `app/Services/Analytics/AnalyticsSessionService.php`,
- `app/Services/Analytics/OrderFormSessionService.php`,
- `app/Services/Analytics/BackendAnalyticsTracker.php`,
- `app/Services/Analytics/AnalyticsContextService.php`,
- `app/Services/Analytics/AnalyticsPayloadSanitizer.php`,
- `app/Services/MarketingCampaignLinkResolver.php`,
- `app/Http/Controllers/MarketingCampaignShortLinkController.php`,
- `app/Http/Middleware/CaptureMarketingSource.php`,
- `app/Http/Middleware/TrackCoursePageView.php`,
- `tests/Feature/AnalyticsBackendTrackingStage1ATest.php`.

Nie zmieniać w 1A:

- formularza Blade,
- submitu formularza,
- płatności,
- faktur,
- KSeF,
- iFirma,
- certyfikatów.

### Zakres Etapu 1B — Formularz I Zamówienie

Drugi krok po stabilnym 1A został rozdzielony na 1B-1 i 1B-2.

Etap 1B-1:

- `order_form_submit_attempted`,
- `order_form_validation_failed`.

Status: wdrożone lokalnie w `pnedu.pl`.

Sposób wdrożenia:

- `order_form_submit_attempted` jest wywoływany na początku `CourseController::storeOrderForm()`, po pobraniu kursu i ustaleniu `buyer_type`, ale przed `$request->validate()`,
- `order_form_validation_failed` jest wywoływany w `catch (ValidationException $e)` dla głównego `$request->validate()` i ten sam wyjątek jest rzucany dalej przez `throw $e`,
- ręczne ścieżki `back()->withErrors()->withInput()` dla `recipient_nip`, `payment_terms` i `payment_gateway` emitują `order_form_validation_failed` tuż przed dotychczasowym returnem,
- redirecty, error bag, komunikaty walidacji i `withInput()` pozostają bez zmian,
- payload zawiera tylko neutralne metadane: `validation_context`, `error_count`, `field_keys`, `section_keys`, `error_codes`, `has_price_variant`, `price_variant_id`, `order_form_session_created_on_submit`.

Etap 1B-2:

- `form_order_created`.

Status: wdrożone lokalnie w `pnedu.pl`.

Pliki faktycznie zmienione:

- `pnedu/app/Http/Controllers/CourseController.php`,
- `pnedu/app/Services/Analytics/BackendAnalyticsTracker.php`,
- `pnedu/app/Services/Analytics/AnalyticsPayloadSanitizer.php`,
- `pnedu/tests/Feature/AnalyticsOrderFormStage1BTest.php`.

Zasada: event `form_order_created` jest emitowany dopiero po skutecznym utworzeniu albo aktualizacji `FormOrder`, po `FormOrderParticipant::syncFromFormOrder()` i po zapisach pomocniczych wymaganych do dotychczasowego redirectu. Payload nie może zawierać danych klienta, uczestnika, szkoły, NIP, telefonu ani e-maila.

Miejsca podłączenia:

- deferred flow: `CourseController::storeOrderForm()` po zapisie `FormOrder`, po `FormOrderParticipant::syncFromFormOrder()`, po `FormOrderCheckoutResumeService::storeAfterSubmit()` i tuż przed dotychczasowym redirectem do `orders.summary`,
- online flow: `CourseController::processOrderFormOnlinePayment()` po zapisie `FormOrder`, po `FormOrderParticipant::syncFromFormOrder()`, po utworzeniu `OnlinePaymentOrder` i `storeAfterSubmit()`, ale przed wywołaniem PayU/PayNow i przed redirectem do bramki.

Nie dodano `DB::transaction()` ani `DB::afterCommit()`. Nie zmieniono redirectów, walidacji, zapisu `FormOrder`, zapisu uczestników, tworzenia `OnlinePaymentOrder`, płatności, webhooków, faktur, iFirma, KSeF, certyfikatów ani starych agregatów.

Payload `form_order_created`:

- pola główne: `event_name`, `event_category=conversion`, `analytics_session_id`, `order_form_session_id`, `form_order_id`, `course_id`, `course_title_snapshot`, `price_variant_id`, `campaign_code`, UTM, `route_name`, `path`, `referrer_domain`, `device_type`,
- `metadata`: `order_flow` (`deferred`/`online`), `payment_type`, `participant_count`, `has_price_variant`, `has_recipient`, `buyer_type`, `amount_gross`, `form_order_status`.

`form_order_id` może być zapisany w `pne_analytics`, ale nie może trafiać do eksportów AI-safe ani raportów przekazywanych zewnętrznemu AI.

### Zakres Etapu 1C — Agregaty Dzienne

Status: wdrożone lokalnie w `adm.pnedu.pl`.

Implementacja:

- serwis `App\Services\Analytics\AnalyticsDailyAggregationService`,
- komenda Artisan `analytics:aggregate-daily`,
- źródło: wyłącznie `analytics_events` (bez joinów do `form_orders`),
- cele: `analytics_daily_course_stats`, `analytics_daily_campaign_stats`,
- strefa czasowa agregacji: `Europe/Warsaw` (`ANALYTICS_AGGREGATION_TIMEZONE`),
- idempotencja: delete wierszy dla danej daty + przeliczenie od zera + insert,
- brak schedulera w kodzie — uruchamianie ręczne komendą.

Komenda:

```bash
./vendor/bin/sail artisan analytics:aggregate-daily --date=2026-06-24
./vendor/bin/sail artisan analytics:aggregate-daily --from=2026-06-01 --to=2026-06-24
./vendor/bin/sail artisan analytics:aggregate-daily --force
```

Domyślnie (bez opcji daty): dzień poprzedni według `Europe/Warsaw`.

Mapowanie eventów → `analytics_daily_course_stats` (klucz: `stat_date + course_id`):

| Event | Kolumna |
|-------|---------|
| `course_description_viewed` | `views_course_description` |
| `order_form_viewed` | `views_order_form` |
| `order_form_submit_attempted` | `submit_attempts` (próby wysłania, nie utworzone zamówienia) |
| `order_form_validation_failed` | `validation_failures` |
| `form_order_created` | `orders_created` |
| `form_order_created.metadata.amount_gross` | suma w `revenue_snapshot` |

Kolumny bez eventów w Etapie 1 (`form_starts`, `payment_orders_created`, `paid_orders`, `invoiced_orders`) pozostają `0`.

Mapowanie eventów → `analytics_daily_campaign_stats` (klucz: `stat_date + campaign_code`, tylko eventy z `campaign_code`):

| Event | Kolumna |
|-------|---------|
| `campaign_short_link_visit` | `link_entries` |
| `course_description_viewed` | `course_description_views` |
| `order_form_viewed` | `order_form_views` |
| `order_form_submit_attempted` | `submit_attempts` |
| `order_form_validation_failed` | `validation_failures` |
| `form_order_created` | `orders_created` |
| `form_order_created.metadata.amount_gross` | suma w `revenue_snapshot` |

Eventy bez dedykowanych kolumn w schemacie MVP (`campaign_redirect_resolved`, `utm_captured`) nie są liczone w agregatach kampanii w Etapie 1C.

Ograniczenie wymiarów kampanii: rollup zapisuje jeden wiersz na `stat_date + campaign_code` z `landing_target`, `campaign_content_depth`, `cta_type` = `NULL`; `campaign_channel` i `campaign_id` wybierane jako dominujące wartości z eventów.

Nie wdrażać w tym etapie:

- schedulera cron,
- aktualizacji starych agregatów `marketing_campaign_stats_daily` / `course_page_stats_daily`.

### Zakres Etapu 1D — Dashboard Lejka Sprzedaży

Status: wdrożone lokalnie w `adm.pnedu.pl`.

Implementacja:

- serwis `App\Services\Analytics\AnalyticsSalesFunnelDashboardService`,
- kontroler `App\Http\Controllers\Analytics\AnalyticsSalesFunnelController`,
- widok `resources/views/analytics/sales-funnel/index.blade.php`,
- route `GET /analytics/sales-funnel` (`analytics.sales-funnel.index`),
- menu: `Analityka -> Lejek sprzedaży`,
- dostęp: admin/super admin (`analytics.debug.access`),
- flaga: `ANALYTICS_SALES_FUNNEL_DASHBOARD_ENABLED` (domyślnie `true`).

Źródło danych (read-only):

- `analytics_daily_course_stats`,
- `analytics_daily_campaign_stats`.

Filtry:

- zakres dat (domyślnie ostatnie 14 dni, `Europe/Warsaw`),
- `campaign_code`,
- `course_id`,
- `landing_target` (kolumna w agregatach kampanii),
- brak filtrów UTM — kolumny UTM nie występują w tabelach dziennych Etapu 1C.

Sekcje widoku:

1. kafelki podsumowania i wskaźniki konwersji,
2. lejek ogólny,
3. tabela kampanii (sortowanie query `sort=`),
4. tabela szkoleń,
5. porównanie landing target (kampania + proxy z agregatów kursów),
6. proste alerty regułowe (bez AI).

Mapowanie wyświetlanych metryk:

| UI | Źródło |
|----|--------|
| Kliknięcia linków | `analytics_daily_campaign_stats.link_entries` |
| Wejścia w opis | `analytics_daily_course_stats.views_course_description` |
| Wejścia w formularz | `analytics_daily_course_stats.views_order_form` |
| Próby submitu | `analytics_daily_course_stats.submit_attempts` |
| Błędy walidacji | `analytics_daily_course_stats.validation_failures` |
| Zamówienia | `analytics_daily_course_stats.orders_created` |
| Przychód brutto | `analytics_daily_course_stats.revenue_snapshot` |

Wskaźniki konwersji (z zabezpieczeniem dzielenia przez zero):

- opis → formularz,
- formularz → submit,
- submit → zamówienie,
- formularz → zamówienie,
- błędy walidacji / submit.

Nie wdrożono w 1D:

- wykresów zaawansowanych,
- eksportów,
- raw eventów w widoku (link do panelu debug),
- eventów płatności/faktur,
- JS, porzuceń, A/B, AI, AI-safe.

### Eventy Poza Zakresem Etapu 1

Nie wdrażać teraz:

- `online_payment_selected`,
- `deferred_invoice_selected`,
- `payment_order_created`,
- `payment_status_changed`,
- `invoice_created`.

Te eventy dotyczą płatności i faktur. Wymagają osobnego etapu i testów regresji procesów finansowych.

Status po wdrożeniu 2A-1:

- submit formularza jest śledzony w 1B-1,
- walidacja jest śledzona w 1B-1,
- tworzenie zamówienia jest śledzone w 1B-2,
- wybór metody płatności jest śledzony w 2A-1 (`online_payment_selected`, `deferred_invoice_selected`),
- utworzenie zamówienia płatności online jest śledzone w 2A-2 (`payment_order_created`),
- zmiana statusu płatności jest śledzona w 2B-1 (`payment_status_changed`; webhook + return sync PayU/PayNow, deterministyczny event_uuid),
- wystawienie/zafakturowanie jest śledzone w 2C-1 (`invoice_created`; observer `FormOrderObserver` w `pneadm`, przejście invoice_number empty→present),
- agregaty/dashboard płatności nie są wdrożone,
- JS tracking nie jest wdrożony,
- porzucenia formularza nie są wdrożone,
- testy A/B nie są wdrożone,
- dashboard biznesowy nie jest wdrożony,
- AI nie jest wdrożone,
- eksporty AI-safe nie są wdrożone.

### Tabela Eventów I Miejsc Podłączenia

| Event | Gdzie w kodzie | Kiedy wywołać | Minimalny payload | Ryzyko | Test |
|---|---|---|---|---|---|
| `campaign_short_link_visit` | `MarketingCampaignShortLinkController::__invoke()` | po znalezieniu targetu, przed redirectem | `analytics_session_id`, `campaign_code`, `route_name`, `path`, `referrer_domain` | podwójne liczenie z obecnym `MarketingCampaignLinkTracker` | GET `/l/{code}` dispatchuje job na queue `analytics` |
| `campaign_redirect_resolved` | `MarketingCampaignLinkResolver::resolveRedirectPath()` albo po jego wywołaniu w kontrolerze | po ustaleniu `course_id`, `landing_target`, UTM | `campaign_code`, `course_id`, `landing_target`, `utm_*` | resolver dziś zwraca tylko string; może wymagać nowego DTO/metody bez łamania API | test resolvera/short linka potwierdza payload |
| `utm_captured` | `CaptureMarketingSource::handle()` | gdy `captureFromRequest()` zwróci payload | `campaign_code`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, safe request context | duplicate z short linkiem po redirect; event powinien być idempotentny lub akceptowany jako osobny etap | request z UTM dispatchuje event bez raw URL/referrer |
| `course_description_viewed` | `TrackCoursePageView::handle()` po successful response, page type `course_show` | równolegle z obecnym `trackCourseShow()` | `course_id`, `course_title_snapshot`, `campaign_code`, `landing_target`, `route_name`, `path`, `referrer_domain` | nie zmieniać obecnego licznika `course_page_stats_daily` | GET `/courses/{id}` dispatchuje event i nadal aktualizuje stary agregat jak dotąd |
| `order_form_viewed` | `TrackCoursePageView::handle()` po successful response, page type `order_form` | równolegle z obecnym `trackOrderForm()` | `course_id`, `course_title_snapshot`, `campaign_code`, `order_form_session_id`, `landing_target`, `route_name`, `path`, `referrer_domain` | utworzenie sesji formularza bez JS; nie pomylić z porzuceniami | GET `/courses/{id}/order-form` dispatchuje event |
| `order_form_submit_attempted` | początek `CourseController::storeOrderForm()` | przed walidacją, po ustaleniu `buyer_type` | `course_id`, `course_title_snapshot`, `order_form_session_id`, `campaign_code`, UTM, `route_name`, `path`, `referrer_domain`, `device_type`, `metadata.has_price_variant`, `metadata.price_variant_id` | nie czytać raw input; bez wartości pól | wdrożone w 1B-1; POST formularza dispatchuje event mimo błędu walidacji |
| `order_form_validation_failed` | `CourseController::storeOrderForm()` przy `ValidationException` i ręcznych `withErrors()` | gdy walidacja nie przejdzie | `course_id`, `order_form_session_id`, `campaign_code`, `metadata.validation_context`, `metadata.error_count`, `metadata.field_keys`, `metadata.section_keys`, `metadata.error_codes` | bez wartości pól, bez komunikatów błędów, bez raw input | wdrożone w 1B-1; błędny POST wysyła event bez wartości pól |
| `form_order_created` | `CourseController::storeOrderForm()` i `CourseController::processOrderFormOnlinePayment()` | po zapisie `FormOrder`, po `FormOrderParticipant::syncFromFormOrder()`, w online przed utworzeniem `OnlinePaymentOrder` i przed bramką | `form_order_id`, `course_id`, `course_title_snapshot`, `order_form_session_id`, `campaign_code`, `metadata.order_flow`, `metadata.buyer_type`, `metadata.payment_type`, `metadata.participant_count`, `metadata.amount_gross` | nie trackować danych uczestnika, fakturowych ani płatniczych; `form_order_id` nie trafia do AI-safe exportów | wdrożone w 1B-2; poprawny deferred i online POST tworzą zamówienie i dispatchują jeden event |
| `online_payment_selected` | `CourseController::storeOrderForm()`, gałąź online | po udanej walidacji, po ustaleniu `payment_type=online` i bramki, tuż przed `processOrderFormOnlinePayment()` | `course_id`, `course_title_snapshot`, `order_form_session_id`, `campaign_code`, `route_name`, `path`, `referrer_domain`, `device_type`, `metadata.payment_type=online`, `metadata.payment_gateway` (`payu`/`paynow`/`unknown`), `metadata.buyer_type`, `metadata.has_price_variant`, `metadata.order_flow=online` | bez danych płatnika/karty/bramki; oznacza tylko wybór metody, nie utworzenie `OnlinePaymentOrder` | wdrożone w 2A-1; online POST dispatchuje event przed redirectem do bramki |
| `deferred_invoice_selected` | `CourseController::storeOrderForm()`, gałąź deferred | po udanej walidacji, po ustaleniu `payment_type=deferred`, przed utworzeniem zamówienia odroczonego | `course_id`, `course_title_snapshot`, `order_form_session_id`, `campaign_code`, `route_name`, `path`, `referrer_domain`, `device_type`, `metadata.payment_type=deferred_invoice`, `metadata.buyer_type`, `metadata.has_price_variant`, `metadata.order_flow=deferred` | bez danych fakturowych/uczestnika; oznacza tylko wybór metody, nie wystawienie faktury | wdrożone w 2A-1; deferred POST dispatchuje event |
| `payment_order_created` | `CourseController::processOrderFormOnlinePayment()` | po utworzeniu `OnlinePaymentOrder`, przed `storeAfterSubmit` i przed redirectem do PayU/PayNow | `payment_order_id`, `form_order_id`, `amount_snapshot`, `course_id`, `course_title_snapshot`, `order_form_session_id`, `campaign_code`, `route_name`, `path`, `referrer_domain`, `device_type`, `metadata.payment_gateway`, `metadata.payment_type=online`, `metadata.order_flow=online`, `metadata.buyer_type`, `metadata.has_price_variant` | bez danych płatnika/karty/bramki; oznacza utworzenie rekordu płatności, nie sukces płatności | wdrożone w 2A-2; online POST dispatchuje event przed redirectem do bramki |
| `payment_status_changed` | `PaymentController::payuNotify()`, `paynowNotify()`, `syncPayuOrderFromApi()`, `syncPaynowOrderFromApi()` | po skutecznym `update(['status'])` i `syncLinkedFormOrderPaymentStatus()` | `payment_order_id`, `form_order_id`, `course_id`, `amount_snapshot`, `metadata.payment_gateway`, `metadata.payment_status`, `metadata.payment_previous_status`, `metadata.status_source` (`webhook`/`return_sync`), `metadata.payment_type=online`, `metadata.amount_gross`, `metadata.order_flow=online` | event server-to-server: BEZ `analytics_session_id`, `order_form_session_id`, route, path, referrer, device; bez raw statusu/payloadu bramki; deterministyczny `event_uuid` (UUID v5, bez `status_source`); tylko płatności z `form_order_id` | wdrożone w 2B-1; webhook i return sync tego samego statusu = jeden rekord (insertOrIgnore) |
| `invoice_created` | `App\Observers\FormOrderObserver` (`created`/`updated`) w `pneadm`; emisja przez `InvoiceAnalyticsTracker` | przy przejściu `invoice_number` empty→present (pierwsze poprawne; ≠ `''`, ≠ `'0'`); pro-forma nie liczy się | top-level `form_order_id`, `course_id`, `amount_snapshot`; `metadata.order_flow` (z `payment_mode`), `metadata.invoice_path_type` (`ifirma`/`manual`/`unknown`), `metadata.payment_type`, `metadata.amount_gross` (z `product_price`) | event backoffice: BEZ sesji/route/path/referrer/device; oznacza ZAFAKTUROWANE, nie opłacone (ADR-005); bez numeru faktury, NIP, nazw/adresów, danych uczestników, danych/raw iFirma, KSeF, raw request, toArray; idempotencja `event_uuid = invoice_created\|{form_order_id}` + insertOrIgnore; online z fakturą NIE liczyć podwójnie w „rozliczone łącznie" | wdrożone w 2C-1; testy `AnalyticsInvoiceCreatedStage2C1Test` (17) |

### `analytics_session_id`

Rekomendacja Etapu 1:

- cookie `pne_analytics_sid`,
- wartość: UUID v4,
- czas życia: `ANALYTICS_SESSION_DAYS`, domyślnie 30 dni,
- cookie `HttpOnly`, `SameSite=Lax`, `secure` w produkcji,
- nie używać e-maila, IP, user agenta ani hasha danych osobowych,
- nie zastępować nim istniejącego `pne_funnel_sid`, ale można skorzystać z podobnego wzorca tworzenia cookie,
- w requestach bez cookie wygenerować nowy UUID i dodać cookie do response.

Miejsce implementacji:

- `pnedu/app/Services/Analytics/AnalyticsSessionService.php`,
- wywoływany przez `BackendAnalyticsTracker`,
- cookie ustawiane na response fail-silent.

Testy:

- pierwszy request dostaje cookie UUID,
- kolejny request używa tego samego UUID,
- UUID nie zawiera PII,
- wyłączona analityka nie tworzy eventu, ale decyzja czy tworzyć cookie przy `off` wymaga potwierdzenia; rekomendacja: nie tworzyć.

### `order_form_session_id`

Rekomendacja Etapu 1 backend-only:

- cookie per kurs `pne_order_form_sid_{course_id}`,
- wartość: UUID v4,
- tworzyć przy `order_form_viewed`,
- używać przy `order_form_submit_attempted`, `order_form_validation_failed`, `form_order_created`,
- nie oznaczać jeszcze porzucenia,
- nie zapisywać wartości pól formularza.

Rekomendowany czas życia:

- `ANALYTICS_ORDER_FORM_SESSION_HOURS`, domyślnie 24 godziny.

Status po Etapie 1B-2:

- `order_form_session_id` jest tworzony przy `order_form_viewed`,
- przy submit/walidacji/utworzeniu zamówienia tracker używa istniejącego cookie lub tworzy UUID z metadaną `order_form_session_created_on_submit=true`,
- `order_form_session_id` jest zapisywany także przy `form_order_created`.

### Stare Agregaty A Nowe Eventy

Zasady Etapu 1:

- nie zmieniać `MarketingCampaignLinkTracker`,
- nie zmieniać `CoursePageViewTracker` logiki agregatów,
- nie aktualizować starych agregatów z nowych eventów,
- nowe eventy służą do równoległej walidacji i przyszłych agregatów,
- w Etapie 1C porównywać dzienne sumy eventów z `marketing_campaign_stats_daily` i `course_page_stats_daily`, ale nie scalać automatycznie.

### Testy Etapu 1

Minimalny zestaw testów:

- short link dispatchuje `campaign_short_link_visit`,
- short link dispatchuje `campaign_redirect_resolved`,
- UTM request dispatchuje `utm_captured`,
- opis szkolenia dispatchuje `course_description_viewed`,
- formularz dispatchuje `order_form_viewed`,
- submit dispatchuje `order_form_submit_attempted`,
- błąd walidacji dispatchuje `order_form_validation_failed`,
- udany zapis dispatchuje `form_order_created` (deferred i online),
- payload nie zawiera PII,
- payload nie zawiera raw `url` ani raw `referrer`,
- błędny Redis nie psuje requestu,
- błędna baza `pne_analytics` nie psuje requestu,
- `ANALYTICS_ENABLED=false` nie psuje requestu,
- formularz działa tak samo z analityką i bez analityki,
- istniejące agregaty zachowują obecne działanie,
- każdy event idzie na queue `analytics`.

Wykonane testy po Etapie 1A:

- `sail artisan test --filter=Analytics` w `pnedu` — 24 testy, 107 asercji, wynik pozytywny,
- `sail artisan test --filter='MarketingCampaign(LinkTracker|ShortLink)'` w `pnedu` — 9 testów, 21 asercji, wynik pozytywny.

## Panel Ustawień Analityki — Runtime Override (wdrożone 2026-06-25)

- Tabela `analytics_settings` w bazie `pneadm` (connection domyślny `mysql`, NIE `analytics`):
  `enabled_override` (nullable bool), `default_mode_override` (nullable string),
  `updated_by` (nullable), `timestamps`. Jeden rekord `id=1`.
- Modele odczytu/zapisu:
  - `pneadm`: `App\Models\AnalyticsSetting` (connection domyślny) — odczyt + zapis + cache,
  - `pnedu`: `App\Models\AnalyticsSetting` (connection `pneadm`) — tylko odczyt + cache.
- Resolver (`AnalyticsModeResolver` w obu projektach): hard kill switch `config('analytics.enabled')`
  → `enabled_override` → `default_mode_override` → `config('analytics.default_mode')` → fallback `standard`.
- Panel adm: `GET /analytics/settings` (`analytics.settings.index`), `POST /analytics/settings`
  (`analytics.settings.update`), kontroler `App\Http\Controllers\Analytics\AnalyticsSettingsController`,
  widok `resources/views/analytics/settings/index.blade.php`, dostęp `isAdmin` (middleware grupy analytics).
- Cache `analytics_settings_singleton` (TTL 60 s), czyszczony po zapisie.
- Audit log: `ActivityLog::logCustom('analytics_settings_updated', ...)` (stare/nowe wartości).
- Menu: dodano `Analityka -> Ustawienia`; przemianowano `Ustawienia -> Analityka` → `GA i lejek (cookie)`.
- Testy: `pneadm` — `AnalyticsSettingsPanelTest` (15) + `AnalyticsRuntimeOverrideResolverTest` (6);
  `pnedu` — `AnalyticsRuntimeOverrideResolverTest` (5). Wynik: `--filter=Analytics` zielone w obu projektach.

### Baner ostrzegawczy stanu analityki (wdrożone 2026-06-25)

- Serwis: `App\Services\Analytics\AnalyticsRuntimeStatusService` (read-only; zwraca `config_enabled`,
  `runtime_enabled_override`, `runtime_default_mode_override`, `effective_enabled`, `effective_mode`,
  `source`, `warning_level`, `show_banner`, `message`).
- Partial: `resources/views/analytics/partials/status-banner.blade.php` (param `showSettingsLink`).
- Wpięcie: `analytics/sales-funnel/index.blade.php`, `analytics/debug-events/index.blade.php`
  (z linkiem do ustawień), `analytics/settings/index.blade.php` (bez linku).
- Poziomy: danger (hard kill switch lub `off`), warning (`aggregate_only`, `light`); brak dla `standard`/`full`.
- Strona ustawień zawiera informację o osobnym `.env` w `pnedu` (lokalny hard kill switch).
- Panel nie odpytuje `pnedu` przez health endpoint.
- Testy: `AnalyticsStatusBannerTest` (12). `--filter=Analytics` w `pneadm`: 89 passed.

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalne nazwy klas.
- Dopisać finalne trasy endpointów.
- Dopisać realne komendy workerów.
- Dopisać wyniki testów fail-silent.
- Dopisać decyzję o batchowaniu eventów JS.
