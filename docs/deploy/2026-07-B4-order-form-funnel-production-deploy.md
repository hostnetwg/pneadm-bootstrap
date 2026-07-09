# Wdrożenie produkcyjne — Form v2 / 2F / B4+ lejek formularza (lipiec 2026)

Status: **wdrożone produkcyjnie** (2026-07-09).  
Prod HEAD `pneadm`: `cb4d732`. Prod HEAD `pnedu`: `bc6deca`.

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

- **Pierwszy event v2** w prod (SQL na `analytics_events`): **2026-06-25**.
- **Backfill B4 wykonany:** `2026-06-25` … `2026-07-08` → 14 dni, 295 wierszy kurs×kanał, 395 kampanii.
- **Pełna atrybucja 2F** (`order_form_attributions`) od wdrożenia **2026-07-09** — dni wcześniejsze mają `pre_attribution_historical` (oczekiwane, nie awaria).
- Config: `analytics.order_form_funnel.attribution_deployed_at` = `2026-07-09` (env: `ANALYTICS_ORDER_FORM_ATTRIBUTION_DEPLOYED_AT`).

### Komendy (placeholdery dat — używaj `YYYY-MM-DD`)

```bash
# backfill B4 (od pierwszego eventu v2 do wczoraj)
php artisan analytics:aggregate-order-forms --from=2026-06-25 --to=2026-07-08 --rebuild

# dzień bieżący po zebraniu ruchu z 2F
php artisan analytics:aggregate-order-forms --date=2026-07-09 --rebuild

# healthcheck (dni z pełną atrybucją — od 09.07)
php artisan analytics:order-form-funnel-healthcheck --from=2026-07-09 --to=2026-07-09
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

### Interpretacja healthcheck po backfillu historycznym

CRITICAL na dniach **przed 2026-07-09** (0% atrybucji, `backend_only`) jest **oczekiwane** — brak retroaktywnej tabeli `order_form_attributions`. Po `7acdb69` healthcheck oznacza te dni jako `pre_attribution_historical` (INFO).

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
| Wykres / ostatnie FORM nie odświeżały się po usunięciu zamówienia | `33ab603` — polling sekcji przy każdej zmianie statystyk |
| Kreseczki w kolumnie Wejście | `cb4d732` — fallback UTM / direct |

---

## 8. Smoke test po deployu

- [ ] `Analityka → Lejek formularza (kanały)` — dane od 25.06, baner historyczny jeśli filtr obejmuje dni przed 09.07.
- [ ] Formularz na pnedu.pl — eventy v2 w `/analytics/debug-events`.
- [ ] `php artisan analytics:order-form-funnel-healthcheck --days=1` (po 09.07, po zebraniu ruchu).
- [ ] Dashboard `/` — usunięcie testowego zamówienia odświeża wykres i tabelę (max ~15 s).
- [ ] `SELECT COUNT(*) FROM order_form_attributions WHERE created_at >= '2026-07-09'` — rośnie po ruchu na formularzu.

---

## 9. Następne kroki (operacyjne)

- Obserwacja metryk B4 od **2026-07-09** (kanały, atrybucja) — min. 3–7 dni.
- Przeliczenie wczoraj codziennie przez cron 03:45; ręcznie tylko przy incydencie.
- Backlog: progi alertów, grupowanie podejrzanych sesji live, ewentualne rozszerzenie kolumny Wejście o `utm_source` w UI debug.
