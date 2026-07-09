# Wdrożenie produkcyjne — Form v2 / 2F / B4+ lejek formularza (lipiec 2026)

Status: **wdrożone produkcyjnie** (2026-07-09).  
Prod HEAD `pneadm`: `39a1079` (weryfikacja: skrypt `docs/deploy/scripts/prod-b4-2f-post-deploy-verify.sh`). Prod HEAD `pnedu`: `bc6deca`.

> Bez sekretów. Ścieżki serwera: `~/domains/adm.pnedu.pl/pneadm`, `~/domains/pnedu.pl/app`.

Powiązane: [`STAGE_B4_ORDER_FORM_FUNNEL_AGGREGATES.md`](../analytics/STAGE_B4_ORDER_FORM_FUNNEL_AGGREGATES.md), [`TRACKING_IMPLEMENTATION_PLAN.md`](../analytics/TRACKING_IMPLEMENTATION_PLAN.md), [`NEXT_STEPS.md`](../NEXT_STEPS.md).

---

## 1. Zakres wdrożenia

| Projekt | Commit | Co |
|---------|--------|-----|
| **pneadm** | `5d08134` | B4+ agregaty lejka, dashboard, healthcheck, migracje, cron 03:45 |
| **pneadm** | `c18bb0a` | hotfix: kolumna `tracking_schema_version` w `analytics_daily_course_channel_funnels` |
| **pneadm** | `7acdb69` | status `pre_attribution_historical`, baner na dashboardzie B4, healthcheck bez fałszywych CRITICAL |
| **pneadm** | `33ab603` | fix pollingu dashboardu zamówień (wykres + ostatnie FORM po usunięciu zamówienia) |
| **pneadm** | `cb4d732` | kolumna Wejście w „Aktywni teraz”: UTM lub `direct (bezpośrednio)` zamiast `—` |
| **pnedu** | `bc6deca` | form v2 eventy, GUS tracking, `TrafficChannelClassifier`, `OrderFormAttributionService` (2F) |

**Nie zmieniano:** UI/copy formularza poza trackingiem, logika zamówień, B3 abandonments (osobna komenda).

---

## 2. Kolejność wdrożenia (wykonana)

1. `pneadm`: `git pull` → `php artisan migrate --force` → cache (`optimize:clear`, `config:cache`, `route:cache`, `view:cache`).
2. `pnedu`: `git pull` → cache → `php artisan queue:restart`.
3. Backfill B4: `php artisan analytics:aggregate-order-forms --from=2026-06-25 --to=2026-07-08 --rebuild`.
4. Opcjonalny backfill B3/R1: `--from=2026-06-20 --to=2026-07-08 --force`.
5. Cron **03:45** `analytics:aggregate-order-forms` — potwierdzony w panelu hostingu.
6. Po hotfixach: ponowny `git pull` + `migrate --force` (jeśli migracja) + `view:cache`.

### pnedu — `npm` na produkcji

`npm ci` / `npm run build` **nie są wymagane** do trackingu formularza B2/2D/2F — collector jest **inline w Blade** (`order-form-client-tracking.blade.php`).  
`npm` potrzebny tylko przy zmianach w globalnym bundlu Vite (`public/build`).

---

## 3. Migracje (`pne_analytics`, projekt `pneadm`)

| Migracja | Opis |
|----------|------|
| `2026_07_09_120000` | `order_form_attributions` |
| `2026_07_09_140000` | 5 tabel `analytics_daily_*_funnels` |
| `2026_07_09_150000` | metryki submit grace + data quality score |
| `2026_07_09_160000` | naprawa brakujących tabel B3/R1 (jeśli potrzeba) |
| `2026_07_09_170000` | hotfix `tracking_schema_version` na `course_channel_funnels` |

---

## 4. Backfill i daty

**Ważne — dwie różne daty (nie mylić):**

| Pojęcie | Data prod (zweryfikowane 2026-07-09) | Znaczenie |
|---------|--------------------------------------|-----------|
| **Pierwszy event lejka formularza (B1/B2)** | **2026-06-25** | `order_form_viewed` / `order_form_started` itd. — podstawa **backfillu B4** `--from` |
| **Pierwszy event schema v2** (`tracking_schema_version=2` w `metadata`) | **2026-07-09 ~14:11:15** | `form_section_viewed` (pnedu `bc6deca`) — pełna taksonomia v2 + GUS |
| **Pełna atrybucja 2F** (`order_form_attributions`) | **2026-07-09** od ~16:11 | `TrafficChannelClassifier` — dni wcześniejsze: `pre_attribution_historical` |

SQL — pierwszy event **schema v2** (nie B1/B2):

```sql
SELECT id, event_name, occurred_at, order_form_session_id, course_id,
       JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.tracking_schema_version')) AS schema_v
FROM analytics_events
WHERE JSON_EXTRACT(metadata, '$.tracking_schema_version') = 2
   OR event_name IN ('form_visible', 'form_section_viewed', 'gus_lookup_clicked')
ORDER BY occurred_at ASC, id ASC
LIMIT 1;
-- Prod 2026-07-09: form_section_viewed, 2026-07-09 14:11:15, course_id=535
```

- **Backfill B4 wykonany:** `2026-06-25` … `2026-07-08` → 14 dni (pierwszy **dzień lejka** B1/B2, nie schema v2).
- Po deployu 09.07: agregat dnia **09.07** ręcznie — 8 kanałów, 21 kurs×kanał, 25 kampanii, 60 GUS, 1 jakość.
- Config: `analytics.order_form_funnel.attribution_deployed_at` = `2026-07-09` (env: `ANALYTICS_ORDER_FORM_ATTRIBUTION_DEPLOYED_AT`).

### Komendy (placeholdery dat — używaj `YYYY-MM-DD`)

```bash
# backfill B4 (od pierwszego DNIA lejka B1/B2, nie od schema v2)
php artisan analytics:aggregate-order-forms --from=2026-06-25 --to=2026-07-08 --rebuild

# dzień po zebraniu ruchu z 2F / v2 (np. dzień po deployu)
php artisan analytics:aggregate-order-forms --date=2026-07-09 --rebuild

# healthcheck — dni z pełną atrybucją (od 09.07); pierwszy pełny dzień v2: oceniaj od 10.07
php artisan analytics:order-form-funnel-healthcheck --from=2026-07-10 --to=2026-07-10
```

**Uwaga:** nie wklejaj literałów `FIRST_V2`, `YESTERDAY`, `...` — to były przykłady; Artisan oczekuje prawdziwych dat.

---

## 5. Crony analityki (prod, zweryfikowane)

| Czas (Europe/Warsaw) | Komenda |
|--------------------|---------|
| 02:15 | `analytics:aggregate-daily` |
| 03:15 | `analytics:aggregate-abandonments` |
| 03:30 | `analytics:aggregate-revenue` |
| 03:45 | `analytics:aggregate-order-forms` |

Log opcjonalny: `storage/logs/analytics-order-forms.log` (po pierwszym udanym uruchomieniu).

---

## 6. UI po wdrożeniu

| Miejsce | Route / opis |
|---------|----------------|
| Lejek formularza (kanały) | `/analytics/order-form-funnels` — agregaty B4+ |
| Dashboard zamówień | `/` — karty operacyjne + „Aktywni teraz” (live z `analytics_events`) |
| Debug eventów | `/analytics/debug-events` |

### Interpretacja healthcheck

| Okres | Status healthcheck | Działanie |
|-------|-------------------|-----------|
| **Przed 2026-07-09** | `pre_attribution_historical` (INFO) | Oczekiwane — brak retroaktywnej 2F (`7acdb69`) |
| **2026-07-09** (dzień deployu) | `warmup_or_deploy_window` + flaga `attribution_deploy_window` | **Nie traktuj 80% `orders_without_attribution` jako błąd kodu** — mieszany dzień (schema v2 od ~14:11, atrybucja od ~16:11). Healthcheck pomija twarde progi. |
| **Od 2026-07-10** | Normalne progi | Pierwszy **pełny** dzień schema v2 + 2F |

**Ręczna ocena zamówień w dniu deployu** — tylko po starcie 2F (~16:15 Europe/Warsaw):

```sql
SELECT COUNT(*) FROM order_form_attributions
WHERE created_at >= '2026-07-09 16:15:00';

-- porównaj z zamówieniami z tego samego okna (pneadm / analytics_events form_order_created)
```

**`direct` ~91% w pierwszej próbce atrybucji (09.07)** — **nie jest jeszcze problemem**: `direct` to poprawnie sklasyfikowany kanał; próbka może być testowa / bez UTM.

**Pełna ocena operacyjna:** dane **`2026-07-10`** po cronie **`2026-07-12 03:45`** (`aggregation_lag_days=2` → automatyczny dzień statystyki = D−2).

```bash
php artisan analytics:order-form-funnel-healthcheck --from=2026-07-10 --to=2026-07-10
```

Zweryfikowane na prod (09.07 ~17:06): pierwszy schema v2 **`form_section_viewed` 14:11:15**; `order_form_attributions` **215** (16:10–18:57); healthcheck 25.06–08.07 = OK; po poprawce kodu 09.07 → `warmup_or_deploy_window`.

### Dashboard „Aktywni teraz”

- Źródło: ostatnie eventy lejka z `analytics_events` (okno ~30 min), nie agregaty B4.
- Kolumna **Wejście:** referrer → kampania → UTM → `direct (bezpośrednio)`.
- Wiele sesji `direct` na stary kurs w krótkim czasie — często bot/test/brak ciasteczka sesji, niekoniecznie wielu ludzi.

---

## 7. Znane problemy i hotfixy

| Problem | Rozwiązanie |
|---------|-------------|
| `Unknown column tracking_schema_version` przy agregacji | `c18bb0a` + migracja `170000` |
| Healthcheck CRITICAL na dniach sprzed 09.07 | `7acdb69` — `pre_attribution_historical` |
| Healthcheck CRITICAL w dniu deployu 09.07 | `ff137b1` — `attribution_deployed_at` → `warmup_or_deploy_window` |
| Wykres / ostatnie FORM nie odświeżały się po usunięciu zamówienia | `33ab603` — polling sekcji przy każdej zmianie statystyk |
| Kreseczki w kolumnie Wejście | `cb4d732` — fallback UTM / direct |

### Deploy hotfix `ff137b1` (warmup dnia atrybucji 2F)

**Brak migracji** — wystarczy `git pull` + cache.

```bash
cd ~/domains/adm.pnedu.pl/pneadm
git pull
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
# opcjonalnie (bezpieczne): php artisan migrate --force

# opcjonalnie — status w analytics_daily_data_quality zgodny z healthcheckiem:
php artisan analytics:aggregate-order-forms --date=2026-07-09 --rebuild

php artisan analytics:order-form-funnel-healthcheck --from=2026-07-09 --to=2026-07-09
```

**Oczekiwany healthcheck dla 2026-07-09:**
- Status: `warmup_or_deploy_window`
- Flaga: `attribution_deploy_window` (w evaluatorze; w tabeli UI po rebuild agregatu)
- Skipped: `tak`
- WERDYKT: **OK** (brak CRITICAL z powodu niskiego attribution / v2 / orders_without_attribution)

---
| Kreseczki w kolumnie Wejście | `cb4d732` — fallback UTM / direct |

---

## 8. Smoke test po deployu

- [x] `Analityka → Lejek formularza (kanały)` — dane od 25.06 (lejek B1/B2), baner historyczny przed 09.07.
- [x] Formularz na pnedu.pl — eventy schema v2 w `/analytics/debug-events` (prod: od 09.07 ~14:11).
- [x] `order_form_attributions` rośnie po ruchu (prod 09.07: 215 rekordów).
- [ ] Healthcheck **pełnego dnia v2** — **`2026-07-10`** po cronie **`2026-07-12 03:45`** (09.07 = warmup deployu).
- [ ] Dashboard `/` — usunięcie testowego zamówienia odświeża wykres (max ~15 s).

Weryfikacja automatyczna (SSH):

```bash
cd ~/domains/adm.pnedu.pl/pneadm && git pull
export PHP_BIN=/opt/alt/php82/usr/bin/php
bash docs/deploy/scripts/prod-b4-2f-post-deploy-verify.sh 2>&1 | tee ~/b4-2f-verify.log
```

---

## 9. Następne kroki (operacyjne)

- Obserwacja metryk B4 od **2026-07-10** (pierwszy pełny dzień schema v2 + 2F) — min. 3–7 dni.
- Przeliczenie wczoraj codziennie przez cron 03:45; ręcznie tylko przy incydencie.
- **Pełna ocena:** healthcheck **`2026-07-10`** po cronie **`2026-07-12 03:45`** (`aggregation_lag_days=2`).
- Backlog: grupowanie sesji live, rozszerzenie kolumny Wejście.
