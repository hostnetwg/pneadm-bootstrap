# Etap R — Agregaty rozliczeń płatności/faktur (PLAN, bez kodu)

Data utworzenia: 2026-06-26
Status: **R1 WDROŻONE lokalnie** (2026-06-26) — agregaty, komenda, migracje, modele, serwis, testy. Dashboard (R2), CSV (R3), alerty/submit_intent (R4) pozostają poza zakresem R1.
Powiązane: [ADR-005](../decisions/ADR-005-invoice-number-means-invoiced-not-paid.md), [STAGE_B_CLIENT_TRACKING.md](./STAGE_B_CLIENT_TRACKING.md), [DATABASE_SCHEMA_PLAN.md](./DATABASE_SCHEMA_PLAN.md).

> ## R1 — stan wdrożenia (2026-06-26)
>
> Wdrożone w `pneadm` (model dat = data eventu, strefa Europe/Warsaw, zero PII):
> - Migracja `2026_06_26_120000_create_analytics_daily_revenue_stats_tables.php` (connection `analytics`): tabele `analytics_daily_course_revenue_stats`, `analytics_daily_campaign_revenue_stats`.
> - Modele `App\Models\Analytics\AnalyticsDailyCourseRevenueStat`, `AnalyticsDailyCampaignRevenueStat`.
> - Serwis `App\Services\Analytics\AnalyticsRevenueAggregationService` (`aggregateForDate`, `aggregateForDateRange`, idempotencja delete+insert, batch lookup `FormOrder`).
> - Komenda `analytics:aggregate-revenue` (`--date` / `--from` `--to` / `--force`; domyślnie wczoraj, lag=1).
> - Config `config/analytics.php` → sekcja `revenue` (`timezone`, `aggregation_lag_days`).
> - Testy: `tests/Feature/AnalyticsRevenueAggregationTest.php` (26 testów, zielone).
> - **Metryki**: `orders_created`, `ordered_revenue_gross`, `online_paid_orders`, `online_paid_revenue_gross`, `deferred_invoiced_orders`, `deferred_invoiced_revenue_gross`, `online_invoiced_marker_orders`, `settled_orders_total`, `settled_revenue_gross`.
> - **Settled** = `online_paid` + `deferred_invoiced` (faktura online = tylko `online_invoiced_marker_orders`, NIE wchodzi do settled → brak double-count).
> - **Atrybucja kampanii**: `campaign_code` z eventu → fallback `FormOrder.fb_source` → `campaign_id` z `marketing_campaigns` (fail-safe). Brak kampanii → tylko liczniki diagnostyczne `*_without_campaign` w tabeli kursów.
> - **Backfill** nieuruchomiony (instrukcja w sekcji deploy). **Cron 03:30** nie wdrożony produkcyjnie (instrukcja przygotowana). **Dashboard `Analityka → Rozliczenia`** dopiero w R2.

Ten etap następuje po zamkniętym pakiecie B (JS tracking, porzucenia, dashboard porzuceń, CSV AI-safe, wykres trendu, presety, healthcheck, porównanie okresów).

---

## 0. Cel biznesowy

Pokazać realny wymiar sprzedażowo-finansowy kampanii i kursów: ile zamówiono, ile opłacono online, ile zafakturowano (odroczone), jaka jest wartość zamówiona vs rozliczona, oraz **które kursy/kampanie realnie dowożą pieniądze**, a nie tylko wejścia i formularze.

Semantyka źródeł prawdy (z ADR-005):

```text
online    → payment_status_changed z payment_status=paid  (źródło prawdy: bramka PayU/PayNow)
odroczone → invoice_created z order_flow=deferred          (rozliczenie operacyjne = pierwszy invoice_number)
settled_orders_total = online_paid + deferred_invoiced     (NIE online_paid + all_invoice_created)
```

---

## 1. Stan obecny (audyt kodu, 2026-06-26)

### 1.1 Eventy źródłowe (gdzie i z jakim payloadem)

| Event | Emisja | Kwota | order_flow | campaign_code w evencie? |
|-------|--------|-------|------------|--------------------------|
| `form_order_created` | **pnedu** `BackendAnalyticsTracker::trackFormOrderCreated()` | `metadata.amount_gross` (z `FormOrder.product_price`) | `metadata.order_flow` (`deferred`/`online`) | **TAK** (z atrybucji requestu) |
| `payment_status_changed` | **pnedu** `BackendAnalyticsTracker::trackPaymentStatusChanged()` (PayU/PayNow notify + return sync) | `metadata.amount_gross` (= `OnlinePaymentOrder.total_amount`) | `metadata.order_flow = 'online'`, `metadata.payment_status` | **NIE** |
| `invoice_created` | **pneadm** `InvoiceAnalyticsTracker` (observer `FormOrderObserver`) | `metadata.amount_gross` (z `FormOrder.product_price`) | `metadata.order_flow` z `FormOrder.payment_mode` | **NIE** |

Kluczowe fakty:
- **Kwoty są w `metadata` (JSON)**, nie w osobnej kolumnie. `analytics_events` nie persystuje `amount_snapshot` jako kolumny — agregacja musi czytać `JSON_EXTRACT(metadata, '$.amount_gross')`.
- `payment_status_changed` i `invoice_created` **nie mają `campaign_code`** → atrybucja kampanii wymaga joinu po `form_order_id` → `FormOrder.fb_source`.
- `payment_status` (znormalizowany): `paid`, `pending`, `canceled`, `failed`, `expired`, `created`, `unknown`. Tylko `paid` liczymy jako opłacone online.
- `form_order_created` emitowane **tylko z pnedu** — ręczne zamówienia z panelu `pneadm` (`submission_source = pnedu_manual`) **nie emitują** tego eventu (luka — patrz Ryzyka).

### 1.2 Istniejące agregaty i ich wzorzec

- `AnalyticsDailyAggregationService` (lejek): czyta `analytics_events` w granicach dnia (timezone `Europe/Warsaw` → UTC), grupuje per `course_id` i per `campaign_code`, idempotencja **delete+insert** per `stat_date`. Atrybucja kampanii lejka = **per-event** (każdy event z `campaign_code`).
- `AnalyticsAbandonmentAggregationService` (porzucenia): atrybucja **first-touch** per sesja, lag=2.
- Komendy: `analytics:aggregate-daily`, `analytics:aggregate-abandonments` — opcje `--date` / `--from` / `--to` / `--force`; domyślny dzień (wczoraj lub z lagiem).
- Tabele agregatów używają `Schema::connection('analytics')` (baza `pne_analytics`).

### 1.3 Placeholdery do uwzględnienia

`analytics_daily_course_stats` i `analytics_daily_campaign_stats` mają już kolumny `paid_orders`, `invoiced_orders`, `payment_orders_created`, `revenue_snapshot` — ale **tylko** `orders_created` + `revenue_snapshot` (z `form_order_created.amount_gross`) są wypełniane. Pozostałe są zawsze `0`. Nie mają osobnych kolumn na przychód opłacony/zafakturowany ani osobnych dat rozliczenia.

---

## 2. Decyzja architektoniczna: osobne tabele rozliczeń

**Rekomendacja: nowe, dedykowane tabele rozliczeń** (nie rozszerzać `analytics_daily_*_stats`).

Powód:
- Rozliczenia operują na **innych datach** niż lejek (data zamówienia ≠ data opłacenia ≠ data faktury). Mieszanie w jednej tabeli lejka groziłoby pomyłką interpretacyjną.
- Rozdzielenie pozwala niezależnie liczyć i recompute'ować rozliczenia bez ruszania lejka.
- Stare placeholdery `paid_orders`/`invoiced_orders` w lejku zostają `0` (kompatybilność), a prawda o rozliczeniach żyje w nowych tabelach.

Proponowane nazwy (zgodne ze stylem):

```text
analytics_daily_course_revenue_stats
analytics_daily_campaign_revenue_stats
```

---

## 3. Model dat (kluczowe)

Jedna semantyka: **każda metryka liczy eventy, które WYDARZYŁY SIĘ danego `stat_date`** (w `Europe/Warsaw`). W jednym wierszu `(stat_date, course_id)` współistnieją:

| Metryka | Liczona wg daty eventu |
|---------|------------------------|
| `orders_created`, `ordered_revenue_gross` | data `form_order_created` (= data zamówienia) |
| `online_paid_orders`, `online_paid_revenue_gross` | data `payment_status_changed:paid` (= data opłacenia) |
| `deferred_invoiced_orders`, `deferred_invoiced_revenue_gross` | data `invoice_created` (= data faktury) |

Konsekwencja: wiersz dnia X to „co się zdarzyło dnia X”, a nie „losy zamówień z dnia X”. Dashboard **musi** to jasno opisać:

```text
Zamówione  — wg daty zamówienia
Rozliczone — wg daty płatności/faktury
```

Nie sumować „zamówione” i „rozliczone” jako jednego lejka bez etykiety — to różne kohorty czasowe.

---

## 4. Metryki docelowe

```text
orders_created                    = liczba form_order_created (occurred_at = stat_date)
ordered_revenue_gross             = SUM(metadata.amount_gross) z form_order_created
online_paid_orders                = liczba payment_status_changed gdzie payment_status=paid AND order_flow=online
online_paid_revenue_gross         = SUM(metadata.amount_gross) z online_paid
deferred_invoiced_orders          = liczba invoice_created gdzie order_flow=deferred
deferred_invoiced_revenue_gross   = SUM(metadata.amount_gross) z invoice_created (order_flow=deferred)
settled_orders_total              = online_paid_orders + deferred_invoiced_orders
settled_revenue_gross             = online_paid_revenue_gross + deferred_invoiced_revenue_gross
```

Dodatkowo (informacyjne, NIE wliczane do settled — żeby uniknąć double-count):

```text
online_invoiced_marker_orders     = liczba invoice_created gdzie order_flow=online  (tylko znacznik księgowy)
```

Reguła anty-double-count: `invoice_created` z `order_flow=online` **nigdy** nie wchodzi do `deferred_invoiced_*` ani do `settled_*`. Online liczone wyłącznie z bramki (`payment_status_changed:paid`).

---

## 5. Proponowane tabele (do akceptacji, migracja w `pneadm`, connection `analytics`)

### 5.1 `analytics_daily_course_revenue_stats`

| Kolumna | Typ | Uwagi |
|---------|-----|-------|
| `id` | bigIncrements | |
| `stat_date` | date, INDEX | |
| `course_id` | unsignedBigInteger, INDEX | |
| `course_title_snapshot` | string(255) nullable | |
| `orders_created` | unsignedInteger default 0 | |
| `ordered_revenue_gross` | decimal(12,2) default 0 | |
| `online_paid_orders` | unsignedInteger default 0 | |
| `online_paid_revenue_gross` | decimal(12,2) default 0 | |
| `deferred_invoiced_orders` | unsignedInteger default 0 | |
| `deferred_invoiced_revenue_gross` | decimal(12,2) default 0 | |
| `online_invoiced_marker_orders` | unsignedInteger default 0 | informacyjne |
| `settled_orders_total` | unsignedInteger default 0 | = online_paid + deferred_invoiced |
| `settled_revenue_gross` | decimal(12,2) default 0 | |
| `created_at`, `updated_at` | timestamps | |
| **UNIQUE** | `['stat_date','course_id']` | |
| **INDEX** | `['course_id','stat_date']` | |

### 5.2 `analytics_daily_campaign_revenue_stats`

Jak wyżej, zamiast `course_*`:

| Kolumna | Typ | Uwagi |
|---------|-----|-------|
| `campaign_code` | string(100), INDEX | |
| `campaign_id` | unsignedBigInteger nullable, INDEX | |
| (te same kolumny metryk co 5.1) | | |
| **UNIQUE** | `['stat_date','campaign_code']` | |
| **INDEX** | `['campaign_code','stat_date']`, `['campaign_id','stat_date']` | |

> `settled_orders_total` i `settled_revenue_gross` można trzymać jako kolumny zmaterializowane (liczone przy agregacji) — prościej dla dashboardu i CSV, kosztem minimalnej redundancji. Rekomendacja: materializować.

---

## 6. Mapowanie eventów → metryki (logika agregacji)

```text
form_order_created:
    course: orders_created++; ordered_revenue_gross += amount_gross
    campaign(code z eventu): to samo

payment_status_changed (payment_status=paid, order_flow=online):
    course: online_paid_orders++; online_paid_revenue_gross += amount_gross
    campaign(code z FormOrder.fb_source via form_order_id): to samo

invoice_created (order_flow=deferred):
    course: deferred_invoiced_orders++; deferred_invoiced_revenue_gross += amount_gross
    campaign(code z FormOrder.fb_source via form_order_id): to samo

invoice_created (order_flow=online):
    course: online_invoiced_marker_orders++   (NIE settled)

po zsumowaniu:
    settled_orders_total  = online_paid_orders + deferred_invoiced_orders
    settled_revenue_gross = online_paid_revenue_gross + deferred_invoiced_revenue_gross
```

Kwota: `(float) JSON_EXTRACT(metadata,'$.amount_gross')`; `null`/brak → `0` (nie przerywać).

---

## 7. Atrybucja kampanii dla rozliczeń (ważne)

Problem: `payment_status_changed` i `invoice_created` **nie niosą** `campaign_code`.

**Rekomendacja:** w czasie agregacji rozwiązywać kampanię po `form_order_id` → `FormOrder.fb_source` (to jest `MarketingCampaign.campaign_code`).

Konsekwencje:
- Jest to atrybucja **order-time** (kampania przypisana do zamówienia), spójna i deterministyczna.
- Wymaga odczytu `pneadm.form_orders` podczas agregacji (cross-connection: agregaty w `analytics`, `form_orders` w `mysql`/`pneadm`). To odczyt tylko do `id`, `fb_source`, `product_id` — **bez PII**.
- Eventy rozliczeń bez `form_order_id` lub bez `FormOrder.fb_source` → trafiają **tylko** do statystyk per kurs, **nie** do per kampania (tak jak w lejku: brak kampanii = brak wiersza kampanii).
- `campaign_id` rozwiązywać przez `MarketingCampaign` po `campaign_code` (jak w dashboardzie porzuceń).

Alternatywa (odrzucona): dodać `campaign_code` do eventów rozliczeniowych w pnedu/pneadm — wymaga zmian w emisji eventów (poza zakresem etapu R; rozważyć osobno, bo backfill historyczny i tak wymagałby joinu po FormOrder).

---

## 8. RODO

Agregaty NIE zawierają: email, telefon, NIP, nazwa nabywcy/szkoły, adres, dane uczestników, `invoice_number`, `form_order_id`, `payment_order_id`, `analytics_session_id`, `order_form_session_id`, raw metadata/event/request/gateway payload.

Dozwolone: `stat_date`, `course_id`, `course_title_snapshot`, `campaign_code`, `campaign_id`, liczniki, sumy kwot, rates. Kwoty dozwolone wyłącznie jako **agregaty** (sumy dzienne), nie jako rekordy jednostkowe.

Odczyt `FormOrder.fb_source`/`product_id` w agregacji służy wyłącznie do mapowania kampanii/kursu i **nie jest zapisywany** poza zagregowanymi licznikami.

---

## 9. Poza zakresem etapu R (świadomie)

```text
bank_payment_confirmed / rekonsyliacja przelewów bankowych
częściowe płatności, zaliczki, faktury zaliczkowe
korekty faktur, anulowanie faktur
zwroty online, chargebacki
wiele faktur do jednego zamówienia
KSeF, iFirma tracking (poza istniejącym invoice_path_type)
ręczne zamówienia z pneadm bez form_order_created (luka emisji — patrz Ryzyka)
```

---

## 10. Dashboard

**Rekomendacja: osobny, prosty dashboard read-only `Analityka → Rozliczenia`** (`analytics.revenue.index`), nie rozszerzanie lejka. Powód: nie mieszać lejka wejść/formularza z finansami i różnymi datami rozliczenia.

MVP:
1. Kafelki summary: `Zamówione` (liczba + kwota), `Opłacone online`, `Zafakturowane odroczone`, `Rozliczone łącznie`.
2. Tabela per kurs.
3. Tabela per kampania.
4. Filtr dat + presety (reużyć `AnalyticsDateRangePresets`).
5. Wyraźny opis różnicy dat: „Zamówione wg daty zamówienia; Rozliczone wg daty płatności/faktury”.
6. (Opcjonalnie później) porównanie okres-do-okresu (reużyć `AnalyticsPeriodComparison`).

Read-only, czyta wyłącznie nowe tabele agregatów; nie skanuje `analytics_events`.

---

## 11. Cron i komenda

Komenda: `analytics:aggregate-revenue` (wzorzec jak `aggregate-daily`).

- Opcje: `--date`, `--from`, `--to`, `--force`.
- Idempotencja: delete+insert per `stat_date` (obie tabele).
- Timezone biznesowy: `Europe/Warsaw`.
- Domyślny zakres: dzień wczorajszy (rozliczenia nie potrzebują lagu JS jak porzucenia; rozważyć lag=1 dla pewności zamknięcia doby). Decyzja: domyślnie wczoraj.
- Harmonogram: cron **po** istniejących agregacjach, np. **03:30 Europe/Warsaw** (po 02:15 lejek i 03:15 porzucenia), `flock`, log do `storage/logs/analytics-aggregate.log`.
- Zero wpływu na formularz/płatności/faktury (czyta tylko eventy + FormOrder lookup).
- Catch-up po wdrożeniu: `--from=<MIN occurred_at> --to=<wczoraj>` (eventy rozliczeniowe istnieją od startu trackingu).

---

## 12. Testy (PR R1)

1. agreguje `form_order_created` → `orders_created`,
2. agreguje `ordered_revenue_gross` (suma `amount_gross`),
3. agreguje `payment_status_changed:paid` (online) → `online_paid_orders`,
4. agreguje `online_paid_revenue_gross`,
5. agreguje `invoice_created` z `order_flow=deferred` → `deferred_invoiced_orders`,
6. **nie** liczy `invoice_created` z `order_flow=online` do `deferred_invoiced` (idzie do `online_invoiced_marker_orders`),
7. `settled_orders_total = online_paid + deferred_invoiced`,
8. **nie dubluje** online paid + online invoice w `settled`,
9. agreguje per kurs,
10. agreguje per kampania (kampania z `FormOrder.fb_source`),
11. idempotencja (delete+insert, ponowne uruchomienie nie dubluje),
12. brak PII w agregatach (asercja kolumn),
13. eventy bez kampanii nie trafiają do campaign stats,
14. kwoty zerowe/null obsłużone bez błędu,
15. statusy inne niż `paid` (`pending`/`failed`/`canceled`/...) nie liczone jako online paid,
16. (dodatkowy) `payment_status_changed:paid` bez `form_order_id` nie trafia do campaign stats, ale liczy się w course stats po `course_id`.

---

## 13. Ryzyka i ograniczenia

| Ryzyko | Ograniczenie |
|--------|--------------|
| Ręczne zamówienia z `pneadm` nie emitują `form_order_created` | `orders_created`/`ordered_revenue` z eventów może być niższe niż liczba `form_orders`. Udokumentować; ewentualna komenda rekonsyliacyjna = osobny etap. |
| `payment_status_changed`/`invoice_created` bez `campaign_code` | Join po `form_order_id` → `FormOrder.fb_source` (cross-connection, bez PII). |
| Kwoty w `metadata` JSON | Czytać `JSON_EXTRACT`; brak/`null` → 0. |
| Online + faktura (double-count) | `invoice_created:online` wyłączone z settled (osobna kolumna marker). |
| Różne daty (zamówienie/płatność/faktura) | Osobne metryki wg daty eventu; dashboard jasno etykietuje. Nie sumować jako jeden lejek. |
| `invoice_created` tylko z zapisów Eloquent | Bezpośrednie `UPDATE` invoice_number poza Eloquent nie wyemitują eventu (znane z ADR-005). |
| Korekty/anulowania faktur, zwroty | Poza zakresem; `invoice_created` idempotentny (jeden na zamówienie). |
| Lookup FormOrder w pętli agregacji | Batch/`whereIn` po `form_order_id` (wydajność), nie N+1. |

---

## 14. Kolejność PR-ów

```text
PR R1 — agregaty rozliczeń: 2 tabele + serwis AnalyticsRevenueAggregationService + komenda analytics:aggregate-revenue + testy (pkt 12). BEZ dashboardu.
PR R2 — dashboard Analityka → Rozliczenia (read-only, kafelki + tabele kurs/kampania + filtr dat + presety + opis dat).
PR R3 — CSV AI-safe rozliczeń (per kurs / per kampania / dzienny), bez PII, rates jako ułamki, BOM UTF-8 (wzorzec B5).
PR R4 — DO DECYZJI: submit_intent (mały etap B) ALBO alerty. Rekomendacja: najpierw submit_intent (porządkuje semantykę lejka), alerty dopiero po 2–3 tyg. czystych danych.
```

---

## 15. Decyzje potrzebne od Waldemara

1. **Nowe tabele** `analytics_daily_*_revenue_stats` vs rozszerzenie istniejących `analytics_daily_*_stats`? (Rekomendacja: nowe tabele.)
2. **Osobny dashboard** `Analityka → Rozliczenia` vs sekcja w lejku? (Rekomendacja: osobny.)
3. **Atrybucja kampanii rozliczeń** przez `FormOrder.fb_source` (order-time) — akceptacja? (Rekomendacja: tak.)
4. **`online_invoiced_marker_orders`** — czy w ogóle zliczać znacznik księgowy (przydatny do kontroli), czy pomijać całkowicie? (Rekomendacja: zliczać, ale nie w settled.)
5. **Materializować `settled_*`** jako kolumny, czy liczyć w locie w dashboardzie? (Rekomendacja: materializować.)
6. **Lag agregacji** rozliczeń: domyślnie wczoraj (lag=1) wystarczy? (Rekomendacja: tak.)
7. **Backfill/catch-up** od początku trackingu po wdrożeniu R1 — zgoda na jednorazowy `--from/--to`?

---

## Załącznik: przyszły mały etap B — `order_form_submit_intent` (NIE teraz)

Obecny `order_form_submit_clicked` odpala się na zdarzeniu `submit` (po walidacji HTML) — działa, ale nazwa jest semantycznie myląca. W przyszłości dodać osobny event `order_form_submit_intent` odpalany przy **kliknięciu** przycisku submit (przed walidacją HTML). Docelowy lejek:

```text
order_form_started
order_form_submit_intent      (klik, przed walidacją)
order_form_submit_clicked     (zdarzenie submit, po walidacji)
order_form_submit_attempted   (backend)
form_order_created            (backend)
```

Tylko odnotowane jako przyszły mały etap. Nie wdrażać teraz.

## Załącznik: alerty — odłożone

Alerty wdrażać dopiero po min. **2–3 tygodniach** czystych danych po B2/B3/B4. Nie ustawiać teraz progów dla `viewed_not_started` (świeże dane JS → naturalnie zawyżone). Docelowy model: baseline → minimalna liczba sesji → alert względny względem mediany → brak alarmów, gdy tracking JS niewiarygodny.
