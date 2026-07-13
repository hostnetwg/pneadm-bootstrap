# Operacje kolejki — produkcja SeoHost (pnedu.pl + adm.pnedu.pl)

Runbook dla workerów Laravel na hostingu współdzielonym **bez Supervisora**: cron co minutę + `flock` + `queue:work database --queue=default,analytics`.

Powiązane dokumenty:
- [`docs/QUEUE_SEOHOST.md`](../QUEUE_SEOHOST.md) — podstawowa konfiguracja crona
- [`docs/DASHBOARD_ORDERS.md`](../DASHBOARD_ORDERS.md) — blok „Aktywni teraz”
- [`docs/deploy/2026-06-analytics-production-deploy.md`](./2026-06-analytics-production-deploy.md) — sekcje 8.4–8.5 (worker analityki)

Incydent referencyjny: **2026-07-13** — GA pokazywało ruch, „Aktywni teraz” = 0, Sendy 8:05 nie wyszedł; naprawa: `queue:restart` na obu appkach.

---

## GA vs własna analityka („Aktywni teraz”)

| | Google Analytics | Własna analityka PNE |
|---|------------------|----------------------|
| Źródło | tag GTM/GA4 w przeglądarce | `StoreAnalyticsEventJob` → kolejka → `pne_analytics.analytics_events` |
| Panel adm | nie używany | `/` („Aktywni teraz”), `/analytics/debug-events` |
| Zależność od crona | nie | **tak** — worker kolejki na **pnedu.pl** |
| Agregaty dzienne (lejek B3/B4) | nie | osobne crony `analytics:aggregate-*` (nie wpływają na „Aktywni teraz”) |

**Wniosek:** ruch w GA przy zerze w „Aktywni teraz” prawie zawsze oznacza **problem z workerem/kolejką**, nie z kodem dashboardu.

---

## Architektura (skrót)

```
pnedu.pl (wizyta / formularz)
    → StoreAnalyticsEventJob (kolejka analytics, connection database na prod)
    → worker cron: queue:work database --queue=default,analytics
    → pne_analytics.analytics_events
    → adm.pnedu.pl API /api/dashboard/live-visitors („Aktywni teraz”)
```

Ścieżki prod (dostosuj login):

```text
pnedu:  ~/domains/pnedu.pl/app
pneadm: ~/domains/adm.pnedu.pl/pneadm
PHP:    /opt/alt/php82/usr/bin/php
```

**Uwaga:** na prod **pneadm** worker jest bezpośrednim cronem `flock queue:work`, a **nie** `schedule:run` (włączenie obu zdublowałoby workery). Zob. sekcja 8.5 w deploy doc analityki.

Sendy (newsletter) = **osobny** cron PHP (`scheduled.php`), nie Laravel.

---

## Checklist po każdym deployu na prod

Wykonaj na **obu** projektach po `git pull` + cache (+ `npm run build` na pnedu jeśli był frontend):

```bash
PHP=/opt/alt/php82/usr/bin/php

cd ~/domains/pnedu.pl/app
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan queue:restart

cd ~/domains/adm.pnedu.pl/pneadm
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan queue:restart
```

**Smoke test (2 min):**

1. Wejdź incognito na stronę kursu na **pnedu.pl**.
2. W adm: https://adm.pnedu.pl/analytics/debug-events — świeży event (np. `course_description_viewed`).
3. Dashboard https://adm.pnedu.pl/ — sekcja „Aktywni teraz” (może być pusta przy braku innych użytkowników; ważny jest punkt 2).

**Skrypt diagnostyczny:**

```bash
cd ~/domains/adm.pnedu.pl/pneadm
bash docs/deploy/scripts/prod-queue-healthcheck.sh
```

---

## Incydent: „Aktywni teraz” = 0 (a GA pokazuje ruch)

### Objawy

- Tabela „Aktywni teraz na pnedu.pl” pusta lub stale 0.
- Google Analytics rejestruje sesje.
- `/analytics/debug-events` bez świeżych wpisów po wizycie na pnedu.pl.
- Często równolegle: nie działa Sendy (zaplanowana wysyłka), PDF w adm opóźnione — wspólny cron hostingu.

### Diagnostyka (kolejność)

```bash
# 1. Automatyczny skrypt
bash ~/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-queue-healthcheck.sh --strict

# 2. Ręcznie — procesy
ps aux | grep "[q]ueue:work"

# 3. Backlog (pnedu)
cd ~/domains/pnedu.pl/app
/opt/alt/php82/usr/bin/php artisan tinker --execute="
echo 'analytics jobs: '.DB::table('jobs')->where('queue','analytics')->count().PHP_EOL;
echo 'failed: '.DB::table('failed_jobs')->count();
"

# 4. Ostatnie eventy (pneadm)
cd ~/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan tinker --execute="
echo DB::connection('analytics')->table('analytics_events')
  ->where('occurred_at','>=',now()->subMinutes(15))->count().' events / 15 min';
"

# 5. Logi
tail -50 ~/domains/pnedu.pl/app/storage/logs/queue-worker.log
tail -50 ~/domains/adm.pnedu.pl/pneadm/storage/logs/queue-worker.log
```

### Szybka naprawa (najczęściej skuteczna)

```bash
bash ~/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-queue-healthcheck.sh --restart
# poczekaj ~60 s
bash ~/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-queue-healthcheck.sh --strict
```

Ręcznie (to samo):

```bash
cd ~/domains/pnedu.pl/app && /opt/alt/php82/usr/bin/php artisan queue:restart
cd ~/domains/adm.pnedu.pl/pneadm && /opt/alt/php82/usr/bin/php artisan queue:restart
```

Jednorazowe przetworzenie zaległości (gdy backlog rośnie):

```bash
cd ~/domains/pnedu.pl/app
/opt/alt/php82/usr/bin/php artisan queue:work database --queue=default,analytics --stop-when-empty --max-time=300
```

### Zablokowany flock (worker „martwy”, cron nie startuje nowego)

1. Sprawdź procesy: `ps aux | grep "[q]ueue:work"`.
2. Jeśli **brak** procesu, a cron działa — poszukaj starych locków:
   ```bash
   ls -la /tmp/pneadm*.lock /tmp/pnedu*.lock
   ls -la ~/domains/pnedu.pl/app/storage/locks/
   ls -la ~/domains/adm.pnedu.pl/pneadm/storage/locks/
   ```
3. Usuń lock **tylko gdy** nie ma żywego `queue:work` dla danej aplikacji:
   ```bash
   rm -f /tmp/pneadm-analytics-abandonments.lock   # przykład — użyj nazw z TWOJEGO crontab
   ```
4. Nie usuwaj locków „w ciemno” przy działającym workerze.

### Inne przyczyny (rzadsze)

| Przyczyna | Gdzie sprawdzić |
|-----------|-----------------|
| `ANALYTICS_ENABLED=false` w `.env` pnedu | `.env` + baner w adm → Analityka → Ustawienia |
| Runtime override `off` | adm → Analityka → Ustawienia |
| Zły `ANALYTICS_QUEUE_CONNECTION` (redis zamiast database na prod) | `.env` pnedu — prod: `database` |
| Cron hostingu nie działa | `crontab -l`, logi DirectAdmin, Sendy `scheduled.log` |
| Wiszący stary worker (nohup) blokuje flock | `ps aux`, zabij PID, potem `queue:restart` |

---

## Monitoring (opcjonalny cron co 5–15 min)

W godzinach pracy można dodać cron **tylko diagnostyczny** (bez auto-restartu):

```bash
*/15 7-21 * * * /bin/bash /home/srv66127/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-queue-healthcheck.sh --strict >> /home/srv66127/domains/adm.pnedu.pl/pneadm/storage/logs/queue-healthcheck.log 2>&1
```

Przy `exit 1` skrypt loguje „WERDYKT: PROBLEM” — ustaw powiadomienie e-mail w DirectAdmin dla tego crona lub przeglądaj log ręcznie.

**Nie** dodawaj `--restart` do crona monitorującego (ryzyko pętli przy innym problemie).

---

## `--max-time` workera

Prod używa `--max-time=3600` (worker kończy się co godzinę, cron uruchamia nowy). To ogranicza „zombie” procesy, ale po deployu **i tak** wykonuj `queue:restart` — nie polegaj wyłącznie na godzinowym cyklu.

Rozważenie na przyszłość: skrócenie do `1800` (30 min) — częstsze odświeżenie kodu; trade-off: więcej restartów procesu PHP.

---

## Pliki w repo

| Plik | Rola |
|------|------|
| `docs/deploy/scripts/prod-queue-healthcheck.sh` | Diagnostyka + opcjonalny `--restart` |
| `docs/deploy/scripts/prod-b4-2f-post-deploy-verify.sh` | Szersza weryfikacja analityki B4+ |
| `pnedu/app/Jobs/Analytics/StoreAnalyticsEventJob.php` | Zapis eventu do bazy |
| `pneadm/app/Services/Analytics/AnalyticsLiveVisitorsService.php` | „Aktywni teraz” |
