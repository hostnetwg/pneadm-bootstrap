# Roadmapa Analityki Eventowej

Data utworzenia/aktualizacji: 2026-06-24  
Status: wersja robocza, do potwierdzenia przez właściciela

## Cel Analityki Eventowej

Celem analityki eventowej jest odtworzenie pełnej ścieżki użytkownika:

```text
kampania
→ kliknięcie
→ opis szkolenia
→ CTA
→ formularz
→ rozpoczęcie formularza
→ błędy walidacji
→ porzucenie albo zamówienie
→ płatność
→ faktura
```

Obecna analityka jest zbyt agregowana. Pozwala liczyć wejścia i zamówienia, ale nie pokazuje, gdzie dokładnie użytkownicy odpadają.

## Problem Obecnej Analityki

Obecnie istnieją:

- `marketing_campaign_stats_daily`,
- `course_page_stats_daily`,
- `form_orders.fb_source`,
- `form_orders.conversion_placement`.

Brakuje:

- trwałego `analytics_session_id`,
- raw eventów,
- eventów formularza,
- sesji formularza,
- porzuceń,
- błędów walidacji per pole,
- testów A/B,
- eksportów AI-safe,
- snapshotów pod AI.

## Docelowy Schemat

```text
użytkownik
→ pnedu.pl
→ backend tracker + JS tracker
→ AnalyticsService
→ Redis queue analytics
→ StoreAnalyticsEventJob
→ pne_analytics
→ agregaty dzienne
→ dashboard adm.pnedu.pl
→ eksporty AI-safe
→ przyszły AI-doradca
```

## Etap 0 — Dokumentacja I Decyzje

Zakres:

- utworzenie dokumentacji w `docs/`,
- opis kontekstu biznesowego,
- opis architektury,
- decyzja o `pne_analytics`,
- decyzja o trybach analityki,
- decyzja o Redis queue `analytics`,
- decyzja o braku danych osobowych w analityce.

Kryteria ukończenia:

- dokumenty w `docs/` istnieją,
- ADR-y są zapisane,
- właściciel potwierdził decyzje wymagające potwierdzenia.

## Etap 1 — `pne_analytics` I Backend Tracking

Zakres:

- utworzenie bazy `pne_analytics`,
- dodanie connection w obu aplikacjach,
- tabele MVP,
- `AnalyticsService`,
- job do zapisu eventów,
- backend eventy krytyczne.

Eventy:

- `campaign_short_link_visit`,
- `campaign_redirect_resolved`,
- `utm_captured`,
- `course_description_viewed`,
- `order_form_viewed`,
- `order_form_submit_attempted`,
- `order_form_validation_failed`,
- `form_order_created`,
- `online_payment_selected`,
- `deferred_invoice_selected`,
- `payment_order_created`,
- `payment_status_changed`,
- `invoice_created`.

Kryteria ukończenia:

- eventy krytyczne trafiają do `pne_analytics`,
- awaria analityki nie blokuje formularza,
- eventy nie zawierają danych osobowych,
- istnieją testy fail-silent.

## Plan Implementacji Etapu 0/1

Status: plan techniczny przygotowany, bez wdrożenia kodu.  
Data dopisania: 2026-06-24.

### Aktualizacja Statusu Etapu 0

Wykonane:

- dokumentacja techniczno-biznesowa została utworzona,
- ADR-y zostały zapisane,
- plan tabel MVP został doprecyzowany,
- plan trackingu backendowego został doprecyzowany,
- wskazano konkretne miejsca przyszłego podłączenia eventów w `pnedu.pl` i `adm.pnedu.pl`.
- wdrożono connection `analytics` w obu projektach,
- wdrożono konfigurację `config/analytics.php` w obu projektach,
- uzupełniono `.env.example` bez sekretów w obu projektach,
- utworzono lokalną bazę `pne_analytics`,
- uruchomiono migrację MVP w projekcie `pneadm`,
- dodano modele, enumy, sanitizer, mode resolver, service i job w obu projektach,
- dodano i uruchomiono testy Etapu 0.

Nadal do wykonania przed Etapem 1:

- potwierdzić decyzje właściciela,
- wpisać produkcyjne hasło tylko w `.env` produkcji,
- zrotować ujawnione hasło produkcyjne po zakończeniu konfiguracji,
- uruchomić migracje na produkcji po przygotowaniu bezpiecznego okna wdrożeniowego,
- przygotować plan podłączenia pierwszych backend eventów.

### Aktualizacja Statusu Etapu 1

Etap 1 nie jest jeszcze rozpoczęty. Etap 0 nie podłączył żadnego trackingu do formularza, zamówień, płatności ani faktur.

Rekomendowane rozbicie Etapu 1:

1. Fundament analityki:
   - connection,
   - migracje,
   - modele,
   - enumy,
   - sanitizer,
   - mode resolver,
   - service,
   - job,
   - testy fail-silent.
2. Kampanie i wejścia:
   - short link,
   - redirect,
   - UTM,
   - opis szkolenia,
   - formularz.
3. Zamówienia:
   - submit attempted,
   - validation failed,
   - form order created,
   - online/deferred selected.
4. Płatności:
   - payment order created,
   - payment status changed.
5. Faktury:
   - invoice created w `adm.pnedu.pl`.

### Plan Etapu 1 — Do Implementacji

Data dopisania: 2026-06-24.  
Status: Etap 1A wdrożony lokalnie; Etap 1A-Debug wdrożony lokalnie; Etap 1B-1 i 1B-2 wdrożone lokalnie; Etap 1C wdrożony lokalnie w `adm.pnedu.pl`; Etap 2 do implementacji.

Etap 1 zostaje zawężony do minimalnego backend trackingu bez JS.

#### Etap 1A — Kampanie I Wejścia

Zakres:

- `campaign_short_link_visit`,
- `campaign_redirect_resolved`,
- `utm_captured`,
- `course_description_viewed`,
- `order_form_viewed`.

Status: wdrożone lokalnie w `pnedu.pl`.

Cel:

- mierzyć ścieżkę kampania → opis szkolenia → formularz,
- nie dotykać submitu ani zapisu zamówienia,
- nie zmieniać obecnych agregatów.

Kryteria ukończenia:

- eventy dispatchują się na queue `analytics`,
- payload nie zawiera PII,
- payload nie zawiera raw `url` ani raw `referrer`,
- obecne agregaty nadal działają jak wcześniej,
- wyłączenie analityki nie zmienia działania stron.

Status kryteriów po wdrożeniu 1A:

- testy potwierdzają dispatch na queue `analytics`,
- testy potwierdzają brak PII i brak raw `url`/`referrer`,
- testy regresji potwierdzają dotychczasowe działanie short linków i starych agregatów,
- `ANALYTICS_ENABLED=false` blokuje dispatch eventów i nie psuje requestu.

#### Etap 1A-Debug — Techniczny Podgląd Eventów

Zakres:

- read-only panel w `adm.pnedu.pl`,
- route `analytics.debug-events.index` (`/analytics/debug-events`),
- menu `Marketing i reklama -> Debug eventów`,
- odczyt ostatnich eventów z `pne_analytics.analytics_events`,
- filtry po eventach, kampanii, kursie, sesjach i datach,
- podgląd `metadata_json`,
- ostrzeżenia przy zakazanych kluczach w metadata/payloadzie,
- flaga `ANALYTICS_DEBUG_PANEL_ENABLED`.

Status: wdrożone lokalnie.

Ważne ograniczenie:

- to nie jest dashboard biznesowy,
- nie zawiera wykresów, eksportów, AI ani agregacji biznesowych,
- nie edytuje i nie kasuje eventów.

#### Etap 1B — Formularz I Zamówienie

Zakres Etapu 1B-1:

- `order_form_submit_attempted`,
- `order_form_validation_failed`.

Status: wdrożone lokalnie.

Kryteria spełnione:

- submit formularza dispatchuje `order_form_submit_attempted`,
- błędy głównego `$request->validate()` dispatchują `order_form_validation_failed`,
- ręczne `withErrors()->withInput()` dispatchują `order_form_validation_failed`,
- `ValidationException` jest rzucany dalej bez zmiany zachowania Laravel,
- payload nie zawiera raw inputu, raw requestu, raw URL, raw referrer ani wartości pól,
- testy potwierdzają brak wpływu na walidację i poprawny submit deferred.

Zakres Etapu 1B-2:

- `form_order_created`.

Status: wdrożone lokalnie w `pnedu.pl`.

Cel:

- mierzyć próbę submitu,
- mierzyć błędy walidacji bez wartości pól,
- mierzyć utworzenie zamówienia bez danych klienta.

Kryteria ukończenia:

- proces zamówienia działa identycznie przy włączonej i wyłączonej analityce,
- błąd Redis lub `pne_analytics` nie psuje requestu,
- eventy nie zawierają danych osobowych,
- `form_order_id` jest zapisany tylko w `pne_analytics`, nie w eksportach AI-safe.

Status po wdrożeniu 1B-2:

- `form_order_created` jest wdrożony lokalnie dla deferred i online flow,
- event powstaje po zapisie `FormOrder` i uczestnika; w online przed utworzeniem `OnlinePaymentOrder` i przed kontaktem z bramką,
- nie dodano `DB::transaction()` ani `DB::afterCommit()`,
- nie wdrożono eventów płatności, faktur, iFirma, KSeF, JS trackingu, porzuceń formularza, A/B testów, dashboardu biznesowego, AI ani eksportów AI-safe.

#### Etap 1C — Agregaty Dzienne

Status: wdrożone lokalnie w `adm.pnedu.pl`.

Zakres:

- `AnalyticsDailyAggregationService`,
- komenda `analytics:aggregate-daily`,
- przeliczenie `analytics_events` → `analytics_daily_course_stats` i `analytics_daily_campaign_stats`,
- strefa czasowa `Europe/Warsaw`,
- idempotencja przez delete + przeliczenie od zera dla każdej daty.

Kryteria ukończenia:

- agregaty liczone tylko z `pne_analytics`,
- stare tabele `marketing_campaign_stats_daily` i `course_page_stats_daily` nietknięte,
- brak joinów do `form_orders`, brak PII, brak kopiowania `metadata_json`,
- testy feature `AnalyticsDailyAggregationTest` przechodzą.

Status po wdrożeniu 1C:

- dzienne agregaty są dostępne w `pne_analytics`,
- scheduler cron dla agregacji — tylko jako plan operacyjny (ręczna komenda na start),
- nadal nie wdrożono: JS trackingu, porzuceń, A/B, AI, eksportów AI-safe, eventów płatności i faktur.

#### Etap 1D — Dashboard Lejka Sprzedaży

Status: wdrożone lokalnie w `adm.pnedu.pl`.

Zakres:

- route `/analytics/sales-funnel`,
- menu `Analityka -> Lejek sprzedaży`,
- read-only dashboard MVP na agregatach dziennych,
- filtry dat/kampanii/kursu/landing target,
- kafelki, lejek, tabele kampanii i szkoleń, alerty regułowe.

Status po wdrożeniu 1D:

- właściciel może analizować lejek z agregatów dziennych bez panelu debug,
- panel `Debug eventów` pozostaje osobno,
- nadal nie wdrożono: eventów płatności/faktur, JS, porzuceń, A/B, AI, eksportów AI-safe.

#### Poza Zakresem Etapu 1

Nie wdrażać teraz:

- JS trackingu,
- testów A/B,
- dashboardu,
- AI,
- eksportów AI-safe,
- przebudowy formularza,
- odzyskiwania porzuconych formularzy,
- `online_payment_selected`,
- `deferred_invoice_selected`,
- `payment_order_created`,
- `payment_status_changed`,
- `invoice_created`.

Status: nie wdrożono.

### Kryteria Gotowości Do Implementacji Etapu 0

Spełnione lokalnie:

- Właściciel zaakceptował connection `analytics` w obu projektach.
- Właściciel zaakceptował, że hasło produkcyjne jest wpisywane wyłącznie w `.env` produkcji.
- Właściciel zaakceptował lokalną bazę `pne_analytics` we wspólnym MySQL `pneadm-mysql`.
- Właściciel zaakceptował brak FK do `pneadm`.
- Właściciel zaakceptował whitelistę metadanych i brak PII.
- Testy analityki przechodzą w obu projektach.

### Kryteria Gotowości Do Implementacji Etapu 1

- Etap 0 jest wdrożony i testy przechodzą.
- Worker `analytics` działa lokalnie.
- Testy fail-silent potwierdzają brak wpływu na proces sprzedaży.
- Testy sanitizera blokują dane osobowe.
- Krótkie linki i obecne agregaty nadal działają.

### Decyzje Nadal Wymagające Potwierdzenia

- Retencja raw eventów: rekomendacja 180 dni.
- Retencja sesji formularza: rekomendacja 365 dni.
- Retencja agregatów: minimum 3 lata albo bezterminowo.
- Retencja eksportów AI-safe: 180-365 dni.
- Domyślny tryb dla płatnych szkoleń: rekomendacja `standard`.
- Tryb dla strategicznych kampanii sprzedażowych: rekomendacja `full`.
- Tryb dla bezpłatnych webinarów dyrektorskich: `standard` albo `full`.
- Tryb dla masowych webinarów TIK: rekomendacja `aggregate_only`.
- Dostęp do przyszłych eksportów AI-safe i AI-doradcy: tylko właściciel/admin.
- Polityka prywatności/cookies: prawdopodobnie wymaga aktualizacji przed pełnym trackingiem.
- Profile fakturowe powiązane z e-mailem: osobna analiza RODO/prawna.

## Etap 2 — JS Tracking Formularza

Zakres:

- JS tracker w formularzu zamówienia,
- endpoint do przyjmowania eventów JS,
- batchowanie eventów,
- brak wysyłania wartości pól.

Eventy:

- `order_form_js_loaded`,
- `order_form_started`,
- `buyer_type_selected`,
- `payment_type_selected`,
- `payment_gateway_selected`,
- `section_interacted`,
- `cta_clicked`,
- `time_checkpoint`,
- `before_unload_form_dirty`.

Eventy przyszłe:

- `gus_lookup_started`,
- `gus_lookup_success`,
- `gus_lookup_failed`,
- `gus_autofill_applied`,
- `ksef_option_selected`,
- `participant_added`,
- `participant_count_changed`,
- `price_recalculated`,
- `saved_billing_profile_used`.

Kryteria ukończenia:

- JS tracking działa na desktopie i mobile,
- brak wartości pól w payloadach,
- eventy są batchowane lub debounced,
- formularz działa bez JS trackera.

## Etap 3 — Agregaty I Dashboard

Zakres:

- agregaty dzienne per kurs,
- agregaty dzienne per kampania,
- pierwszy dashboard w `adm.pnedu.pl`.

Metryki:

- kliknięcia,
- wejścia w opis,
- wejścia w formularz,
- start formularza,
- submit,
- zamówienie,
- płatność,
- faktura,
- konwersje między etapami.

Kryteria ukończenia:

- dashboard pokazuje kampanie i kursy z problemami,
- można porównać `course_description` i `order_form_direct`,
- raporty nie zawierają danych osobowych.

## Etap 4 — Tryby Analityki I Sampling

Zakres:

- tryby `full`, `standard`, `light`, `aggregate_only`, `off`,
- sampling 100%, 50%, 10%,
- ustawienia globalne,
- ustawienia per kampania,
- ustawienia per szkolenie/formularz.

Kryteria ukończenia:

- można ograniczyć analitykę dla masowych wydarzeń,
- można awaryjnie wyłączyć analitykę,
- tryb aktywny jest widoczny w panelu.

## Etap 5 — Porzucenia Formularza

Zakres:

- `order_form_abandonments`,
- detekcja porzuceń po czasie,
- metryki czasu w formularzu,
- ostatnia sekcja/pole przed porzuceniem.

Kryteria ukończenia:

- można zobaczyć, gdzie użytkownicy porzucają formularz,
- nie są zapisywane dane osobowe,
- brak automatycznego odzyskiwania leadów na tym etapie.

## Etap 6 — Testy A/B

Zakres:

- tabele A/B,
- przypisanie wariantu,
- stały wariant dla użytkownika,
- eventy w kontekście testu,
- raport wyników.

Testy planowane:

- opis szkolenia vs formularz,
- pełny newsletter vs krótki newsletter,
- video pitch,
- CTA,
- formularz jednoetapowy vs wieloetapowy,
- elementy zaufania.

Kryteria ukończenia:

- użytkownik zachowuje wariant,
- zamówienie jest powiązane z wariantem,
- wyniki można raportować bez danych osobowych.

## Etap 7 — Eksporty AI-Safe

Zakres:

- CSV,
- XLSX,
- PDF,
- Markdown.

Dane eksportowane:

- agregaty,
- ID kursów/kampanii,
- nazwy kursów,
- typy kampanii,
- konwersje,
- przychody zagregowane.

Kryteria ukończenia:

- eksporty nie zawierają danych osobowych,
- istnieje historia eksportów,
- raport można wkleić do narzędzia AI.

## Etap 8 — Przygotowanie AI-Doradcy

Zakres:

- `analytics_snapshots`,
- struktura `ai_reports`,
- struktura `ai_interactions`,
- struktura `ai_usage_logs`,
- źródła danych dla AI-doradcy.

AI-doradca ma w przyszłości odpowiadać m.in.:

- co promować w tym tygodniu,
- gdzie tracimy sprzedaż,
- która kampania działa najlepiej,
- czy prowadzić do opisu czy formularza,
- które pola formularza są problematyczne.

Kryteria ukończenia:

- AI otrzymuje tylko dane AI-safe,
- dane są zagregowane,
- źródła raportu są zapisane.

## Do Aktualizacji Po Wdrożeniu

- Oznaczać każdy etap jako `planowany`, `w trakcie`, `wdrożony`, `utrzymanie`.
- Dopisać datę wdrożenia każdego etapu.
- Dopisać linki do PR/commitów.
- Dopisać rzeczywiste metryki po uruchomieniu dashboardu.
