# Deploy produkcyjny R1+R2 (+R2.1+R3) — Rozliczenia (pneadm / adm.pnedu.pl)

Data przygotowania: 2026-06-26
Status: **R1+R2 wdrożone na produkcji (GO)**. **R2.1+R3 wdrożone na produkcji (GO, 2026-06-26)**. Kod jest już na `origin/main`.
Powiązane: `docs/analytics/STAGE_R_REVENUE_SETTLEMENT_AGGREGATES.md`, `docs/deploy/2026-06-analytics-production-deploy.md` (sekcje 8.6/8.7 — wzorzec cronów).

## Commity do wdrożenia (na `origin/main`)

```text
9d43a91 feat(analytics): add revenue settlements dashboard (R2)
5e225bf feat(analytics): aggregate revenue settlement metrics
```

## Zakres deployu

- **R1**: migracja 2 tabel agregatów + serwis + komenda `analytics:aggregate-revenue`.
- **R2**: dashboard read-only `Analityka → Rozliczenia` (`/analytics/revenue`).
- **Bez** zmian w `pnedu`, **bez** R3 CSV, **bez** nowych eventów, **bez** zmian w płatnościach/fakturach/PayU/PayNow/iFirma/KSeF.

> Realne parametry produkcji (z dotychczasowych deployów):
> - katalog: `/home/srv66127/domains/adm.pnedu.pl/pneadm`
> - PHP CLI: `/opt/alt/php82/usr/bin/php`
> - flock: `/usr/bin/flock`, locki w `/tmp`
> - kolejka: `database` (`failed_jobs`), worker przez cron+flock (sekcja 8.5 starego deploy doc)
> - istniejące crony agregacji: **02:15** `analytics:aggregate-daily`, **03:15** `analytics:aggregate-abandonments`

---

## 1. Wejście na produkcję

```bash
ssh srv66127@h30
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
```

---

## 2. Kontrola przed deployem

```bash
git status
git log --oneline -5
/opt/alt/php82/usr/bin/php artisan --version
```

- `git status` **MUSI** być czysty (`nothing to commit, working tree clean`).
- Jeśli `git status` jest dirty → **STOP**. Nie rób `git pull`. Najpierw wyjaśnij różnice:

```bash
git status
git diff
git stash list
```

  Lokalne zmiany na prod są nieoczekiwane — skonsultuj z Waldemarem przed kontynuacją (ewentualny `git stash` tylko świadomie).

---

## 3. Pobranie kodu

```bash
git pull origin main
git log --oneline -5
```

Po `git pull` HEAD ma zawierać (dwa górne wpisy):

```text
9d43a91 feat(analytics): add revenue settlements dashboard (R2)
5e225bf feat(analytics): aggregate revenue settlement metrics
```

Jeśli ich nie ma → **STOP** (sprawdź `git remote -v`, gałąź `main`, dostęp do origin).

---

## 4. Migracja (baza analityczna `pne_analytics`, connection `analytics`)

R1 tworzy **dwie** tabele przez `Schema::connection('analytics')`. Migracje `pneadm` obejmują obie bazy
(`mysql`/pneadm i `analytics`/pne_analytics) — uruchamiamy standardowo:

```bash
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan migrate:status
```

Migracja `2026_06_26_120000_create_analytics_daily_revenue_stats_tables` ma utworzyć:

```text
analytics_daily_course_revenue_stats
analytics_daily_campaign_revenue_stats
```

Szybka weryfikacja, że tabele istnieją w `pne_analytics`:

```bash
/opt/alt/php82/usr/bin/php artisan tinker --execute="echo 'course=' . (\Illuminate\Support\Facades\Schema::connection('analytics')->hasTable('analytics_daily_course_revenue_stats') ? 'OK' : 'BRAK') . ' campaign=' . (\Illuminate\Support\Facades\Schema::connection('analytics')->hasTable('analytics_daily_campaign_revenue_stats') ? 'OK' : 'BRAK');"
```

> **NIE** uruchamiać żadnych migracji w `pnedu`.

---

## 5. Cache aplikacji + queue restart

Zgodnie z dotychczasowym standardem prod (`pneadm` używał `optimize:clear` po pull):

```bash
/opt/alt/php82/usr/bin/php artisan optimize:clear
```

Opcjonalnie (jeśli Waldemar chce wymusić rebuild cache jak w pełnym deployu):

```bash
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
```

Restart kolejki (bezpieczny standard po deployu; worker z cron+flock i tak złapie nowy kod po `--max-time`):

```bash
/opt/alt/php82/usr/bin/php artisan queue:restart
```

> R1/R2 **nie zmieniają** jobów analitycznych — `queue:restart` jest profilaktyczny.

---

## 6. Backfill R1 (po migracji)

Decyzja Waldemara: **backfill miesięcznymi partiami od najstarszego relewantnego eventu do wczoraj.**

### 6.1. Ustalenie zakresu

```sql
SELECT MIN(occurred_at), MAX(occurred_at), COUNT(*)
FROM pne_analytics.analytics_events
WHERE event_name IN (
  'form_order_created',
  'payment_status_changed',
  'invoice_created'
);
```

Lub przez tinker (bez wchodzenia do MySQL CLI):

```bash
/opt/alt/php82/usr/bin/php artisan tinker --execute="\$q = DB::connection('analytics')->table('analytics_events')->whereIn('event_name', ['form_order_created','payment_status_changed','invoice_created']); echo 'min=' . \$q->min('occurred_at') . ' max=' . \$q->max('occurred_at') . ' count=' . \$q->count();"
```

Z wyniku:
- `from` = data **najstarszego** relewantnego eventu (w Europe/Warsaw),
- `to` = **wczoraj** w Europe/Warsaw,
- **nie** agregować dzisiejszego dnia w backfillu (dzień jest jeszcze otwarty; policzy go cron jutro).

> Uwaga strefowa: `occurred_at` jest w UTC. Komenda i tak liczy dobę w Europe/Warsaw. Jeśli `min(occurred_at)`
> wypada np. `2026-06-25 22:30 UTC`, to w Warszawie to już `2026-06-26` — dobierz `--from` wg daty lokalnej.

### 6.2. Backfill partiami miesięcznymi

`--force` jest opcjonalne (delete+insert jest zawsze idempotentny), ale trzymamy je dla spójności i jasności intencji.
Daty poniżej to **przykład** — podstaw realny zakres z 6.1:

```bash
/opt/alt/php82/usr/bin/php artisan analytics:aggregate-revenue --from=2026-06-01 --to=2026-06-30 --force
/opt/alt/php82/usr/bin/php artisan analytics:aggregate-revenue --from=2026-07-01 --to=2026-07-31 --force
# ... kolejne miesiące aż do miesiąca, w którym wypada "wczoraj"
```

Reguły dopasowania:
- jeśli najstarszy event jest **w środku miesiąca** → pierwszy zakres zaczyna się od tej daty (np. `--from=2026-06-25`),
- ostatni zakres **kończy się na wczoraj** (np. jeśli dziś 2026-06-26 → `--to=2026-06-25`),
- partie miesięczne chronią request/proces przed zbyt dużym wolumenem; przy małym wolumenie można zrobić jeden zakres.

Każda komenda wypisze: liczbę dni, wiersze kursów, wiersze kampanii.

### 6.3. Kontrola po backfillu

Liczność i zakres dat:

```sql
SELECT COUNT(*), MIN(stat_date), MAX(stat_date)
FROM pne_analytics.analytics_daily_course_revenue_stats;

SELECT COUNT(*), MIN(stat_date), MAX(stat_date)
FROM pne_analytics.analytics_daily_campaign_revenue_stats;
```

**Sanity `settled` (kurs)** — oczekiwany wynik: **0 wierszy** (settled = online_paid + deferred_invoiced):

```sql
SELECT
  stat_date,
  SUM(online_paid_orders)      AS online_paid_orders,
  SUM(deferred_invoiced_orders) AS deferred_invoiced_orders,
  SUM(settled_orders_total)     AS settled_orders_total,
  SUM(online_paid_orders + deferred_invoiced_orders) AS expected_settled
FROM pne_analytics.analytics_daily_course_revenue_stats
GROUP BY stat_date
HAVING settled_orders_total <> expected_settled;
```

**Sanity `settled` (kampania)** — oczekiwany wynik: **0 wierszy**:

```sql
SELECT
  stat_date,
  SUM(online_paid_orders)      AS online_paid_orders,
  SUM(deferred_invoiced_orders) AS deferred_invoiced_orders,
  SUM(settled_orders_total)     AS settled_orders_total,
  SUM(online_paid_orders + deferred_invoiced_orders) AS expected_settled
FROM pne_analytics.analytics_daily_campaign_revenue_stats
GROUP BY stat_date
HAVING settled_orders_total <> expected_settled;
```

> Jeśli sanity zwróci jakiekolwiek wiersze → **NO-GO**. Zgłoś do analizy (nie powinno wystąpić — settled jest materializowane w serwisie).

---

## 7. Cron produkcyjny R1 (`analytics:aggregate-revenue`)

Dokładamy trzeci cron agregacji, **po** istniejących (02:15 daily, 03:15 abandonments) → **03:30**.
Styl zgodny z sekcjami 8.6/8.7 starego deploy doc (jawna ścieżka PHP, `flock` w `/tmp`, osobny log):

```bash
# Agregacja rozliczeń — adm.pnedu.pl / pneadm (03:30 czasu serwera = Europe/Warsaw)
30 3 * * * /usr/bin/flock -n /tmp/pneadm-analytics-revenue.lock /opt/alt/php82/usr/bin/php /home/srv66127/domains/adm.pnedu.pl/pneadm/artisan analytics:aggregate-revenue >> /home/srv66127/domains/adm.pnedu.pl/pneadm/storage/logs/analytics-revenue.log 2>&1
```

Uwagi:
- osobny `flock` (`pneadm-analytics-revenue.lock`) i osobny log (`analytics-revenue.log`) — bez kolizji z 02:15/03:15,
- bez argumentów komenda liczy **wczoraj** (lag=1) — dzień jest już domknięty,
- jeśli crontab serwera działa w UTC, dodaj na początku crontaba `CRON_TZ=Europe/Warsaw` **albo** przesuń godzinę ręcznie
  (komenda i tak liczy dobę w Europe/Warsaw — zmieni się tylko godzina odpalenia). Trzymaj jeden, spójny standard z 8.6/8.7.

Po dodaniu wpisu:

```bash
crontab -l
```

Sprawdź, że widnieje wpis `analytics:aggregate-revenue` z godziną `30 3`.

Pierwszy automatyczny przebieg możesz zweryfikować następnego dnia:

```bash
tail -n 50 /home/srv66127/domains/adm.pnedu.pl/pneadm/storage/logs/analytics-revenue.log
```

---

## 8. Smoke test R2 (dashboard)

1. Zaloguj się do `https://adm.pnedu.pl` jako **admin**.
2. Wejdź **Analityka → Rozliczenia** lub bezpośrednio: `https://adm.pnedu.pl/analytics/revenue`.
3. Sprawdź, że:
   - [ ] dashboard ładuje się bez błędu (HTTP 200, nie 500),
   - [ ] widać 4 kafelki KPI (Zamówione, Opłacone online, Zafakturowane odroczone, Rozliczone łącznie),
   - [ ] widać tabelę **per kurs**,
   - [ ] widać tabelę **per kampania**,
   - [ ] filtry dat działają (Od/Do + Filtruj),
   - [ ] presety zakresu działają,
   - [ ] porównanie okres-do-okresu pokazuje wartości/delty,
   - [ ] widoczny komunikat o **modelu dat** (zamówienie/płatność/faktura — różne daty).
4. Sprawdź, że dashboard **NIE** pokazuje danych osobowych ani technicznych ID:
   - [ ] brak emaili, telefonów, NIP, adresów, danych uczestników,
   - [ ] brak `invoice_number`, `form_order_id`, `payment_order_id`, raw metadata.

---

## 9. Kontrola logów i kolejek

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
tail -n 100 storage/logs/laravel.log
tail -n 100 storage/logs/analytics-revenue.log   # pojawi się po pierwszym uruchomieniu komendy/crona
/opt/alt/php82/usr/bin/php artisan tinker --execute="echo DB::table('failed_jobs')->count();"
```

> Na tej produkcji kolejka to `database` → liczbę nieudanych jobów czytamy z `failed_jobs`
> (zamiast `queue:failed`). `0` = OK.

---

## 10. GO / NO-GO

**GO (wdrożenie zaliczone), gdy:**

```text
git pull OK (HEAD = 9d43a91)
migracja OK (2 tabele revenue istnieją)
backfill OK (komendy bez wyjątków)
sanity settled (kurs i kampania) = 0 wierszy
dashboard /analytics/revenue działa (KPI + tabele + filtry + presety + porównanie)
logi bez nowych błędów krytycznych, failed_jobs bez nowych
cron 03:30 dodany (crontab -l)
```

**NO-GO / zatrzymaj i zgłoś, gdy:**

```text
git status dirty przed deployem
migracja fail
backfill rzuca wyjątki
sanity settled zwraca wiersze
dashboard zwraca 500
logi pokazują błędy krytyczne
```

---

## 11. Ryzyka i ograniczenia (świadome)

- **Różne daty eventów** w jednym wierszu agregatu (zamówienie / płatność / faktura). Dashboard etykietuje to wyraźnie — nie sumować jako jeden lejek.
- **Atrybucja kampanii** przez `FormOrder.fb_source` (order-time), `payment_status_changed`/`invoice_created` nie mają `campaign_code` w evencie.
- **Eventy bez kampanii** → tylko statystyki kursu + liczniki diagnostyczne `*_without_campaign` (nie wchodzą do tabeli kampanii).
- **Ręczne zamówienia z pneadm** (`submission_source=pnedu_manual`) **nie** emitują `form_order_created` → mogą zaniżać `orders_created` względem liczby `form_orders`. Znane ograniczenie (osobny etap, jeśli zajdzie potrzeba).
- **Faktura online** liczona tylko jako `online_invoiced_marker_orders` (poza settled) — brak double-count z opłatą online.
- **R3 CSV** wdrożone (2026-06-26) — eksport kursy/kampanie/dziennie na dashboardzie Rozliczenia.

---

## 12. Rollback (awaryjnie)

R1/R2 są addytywne (nowe tabele, nowy dashboard, brak zmian w istniejących przepływach). W razie problemu:

```bash
# 1) cofnij kod do poprzedniego stanu (przed R1+R2):
git checkout 6b0f94d
/opt/alt/php82/usr/bin/php artisan optimize:clear

# 2) (opcjonalnie) usuń cron 03:30 z crontaba.
# 3) tabele revenue mogą zostać (puste/niewidoczne bez dashboardu) lub:
/opt/alt/php82/usr/bin/php artisan migrate:rollback --step=1 --force   # cofa tylko ostatnią migrację (R1)
```

> Rollback rzadko potrzebny — zmiany nie dotykają płatności/faktur/eventów. Dashboard za flagą `ANALYTICS_REVENUE_DASHBOARD_ENABLED` (ustaw `false` w `.env`, by ukryć bez cofania kodu).

---

## 13. Dogrywka: R2.1 (przycisk „Przelicz rozliczenia”) + R3 (eksport CSV)

Status: **WDROŻONE NA PRODUKCJI (GO, 2026-06-26)**. HEAD prod: `12f1298`. **Bez migracji** — `git pull` + `optimize:clear`.

### Commity

```text
12f1298 docs(analytics): close revenue package at R3, backlog R4
e718919 docs(deploy): add R2.1 + R3 production deploy section
743e3b9 feat(analytics): add revenue CSV exports (R3)
16a53bb feat(analytics): add revenue recompute button (R2.1)
```

### Wykonany deploy (2026-06-26)

```bash
git pull                    # e718919 → 12f1298
php artisan optimize:clear  # OK
```

### Smoke test (wyniki)

- [x] `/analytics/revenue` działa (admin)
- [x] Przycisk **Przelicz rozliczenia** — OK (15 dni, 6 kursów, 7 kampanii, 2026-06-12–2026-06-26)
- [x] R3 CSV — wdrożone (3 przyciski eksportu na dashboardzie)
- [x] Dashboard read-only (agregaty R1, bez skanowania raw eventów)

### Zakres

- **R2.1**: przycisk **Przelicz rozliczenia** na `/analytics/revenue` (ręczna agregacja R1 z panelu, idempotentna, admin-only). Limit zakresu: `ANALYTICS_REVENUE_RECOMPUTE_MAX_DAYS` (domyślnie 92 dni). Audyt: `analytics_revenue_recomputed`.
- **R3**: 3 przyciski eksportu CSV „AI-safe” (kursy / kampanie / dziennie), te same filtry co dashboard. Bez danych osobowych, bez surowych eventów; kwoty z kropką (`150.00`), UTF-8 BOM.
- **Bez** zmian w bazie, **bez** zmian w `pnedu`, płatnościach, fakturach, eventach.

### Kroki wdrożenia

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git status                      # MUSI być czysty
git pull origin main
git log --oneline -3            # HEAD = 743e3b9
/opt/alt/php82/usr/bin/php artisan optimize:clear
```

> **Brak `artisan migrate`** — R2.1/R3 nie tworzą tabel. (Jeśli `git status` dirty → STOP, jak w sekcji 2.)

### Smoke test

1. `https://adm.pnedu.pl/analytics/revenue` jako **admin**.
2. R2.1:
   - [ ] widoczny przycisk **Przelicz rozliczenia** + modal z zakresem dat,
   - [ ] po „Tak, przelicz” — zielony komunikat (dni / wiersze kursów / wiersze kampanii),
   - [ ] zbyt duży zakres (> limit) → czerwony komunikat o limicie.
3. R3 (z aktywnymi filtrami dat):
   - [ ] **Eksport CSV — kursy** pobiera `pne-revenue-courses-<from>_<to>.csv`,
   - [ ] **Eksport CSV — kampanie** pobiera `pne-revenue-campaigns-<from>_<to>.csv`,
   - [ ] **Eksport CSV — dziennie** pobiera `pne-revenue-daily-<from>_<to>.csv`,
   - [ ] pliki otwierają się w Excelu z poprawnymi polskimi znakami,
   - [ ] brak danych osobowych / `invoice_number` / `form_order_id` / raw metadata w plikach.

### GO / NO-GO

```text
GO (2026-06-26): git pull OK (HEAD=12f1298), optimize:clear OK, recompute OK, R3 CSV wdrożone
```

### Rollback

```bash
git checkout 9d43a91          # stan po R1+R2 (przed R2.1/R3)
/opt/alt/php82/usr/bin/php artisan optimize:clear
```

> Addytywne, bez zmian w bazie — rollback to wyłącznie cofnięcie kodu. Flaga `ANALYTICS_REVENUE_DASHBOARD_ENABLED=false` ukrywa cały moduł (dashboard + recompute + eksporty) bez cofania kodu.
