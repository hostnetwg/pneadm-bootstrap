# Następne Kroki

Data utworzenia/aktualizacji: 2026-07-09  
Status: plan roboczy, do potwierdzenia przez właściciela

## Cel Dokumentu

Dokument określa najbliższe kroki po utworzeniu dokumentacji. Ma chronić projekt przed chaotycznym wdrożeniem analityki i przypominać, że obecny etap dotyczy dokumentacji, nie kodu.

## Pakiet Rozliczenia — ZAMKNIĘTY NA R3 (2026-06-26)

Decyzja Waldemara: **zamykamy pakiet rozliczeń na R3**. R4 odłożone do backlogu.

```text
R1   — agregaty rozliczeń           → produkcja GO
R2   — dashboard Analityka→Rozliczenia → produkcja GO
R2.1 — przycisk Przelicz rozliczenia  → produkcja GO (deploy 2026-06-26)
R3   — CSV AI-safe rozliczeń          → produkcja GO (deploy 2026-06-26)
R4   — submit_intent / alerty         → ODŁOŻONE (backlog)
```

- Deploy R2.1+R3 wykonany na produkcji (2026-06-26, HEAD `12f1298`, `optimize:clear`). Runbook: sekcja 13 w `docs/deploy/2026-06-R1-R2-revenue-production-deploy.md`.
- **Backlog R4** — `submit_intent` + alerty: odłożone do czasu zebrania stabilniejszej próby danych po R1–R3 i obserwacji rozliczeń. Uzasadnienie: alerty wymagają baseline'u i większej próby; `submit_intent` poprawi semantykę formularza, ale nie był potrzebny do zamknięcia pakietu Rozliczenia.
- **Rekomendowany tryb dalszej pracy:** przez kilka dni obserwować dane rozliczeń i porzuceń; nie uruchamiać alertów przed zebraniem stabilniejszej próby.
- **Następny aktywny temat:** monitoring danych po wdrożeniu R3 (bez startu R4).

## Lokalny dev na nowym komputerze (checklist)

Po sklonowaniu ostatniego commita:

- [x] Sieć `pne-network` i kontener `pneadm-mysql` (wspólny MySQL dla obu projektów),
- [x] Baza `pne_analytics` w `pneadm-mysql`,
- [x] Zmienne `DB_ANALYTICS_*` / `ANALYTICS_*` w `.env` obu projektów,
- [x] Migracje analityczne uruchomione tylko z `pneadm` (`sail artisan migrate`),
- [x] Testy `sail artisan test --filter=Analytics` w obu projektach,
- [ ] Ręczna weryfikacja panelu `/analytics/debug-events` po zalogowaniu jako admin,
- [ ] Worker kolejki `analytics` (opcjonalnie lokalnie): `sail artisan queue:work redis --queue=analytics`.

Szczegóły: `docs/analytics/TRACKING_IMPLEMENTATION_PLAN.md` → sekcja „Lokalna konfiguracja `pne_analytics` na nowym komputerze developerskim”.

## Najbliższe 10 Kroków

1. Otworzyć `adm.pnedu.pl -> Analityka -> Lejek sprzedaży` i zweryfikować dane po `analytics:aggregate-daily`.
2. Porównać nowe agregaty ze starymi `course_page_stats_daily` / `marketing_campaign_stats_daily` (tylko odczyt).
3. ~~Zaplanować cron produkcyjny dla `analytics:aggregate-daily` (np. 02:15 Europe/Warsaw).~~ **ZROBIONE** — wdrożony jako zwykły cron z `flock` w `pneadm` (02:15 czasu serwera = Europe/Warsaw; serwer działa w Europe/Warsaw, a komenda i tak liczy datę w tej strefie). NIE użyto Laravel Scheduler, bo pneadm nie ma `schedule:run`, a jego włączenie zdublowałoby worker kolejki. Catch-up i kontrola: `docs/deploy/2026-06-analytics-production-deploy.md` sekcja 8.6.
4. Wpisać produkcyjne hasło `pne_analytics` wyłącznie w `.env` produkcji obu aplikacji.
5. Po konfiguracji produkcji zrotować ujawnione w rozmowie hasło do bazy analitycznej.
6. Zweryfikować connection `analytics`, worker kolejki `analytics`, dashboard i panel debug na produkcji.
7. Przetestować filtry dashboardu (daty, kampania, kurs, landing target) na realnych danych.
8. ~~Taksonomia formularza v2 — Etap 2F (`traffic_channel` / atrybucja) wdrożony.~~ **ZROBIONE prod 2026-07-09** (`pnedu` `bc6deca`). **B4+ agregaty lejka** — **ZROBIONE prod** (`pneadm` `cb4d732`). Następny krok: obserwacja jakości danych od 09.07 (nie backfill historyczny atrybucji).
9. Rozważyć progi alertów dashboardu po pierwszych tygodniach obserwacji.
10. Skonsultować z ChatGPT kolejny etap (płatności, JS, porzucenia) po akceptacji właściciela.

## Następny Etap

Etap 0 został wdrożony lokalnie. Kolejnym krokiem jest konfiguracja produkcyjna i przygotowanie Etapu 1.

Etap 0:

- status: wdrożony lokalnie,
- connection `analytics` w obu projektach: wykonane,
- `.env.example` w obu projektach: wykonane,
- lokalna baza `pne_analytics` w MySQL `pneadm-mysql`: wykonane,
- migracje MVP w projekcie `pneadm`: wykonane lokalnie,
- modele analityczne: wykonane,
- enumy/stałe: wykonane,
- `AnalyticsPayloadSanitizer`: wykonane,
- `AnalyticsModeResolver`: wykonane,
- `AnalyticsService`: wykonane,
- `StoreAnalyticsEventJob`: wykonane,
- konfiguracja Redis queue `analytics`: wykonane,
- testy połączenia, sanitizera, mode resolvera i fail-silent: wykonane.

Etap 1:

- backend tracking krótkich linków: wdrożone w 1A,
- UTM: wdrożone w 1A,
- opis szkolenia: wdrożone w 1A,
- wejście w formularz: wdrożone w 1A,
- techniczny panel debug eventów w `adm.pnedu.pl`: wdrożone w 1A-Debug,
- submit: wdrożone w 1B-1,
- walidacja: wdrożone w 1B-1,
- tworzenie zamówienia: wdrożone w 1B-2 (`form_order_created`, deferred i online),
- agregaty dzienne: wdrożone w 1C (`analytics:aggregate-daily` w `adm.pnedu.pl`),
- dashboard lejka sprzedaży: wdrożony w 1D (`/analytics/sales-funnel`),
- wybór płatności: wdrożono w 2A-1 (`online_payment_selected`, `deferred_invoice_selected`),
- utworzenie zamówienia płatności online: wdrożono w 2A-2 (`payment_order_created`),
- status płatności: wdrożono w 2B-1 (`payment_status_changed`; webhook + return sync PayU/PayNow),
- zafakturowanie: wdrożono w 2C-1 (`invoice_created`; observer `FormOrderObserver` w `pneadm`, przejście invoice_number empty→present),
- taksonomia formularza v2: wdrożono pierwszy fundament (`AnalyticsEventContract`, `form_session_id`, endpoint JS akceptuje nowe eventy),
- agregaty/dashboard płatności i faktur: nie wdrożono.

### Decyzja terminologiczna: `invoice_number` = zafakturowane, nie opłacone (ADR-005) — ZAAKCEPTOWANA

Status: zaakceptowana (właściciel + ChatGPT, 2026-06-25). Dokumentacja, bez zmian kodu.

- `form_orders.invoice_number` (niepuste, ≠ `''`, ≠ `'0'`) oznacza **zafakturowane / rozliczone operacyjnie**, NIE fizyczny wpływ przelewu.
- Online: źródło prawdy o opłaceniu = bramka (`payment_status_changed: paid` → `online_paid`).
- Odroczone: rozliczenie operacyjne = pierwsze pojawienie się `invoice_number` (przyszły `deferred_invoiced` / event `invoice_created`).
- Metryka łączna: `settled_orders_total = online_paid + deferred_invoiced`.
- Docelowe 3 metryki zamówień: `online_paid`, `deferred_invoiced`, `settled_orders_total` („Opłacone online", „Zafakturowane odroczone", „Rozliczone łącznie").
- Docelowe 4 metryki przychodu: `ordered_revenue_gross`, `online_paid_revenue_gross`, `deferred_invoiced_revenue_gross`, `settled_revenue_gross`.
- Wykrywanie odroczonych po `payment_mode` / `order_flow` (NIE po braku `OnlinePaymentOrder`).
- Edge case: online z późniejszą fakturą NIE liczone podwójnie (faktura = znacznik księgowy).
- Alias `orders_paid` w `CourseFunnelStatsService` zostaje dla kompatybilności (bez refaktoryzacji teraz); w nowych metrykach używamy `orders_invoiced` / `deferred_invoiced`.
- Poza zakresem (osobne przyszłe eventy): rekonsyliacja przelewów (`bank_payment_confirmed`), korekty/anulowanie faktur, zwroty, chargebacki, częściowe płatności, zaliczki, wiele faktur, KSeF. Szczegóły: `docs/decisions/ADR-005-invoice-number-means-invoiced-not-paid.md`.

## Decyzje Właściciela Do Podjęcia

- Czy produkcyjna baza `srv66127_pne_analytics` jest docelową nazwą na stałe?
- Czy po ujawnieniu hasła w rozmowie zostanie ono zrotowane przed końcowym uruchomieniem produkcyjnym?
- Czy lokalna baza ma nazywać się `pne_analytics`, a nie `pne_analytics_dev`?
- Jaka ma być retencja raw eventów? Rekomendacja: 180 dni.
- Jaka ma być retencja sesji formularza? Rekomendacja: 365 dni.
- Jaka ma być retencja agregatów? Rekomendacja: minimum 3 lata albo bezterminowo.
- Jaka ma być retencja eksportów AI-safe? Rekomendacja: 180-365 dni.
- Czy raw eventy mają być backupowane tak samo długo jak agregaty?
- Czy tracking własny wymaga zmian w polityce cookies?
- Czy domyślny tryb dla płatnych szkoleń to `standard`? Rekomendacja: tak.
- Czy strategiczne kampanie sprzedażowe mają mieć `full`? Rekomendacja: tak.
- Czy bezpłatne webinary dyrektorskie mają mieć `standard` czy `full`?
- Czy masowe webinary TIK mają mieć zawsze `aggregate_only`? Rekomendacja: tak.
- Czy eksporty AI-safe mają być dostępne tylko dla właściciela/admina? Rekomendacja: tak.
- Czy AI-doradca ma być dostępny tylko w `adm.pnedu.pl` dla administratorów?
- Czy łączenie zapisanych profili fakturowych z e-mailem wymaga dodatkowej zgody i analizy prawnej? Rekomendacja: tak.

## Czego Nie Robimy Na Razie

Na tym etapie nie robimy:

- endpointów,
- zmian w `.env`,
- zmian w formularzu zamówienia,
- zmian w płatnościach,
- zmian w fakturach,
- zmian w KSeF,
- zmian w iFirma,
- wdrożenia AI,
- publicznego asystenta,
- automatycznego odzyskiwania porzuconych formularzy,
- testów A/B w produkcji.

Uwaga: migracja MVP, modele analityczne i klasy bazowe zostały utworzone w Etapie 0. Powyższe ograniczenie dotyczy teraz Etapu 1 i oznacza: nie tworzyć nowych migracji ani modeli poza uzasadnioną korektą fundamentu bez osobnej decyzji.

## Rekomendowany Prompt Do ChatGPT Do Konsultacji

Ten prompt służy do konsultacji kolejnego etapu z ChatGPT na `https://chatgpt.com/`:

```text
Etap 0–1D analityki PNEdu zostały wdrożone lokalnie w Cursorze.

Wdrożono m.in.:
- backend eventy w pnedu.pl (1A, 1B),
- panel debug eventów,
- agregaty dzienne analytics:aggregate-daily (1C),
- dashboard Lejek sprzedaży /analytics/sales-funnel (1D).

Nie wdrożono jeszcze:
- eventów płatności i faktur,
- JS trackingu, porzuceń, A/B,
- AI, eksportów AI-safe.

Przygotuj plan Etapu 2: eventy płatności (payment_order_created, payment_status_changed)
albo rozbudowy agregacji landing_target per kampania.
```

## Zadanie Implementacyjne Dla Cursora

Kolejne zadanie po akceptacji właściciela:

```text
Na podstawie dokumentacji w docs/ wdroż eventy płatności analityki PNEdu (Etap 2):
payment_order_created, payment_status_changed. Nie wdrażaj faktur, JS, A/B, AI.
Zachowaj fail-silent i RODO. Wszystkie komendy Laravel/PHP uruchamiaj przez sail.
```

## Status Implementacji Etapu 0/1

Etap 0 jest wdrożony lokalnie i opisany w:

- `docs/analytics/TRACKING_IMPLEMENTATION_PLAN.md`,
- `docs/analytics/DATABASE_SCHEMA_PLAN.md`,
- `docs/analytics/ANALYTICS_ROADMAP.md`.

Najważniejsze ustalenia:

- connection w kodzie ma nazywać się `analytics`,
- baza dev ma być dostępna dla obu projektów,
- produkcyjne dane dostępowe nie trafiają do repozytorium,
- migracje MVP powstają w projekcie `pneadm`,
- brak FK do `pneadm`,
- pierwsza implementacja nie dotyka procesów sprzedaży,
- backend eventy są dopiero Etapem 1.

## Decyzje Nadal Wymagające Potwierdzenia

- retencja raw eventów,
- retencja sesji formularza,
- retencja agregatów,
- retencja eksportów AI-safe,
- domyślne tryby analityki,
- backup raw eventów,
- aktualizacja polityki cookies,
- zakres dostępu do eksportów AI-safe,
- analiza prawna profili fakturowych powiązanych z e-mailem.
- czy Etap 1A wdrażać jako pierwszy osobny commit,
- nazwa cookie dla `analytics_session_id`, rekomendacja `pne_analytics_sid`,
- czas życia `analytics_session_id`, rekomendacja 7-30 dni,
- sposób utrzymania `order_form_session_id`, rekomendacja 24h cookie/session per kurs,
- czy przy `ANALYTICS_ENABLED=false` tworzyć cookie sesji analitycznej; rekomendacja: nie.

## Zasady Aktualizacji Dokumentacji

Po każdym większym etapie należy zaktualizować:

- `docs/analytics/ANALYTICS_ROADMAP.md`,
- `docs/analytics/DATABASE_SCHEMA_PLAN.md`,
- `docs/analytics/TRACKING_IMPLEMENTATION_PLAN.md`,
- odpowiednie ADR-y,
- `docs/NEXT_STEPS.md`.

Po każdej decyzji właściciela należy:

- zmienić status w ADR,
- dopisać datę decyzji,
- opisać konsekwencje.

Po każdej implementacji należy:

- dopisać realne klasy i pliki,
- dopisać realne trasy,
- dopisać realne komendy,
- dopisać testy,
- oznaczyć rozbieżności między planem i kodem.

## Panel Ustawień Analityki — Runtime Override (wdrożone 2026-06-25)

- Wdrożono panel `Analityka -> Ustawienia` (`/analytics/settings`) — podgląd i zmiana trybu analityki.
- Wdrożono runtime override z bazy `pneadm` (tabela `analytics_settings`), wspólny dla `pneadm` i `pnedu`.
- `.env ANALYTICS_ENABLED=false` pozostaje **hard kill switch** (priorytet absolutny nad override).
- `sample_rate` na tym etapie jest **tylko podglądowe** (bez edycji).
- Stare `Ustawienia -> Analityka` przemianowane na `Ustawienia -> GA i lejek (cookie)`.
- Dodano baner ostrzegawczy stanu analityki w panelach `Analityka` (sales-funnel, debug-events, ustawienia):
  pokazuje `off` i hard kill switch (czerwony) oraz `aggregate_only`/`light` (żółty); brak dla `standard`/`full`.
  Panel nadal nie odpytuje `pnedu` (brak health endpointu) — `pnedu` może mieć własny `.env` hard kill switch.
- Uwaga dev: testy używają osobnej bazy `testing`. Nową migrację trzeba zastosować także tam, np.:
  `DB_DATABASE=testing php artisan migrate --path=database/migrations/2026_06_25_120000_create_analytics_settings_table.php` (wewnątrz kontenera Sail).

## Etap B — JS Tracking Formularza (B1 + B1a + B2 zacommitowane i pushed, 2026-06-25)

- **B1** — backendowy endpoint `POST /analytics/client-events` w `pnedu` (`6b32a4d`): batch ≤20 eventów, payload ≤10 KB, rate limit 60/min/IP, **fail-silent `204`**, 4 eventy MVP (`order_form_started`, `order_form_section_interacted`, `order_form_cta_clicked`, `order_form_submit_clicked`), whitelisty wartości (`section_key`/`cta_key`/`trigger`), tryby (standard = pełne MVP). CSRF-exempt dla wsparcia `navigator.sendBeacon`. Zero PII.
- **B1a — hardening** (w tym samym commicie `6b32a4d`):
  - dodano **same-origin guard** (porównanie po HOŚCIE z `Origin`/`Referer`; obcy host → `204` bez zapisu; oba puste → best-effort; nigdy `403`; bez logowania URL-i); CSRF-exempt **zostaje**;
  - klientowski `event_uuid` jest **tylko seedem deduplikacji**; finalny `event_uuid` generowany/namespacowany po stronie serwera (deterministyczny UUIDv5: `client_js|order_form_session_id|event_name|client_event_uuid`, mieści się w `char(36)`);
  - batch większy niż limit jest **ucinany do limitu** (best-effort, nie odrzucamy całości);
  - **whitelisty bez zmian** po audycie realnego formularza (`invoice` zostaje, NIE dodano `invoice_data`);
  - **porzucenia nadal poza zakresem** B1/B1a (planowane jako agregacja po 24 h w B3).
- **B2 — JS collector na formularzu** (`pnedu` `bdc74ca`, **zacommitowane i wypchnięte**):
  - inline, fail-silent collector ładowany **tylko** na stronie formularza zamówienia (layout nie używa `@vite`; styl projektu = inline + CDN + `@stack('scripts')`);
  - wysyła 4 eventy MVP do `POST /analytics/client-events`; sekcje/CTA przez `data-analytics-section`/`data-analytics-cta` (whitelista); zero wartości pól, zero PII w configu;
  - batch ≤20, debounce ~3 s, flush `submit`/`visibilitychange`/`pagehide` (`sendBeacon` + `fetch keepalive`); klientowski `event_uuid` = seed (UUIDv5 po stronie serwera);
  - **nie blokuje formularza** w żadnym scenariuszu (brak `preventDefault`); gdy hard kill switch — collector nie jest renderowany;
  - pliki: `resources/views/courses/partials/order-form-client-tracking.blade.php`, zmiany w `resources/views/courses/order-form.blade.php`, test `tests/Feature/AnalyticsOrderFormClientTrackingStageB2Test.php`.
- **Deploy produkcyjny B2:** **GO** (decyzja Waldemara 2026-06-25). Instrukcja: `docs/deploy/2026-06-analytics-production-deploy.md` sekcje 7.2, 7.3, 9.1. `pnedu`: `git pull` + `npm ci` + `npm run build` + cache + `queue:restart`. `pneadm`: `git pull` + cache (dokumentacja + linki w sales-funnel `60acc21`).
- Testy: `--filter=Analytics` → **110 passed** (pnedu), **98 passed** (pneadm); sanity formularza → **15 passed**; `npm run build` → OK.
- **B3 — agregacja porzuceń (wdrożone produkcyjnie 2026-06-25, `pneadm` `b0b4535`):** zakres **kurs + kampania**. Komenda `analytics:aggregate-abandonments`, domyślnie 2 dni wstecz. Klasyfikacja po `order_form_session_id`; kampania **first-touch**; bez PII. Catch-up prod 2026-06-25: 9 wierszy kursów, 6 kampanii. Cron 03:15 Europe/Warsaw.
- **B4 — dashboard porzuceń:** ✅ wdrożone produkcyjnie (`pneadm` `a6ee852`, 2026-06-26). Read-only, czyta wyłącznie agregaty B3, nie skanuje `analytics_events`; dane per kurs i per kampania; `lag=2`; first-event/first-touch attribution; brak PII. Route `analytics.form-abandonments.index`, menu `Analityka → Porzucenia formularza`.
- **B5 — CSV AI-safe export:** ✅ wdrożone produkcyjnie (`pneadm` `cb8046a`, 2026-06-26). Eksport CSV per kurs i per kampania, agregaty B3/B4, bez raw eventów/sesji/PII; przyciski w UI zachowują filtry.
- **B6 — wykres trendu dziennego + dzienny CSV:** ✅ wdrożone produkcyjnie (`pneadm` `5a2e2b5`, 2026-06-26).
- **Przycisk „Przelicz porzucenia”:** ✅ wdrożone produkcyjnie (`pneadm` `69d6e83`, 2026-06-26).
- **Predefiniowane zakresy dat (oba dashboardy):** ✅ wdrożone produkcyjnie (`pneadm` `9f9fd23`, 2026-06-26).
- **Healthcheck `analytics:abandonment-healthcheck`:** ✅ wdrożone produkcyjnie (`pneadm` `6608791`, 2026-06-26).
- **Porównanie okres-do-okresu (oba dashboardy):** ✅ wdrożone produkcyjnie (`pneadm` `5526e96`, 2026-06-26). Delty KPI vs poprzedni okres o tej samej długości.

## Form v2 + 2F + B4+ — wdrożone produkcyjnie (2026-07-09)

Runbook: [`docs/deploy/2026-07-B4-order-form-funnel-production-deploy.md`](deploy/2026-07-B4-order-form-funnel-production-deploy.md).

### pnedu (`bc6deca`)

- Form v2 eventy (`form_visible`, `form_first_interaction`, …), GUS tracking (`GusAnalyticsTracker`).
- **2F:** `TrafficChannelClassifier`, `OrderFormAttributionService`, zapis do `order_form_attributions` (connection `analytics`).
- Tracking JS = inline Blade — **npm nie wymagany** na prod do analityki formularza.
- Deploy prod: `git pull` + cache + `queue:restart` (wykonane).

### pneadm (`5d08134` … `cb4d732`)

- **B4+:** 5 tabel dziennych, `analytics:aggregate-order-forms`, dashboard `analytics.order-form-funnels.index`, CSV, healthcheck.
- Migracje + hotfix `tracking_schema_version` (`c18bb0a`).
- `pre_attribution_historical` dla dni przed `2026-07-09` (`7acdb69`).
- Dashboard `/`: fix pollingu po usunięciu zamówienia (`33ab603`); „Aktywni teraz” — UTM/direct w Wejściu (`cb4d732`).
- **B3 abandonments bez zmian** (osobna komenda i tabele).
- Testy lokalne przed push: `AnalyticsOrderFormFunnel*` — 25 passed.

### Produkcja — wykonane

- Backfill B4: `--from=2026-06-25 --to=2026-07-08` (14 dni; pierwszy event v2 = 2026-06-25).
- Backfill B3/R1: `--from=2026-06-20 --to=2026-07-08` (19 dni).
- Cron **03:45** `analytics:aggregate-order-forms` — OK.

### Obserwacja po wdrożeniu (nie blokery)

- Healthcheck na dniach **przed 09.07** — `pre_attribution_historical` / niski score: **oczekiwane**.
- Od **09.07** oczekuj rosnącej `attribution_coverage` i kanałów w dashboardzie B4.
- Ręczna weryfikacja: `php artisan analytics:order-form-funnel-healthcheck --from=2026-07-09 --to=2026-07-08` (wczoraj względem dnia uruchomienia — podstaw aktualną datę).

### Dokumentacja

- Spec: `docs/analytics/STAGE_B4_ORDER_FORM_FUNNEL_AGGREGATES.md`
- Deploy: `docs/deploy/2026-07-B4-order-form-funnel-production-deploy.md`

## Form v2 + B4+ agregaty lejka (2026-07-09, lokalnie) — ARCHIWUM

> Sekcja zastąpiona przez „wdrożone produkcyjnie” powyżej. Zachowana dla historii PRzed prod.

- **2F traffic_channel / atrybucja (`pnedu`):** `TrafficChannelClassifier`, `OrderFormAttributionService`, tabela `order_form_attributions`, touch model, `conversion_reporting_channel`. Testy: 25 passed.
- **B4+ agregaty lejka (`pneadm`):** 5 tabel dziennych na `pne_analytics`, komenda `analytics:aggregate-order-forms`, dashboard `analytics.order-form-funnels.index`, CSV AI-safe. **B3 abandonments bez zmian.** Testy: `AnalyticsOrderFormFunnelAggregationTest` — 20 passed.

## Walidacja produkcyjna B2/B3 (2026-06-26)

- **Komenda `analytics:abandonment-healthcheck`** (read-only): sprawdza dopływ eventów lejka (w tym JS z B2), spójność kubełków B3 (`sessions_total == suma kubełków`, FAILURE gdy różnica ≠ 0) oraz kształt lejka + „ciemną strefę”. Opcje `--days` (domyślnie 7) lub `--from/--to`. Nic nie zapisuje.
- **Wynik walidacji na kopii produkcji (25–26.06):**
  - B3 spójność: ✅ `sesje=38 = kubełki=38, różnica=0`.
  - Backend spójny: `proba=7 = zamówienia=7`, `viewed=41`.
  - JS (B2) z 25.06 niski (`start=1–3`, `klik_submit=0`) — **oczekiwane**, bo B2 wdrożono 25.06; sesje sprzed wdrożenia wpadają do `viewed_not_started` (73,7%). 26.06 (po B2) `start=2`/`viewed=3` → JS działa.
  - **Do potwierdzenia za kilka dni** na danych czysto po-B2: czy `submit_clicked` zaczyna się pojawiać na realnym ruchu.
- **Wynik healthcheck na produkcji (26.06, `--days=14`):**
  - B3 spójność: ✅ 25.06 `38=38`, 26.06 `58=58`.
  - `submit_clicked=2` na 26.06 — end-to-end JS↔BE działa post-B2.
  - Prod HEAD: `5526e96` (potwierdzone `git log -1` na srv66127@h30).
- Użycie na produkcji: `php artisan analytics:abandonment-healthcheck --days=14`.

## Etap B — zamknięty (2026-06-26)

Cały Etap B (B1–B6 + recompute + presety + healthcheck + porównanie okresów) jest **wdrożony produkcyjnie**. Prod `pneadm` HEAD: `5526e96`.

**Następne etapy (do decyzji właściciela, poza obecnym wdrożeniem):**
- progi alertów na dashboardach (po kilku tygodniach obserwacji),
- ~~agregaty rozliczeń płatności (online paid + invoiced)~~ **R1 WDROŻONE lokalnie (2026-06-26)** — `analytics:aggregate-revenue` + tabele `analytics_daily_*_revenue_stats`.
- ~~dashboard Rozliczenia~~ **R2 WDROŻONE lokalnie (2026-06-26)** — `Analityka → Rozliczenia` (`/analytics/revenue`). Po deployu R1+R2: migracja, backfill partiami miesięcznymi (od najstarszego eventu do wczoraj), cron 03:30. Następne: **R3** CSV AI-safe, **R4** `submit_intent`/alerty.
- pakiet AI-safe „kopiuj do ChatGPT”,
- re-walidacja healthcheck za 3–5 dni na czystych danych post-B2.

## Do Aktualizacji Po Wdrożeniu

- Odhaczyć wykonane kroki.
- Dopisać decyzje właściciela.
- Dopisać linki do wdrożonych migracji i klas.
- Dopisać rekomendację kolejnego etapu po Etapie 1.
