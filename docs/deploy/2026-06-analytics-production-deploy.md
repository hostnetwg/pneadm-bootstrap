# Wdrożenie produkcyjne — Analityka PNEdu (czerwiec 2026)

Status: **GO — deploy Etapu B2** (decyzja Waldemara 2026-06-25). Commity wypchnięte na GitHub; wykonaj `git pull` na produkcji wg sekcji 7.

Ten plik opisuje wdrożenie zakresu:

- `pneadm`: event `invoice_created` (2C-1), panel ustawień analityki (B+C), baner stanu analityki, **linki kampanii/szkoleń w sales-funnel** (`60acc21`).
- `pnedu`: runtime override trybu analityki, **Etap B1/B1a/B2** (endpoint JS + collector formularza).

> Bez sekretów. Wszystkie loginy/hosty/hasła to placeholdery (`USER_PNEDU`, `HOST`, `...`).

---

## 1. Commity do wdrożenia

### pneadm (gałąź `main` — commity analityki + Etap B docs + sales-funnel)

```text
764e31f — feat(analytics): add invoice_created event (stage 2C-1)
ee22c83 — feat(analytics): add analytics settings panel with runtime override (B+C)
037d05e — feat(analytics): add analytics status warning banner
2c12826 — docs(analytics): document client analytics endpoint (B1 + B1a hardening)
272dc6e — docs(analytics): document order form client-side tracking (B2)
fb209dc — docs(analytics): document B2 deploy asset build step
60acc21 — feat(analytics): link campaigns and courses in sales funnel dashboard
```

### pnedu (gałąź `main` — Etap B)

```text
ccb3e3e — feat(analytics): apply runtime analytics mode override from pneadm
6b32a4d — feat(analytics): harden client analytics endpoint (Etap B1 + B1a)
bdc74ca — feat(analytics): add order form client-side tracking (Etap B2)
```

> **Deploy Etapu B2 (2026-06-25):** kod w `pnedu` (`6b32a4d`, `bdc74ca`). W `pneadm` oprócz dokumentacji jest też kod UI (`60acc21` — linki w lejku). Oba projekty: `git pull` + cache. `pnedu` dodatkowo: `npm ci` + `npm run build` (sekcja 7.2–7.3). Po deployu: smoke test 9.1. **Następny etap rozwojowy: B3** (agregacja porzuceń).

### Wynik testów lokalnych

```text
pneadm --filter=Analytics: 89 passed (244 assertions)
pnedu  --filter=Analytics: 75 passed (598 assertions)
pnedu  sanity formularza:   15 passed (OrderEntryPlacement 4 + FormOrderCheckoutResumeService 5 + PaymentDisplayOptionOrderFormTestMode 6)
```

### Wynik testów lokalnych — Etap B (client tracking), stan na 2026-06-25

```text
pnedu --filter=Analytics:                          110 passed (746 assertions)
pnedu --filter=OrderEntryPlacementTest:              4 passed (5 assertions)
pnedu --filter=FormOrderCheckoutResumeServiceTest:   5 passed (9 assertions)
pnedu --filter=PaymentDisplayOptionOrderFormTestModeTest: 6 passed (11 assertions)
pnedu npm run build:                               OK (vite build, ✓ built ~8.8 s)
pnedu npm test:                                    BRAK SKRYPTU (projekt nie ma testów JS; frontend B2 testowany testami PHP Feature)
```

---

## 2. Decyzje obowiązujące w tym deployu

- Startowy runtime override po deployu: **`use_config`** (NIE ustawiać od razu `enabled + standard`).
- `ANALYTICS_SAMPLE_RATE` rekomendowane produkcyjnie: **`100`**. Brak edycji `sample_rate` z panelu.
- `ANALYTICS_ENABLED=false` w `.env` pozostaje **hard kill switch** (priorytet absolutny nad panelem).
- **Kolejka analityki na produkcji:** jeśli masz już `CACHE_STORE=database` i `QUEUE_CONNECTION=database` (typowa konfiguracja PNEdu na prod), **NIE zmieniaj ich na redis**. Ustaw tylko `ANALYTICS_QUEUE_CONNECTION=database` w obu projektach i upewnij się, że worker obsługuje kolejkę `analytics` (patrz sekcja 8).
- NIE dodajemy: health endpointu/indicatora `pnedu`, nowych metryk, agregatów płatności/rozliczeń, dashboardu rozliczeń, komendy rekonsyliacyjnej, edycji `sample_rate`, per-course/campaign/event mode.

---

## 3. `.env` — adm.pnedu.pl / pneadm

> Nazwy zgodne z `config/analytics.php` i `config/database.php` w `pneadm`. Wartości to placeholdery.

### 3.1 Włączenie analityki

```env
ANALYTICS_ENABLED=true
ANALYTICS_DEFAULT_MODE=standard
ANALYTICS_SAMPLE_RATE=100

# Opcjonalne flagi paneli (jeśli używane):
ANALYTICS_DEBUG_PANEL_ENABLED=true
ANALYTICS_SALES_FUNNEL_DASHBOARD_ENABLED=true

# Kolejka analityki (PRODUKCJA — wariant database, rekomendowany):
ANALYTICS_QUEUE_CONNECTION=database
ANALYTICS_QUEUE=analytics
ANALYTICS_QUEUE_TRIES=2
ANALYTICS_QUEUE_TIMEOUT=30
```

```text
UWAGA: W kodzie domyślnie ANALYTICS_QUEUE_CONNECTION=redis (config/analytics.php).
Na produkcji z QUEUE_CONNECTION=database MUSISZ jawnie ustawić ANALYTICS_QUEUE_CONNECTION=database,
inaczej eventy analityki trafią na redis (którego worker może nie obsługiwać) i nie pojawią się w panelu.
Sprzedaż i formularz działają normalnie (analityka jest fail-silent), ale debug-events będzie pusty.
```

### 3.2 Połączenie do `pne_analytics` (osobna baza eventów)

```env
DB_ANALYTICS_HOST=...
DB_ANALYTICS_PORT=3306
DB_ANALYTICS_DATABASE=pne_analytics
DB_ANALYTICS_USERNAME=...
DB_ANALYTICS_PASSWORD=...
# Opcjonalnie: DB_ANALYTICS_URL=..., DB_ANALYTICS_TIMEZONE=+00:00
# (connection nazywa się 'analytics'; nazwę można nadpisać przez ANALYTICS_DB_CONNECTION, domyślnie 'analytics')
```

```text
To jest osobna baza eventów analitycznych: pne_analytics.
Nie mylić z bazą pneadm (główna baza aplikacji adm.pnedu.pl).
```

### 3.3 Baza główna pneadm (już istnieje — tylko potwierdzić)

```env
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=pneadm
DB_USERNAME=...
DB_PASSWORD=...
```

### 3.4 Cache / queue (produkcja — wariant `database`)

```env
# ZOSTAW bez zmian, jeśli już tak masz na produkcji:
CACHE_STORE=database
CACHE_PREFIX=...             # istniejący prefix adm — nie zmieniać
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

# Redis — opcjonalnie (może zostać w .env, nawet jeśli nie używasz go do kolejek):
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

```text
NIE zmieniaj CACHE_STORE ani QUEUE_CONNECTION na redis tylko dla analityki.
To duża zmiana dla całej aplikacji i nie jest wymagana.

Cache ustawień analityki (analytics_settings_singleton) działa na CACHE_STORE=database — OK.

Worker musi obsługiwać kolejkę analytics na połączeniu database, np.:
  php artisan queue:work database --queue=default,analytics
Patrz sekcja 8.
```

### 3.5 Wariant alternatywny: redis (tylko jeśli prod już tak działa)

Jeśli na produkcji masz świadomie `QUEUE_CONNECTION=redis` i działającego workera na redis:

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
ANALYTICS_QUEUE_CONNECTION=redis
ANALYTICS_QUEUE=analytics
```

```text
Nie przechodź na redis „dla analityki”, jeśli dziś używasz database — to niepotrzebne ryzyko.
```

---

## 4. `.env` — pnedu.pl

> Nazwy zgodne z `config/analytics.php` i `config/database.php` w `pnedu`. Wartości to placeholdery.

### 4.1 Włączenie analityki

```env
ANALYTICS_ENABLED=true
ANALYTICS_DEFAULT_MODE=standard
ANALYTICS_SAMPLE_RATE=100

# Kolejka analityki (PRODUKCJA — wariant database, rekomendowany):
ANALYTICS_QUEUE_CONNECTION=database
ANALYTICS_QUEUE=analytics
ANALYTICS_QUEUE_TRIES=2
ANALYTICS_QUEUE_TIMEOUT=30
```

```text
Jeśli ANALYTICS_ENABLED=false w pnedu.pl, portal NIE będzie zbierał eventów,
niezależnie od ustawień runtime override w panelu pneadm (lokalny hard kill switch).

UWAGA: Domyślnie w kodzie ANALYTICS_QUEUE_CONNECTION=redis. Na prod z QUEUE_CONNECTION=database
ustaw jawnie ANALYTICS_QUEUE_CONNECTION=database (patrz też sekcja 3.4 i 8).
```

### 4.2 Połączenie do `pne_analytics`

```env
DB_ANALYTICS_HOST=...
DB_ANALYTICS_PORT=3306
DB_ANALYTICS_DATABASE=pne_analytics
DB_ANALYTICS_USERNAME=...
DB_ANALYTICS_PASSWORD=...
# Opcjonalnie: DB_ANALYTICS_URL=..., DB_ANALYTICS_TIMEZONE=+02:00
```

### 4.3 Połączenie `pnedu` → baza `pneadm` (KRYTYCZNE dla runtime override)

```env
DB_PNEADM_HOST=...
DB_PNEADM_PORT=3306
DB_PNEADM_DATABASE=pneadm
DB_PNEADM_USERNAME=...
DB_PNEADM_PASSWORD=...
```

```text
Connection 'pneadm' w pnedu MUSI pozwalać czytać ustawienia analytics_settings z bazy pneadm.
Analogiczny mechanizm jest już używany przez PaymentDisplayOption.
Uwaga zgodności wstecznej: jeśli prod używa starych nazw DB_ADMPNEDU_*, one nadal działają
jako fallback (DB_ADMPNEDU_HOST/PORT/DATABASE/USERNAME/PASSWORD). Preferowane są nazwy DB_PNEADM_*.
```

### 4.4 Baza główna pnedu (już istnieje — tylko potwierdzić)

```env
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=pnedu
DB_USERNAME=...
DB_PASSWORD=...
```

### 4.5 Cache / queue (produkcja — wariant `database`)

```env
# ZOSTAW bez zmian, jeśli już tak masz na produkcji:
CACHE_STORE=database
CACHE_PREFIX=pnedu_
QUEUE_CONNECTION=database

# Redis — opcjonalnie:
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

```text
NIE zmieniaj na redis. Dopisz tylko ANALYTICS_QUEUE_CONNECTION=database (sekcja 4.1).
Worker: database --queue=default,analytics (sekcja 8).
```

---

## 5. Uprawnienia DB

Użytkownik DB, którym `pnedu.pl` łączy się przez connection `pneadm`, musi mieć `SELECT` na tabeli:

```text
pneadm.analytics_settings
```

(oraz zachować dotychczasowy dostęp do tabel używanych przez `PaymentDisplayOption`).

### Kontrola (uruchomić jako użytkownik DB używany przez pnedu)

```sql
SELECT COUNT(*) AS ok FROM pneadm.analytics_settings;
-- lub:
SHOW GRANTS FOR CURRENT_USER();
```

### Nadanie uprawnienia (jeśli brakuje) — placeholdery

```sql
GRANT SELECT ON pneadm.analytics_settings TO 'USER_PNEDU'@'HOST';
FLUSH PRIVILEGES;
```

```text
Nie wpisywać realnego użytkownika, hosta ani hasła.
```

---

## 6. Migracja produkcyjna

- Migrację uruchamiamy **tylko w `pneadm`**.
- Trafia do **standardowej bazy `pneadm`** (connection domyślny `mysql`).
- **NIE** uruchamiać w `pnedu`.
- **NIE** uruchamiać w `pne_analytics`.

Plik migracji:

```text
database/migrations/2026_06_25_120000_create_analytics_settings_table.php
```

### Wariant standardowy (uruchamia wszystkie pending migracje)

```bash
php artisan migrate --force
```

### Wariant chirurgiczny (tylko ta migracja)

```bash
php artisan migrate --force --path=database/migrations/2026_06_25_120000_create_analytics_settings_table.php
```

```text
Wariant --path uruchamia tylko wskazaną migrację i pomija inne pending migrations.
Użyj go, jeśli chcesz pełną kontrolę i masz pewność, że nie ma innych oczekiwanych migracji do wdrożenia.
```

Po migracji powstaje tabela `analytics_settings` z 1 rekordem `id=1` (override = null/null → „użyj .env/config").

---

## 7. Komendy deploya

> Ścieżki to placeholdery — dostosuj do realnego serwera.

### 7.1 Deploy `pneadm`

```bash
cd /path/to/adm.pnedu.pl

git status
git pull

# Maintenance mode — OPCJONALNE, tylko jeśli obecny proces prod tego używa:
php artisan down --render="errors::503" || true

# Migracja (wariant standardowy lub --path, patrz sekcja 6):
php artisan migrate --force

# Cache wg obecnego procesu prod:
php artisan config:cache
php artisan route:cache
php artisan view:cache
# (lub: php artisan optimize)

php artisan up

# Weryfikacja migracji:
php artisan migrate:status | grep analytics_settings
```

### 7.2 Deploy `pnedu` (z budowaniem assetów — od Etapu B2)

> Od Etapu B2 proces deployu `pnedu` zawiera krok `npm run build` (decyzja Waldemara, 2026-06-25).
> WAŻNE: sam collector B2 jest renderowany **inline w Blade** i NIE wymaga bundla — portal zadziała
> nawet bez przebudowy assetów. `npm run build` wchodzi do procesu jako standaryzacja (przyszłe zmiany
> w `resources/js/*`/`resources/sass/*`), a nie jako twardy warunek działania B2.
> Konsekwencje `public/build` (dirty working tree, ryzyko blokady `git pull`) → patrz **7.3**.

```bash
cd /path/to/pnedu.pl          # produkcja PNEdu: /home/srv66127/domains/pnedu.pl/app

git status                     # MUSI być czysto (jeśli dirty przez public/build → patrz 7.3)
git pull

# Maintenance mode na portalu sprzedażowym BYWA RYZYKOWNE — zwykle pomijamy 'down'.
# Wariant z maintenance (opcjonalny):
# php artisan down --render="errors::503" || true

# Composer — TYLKO jeśli zmieniły się zależności PHP. B2 ich NIE zmienia, więc zwykle pomijamy:
# composer install --no-dev --optimize-autoloader

# Frontend (Node/npm) — od B2. PRZED pierwszym buildem zweryfikuj dostępność Node na serwerze (7.3):
npm ci                         # preferowane: w repo jest package-lock.json (deterministyczny install)
npm run build                  # vite build → public/build/* (UWAGA: pliki śledzone, patrz 7.3)

php artisan config:cache
php artisan route:cache
php artisan view:cache
# (lub: php artisan optimize)

php artisan queue:restart      # przeładowanie workerów po deployu (lub naturalnie przez --max-time crona)

# Jeśli użyto 'down':
# php artisan up
```

```text
- npm ci jest PREFEROWANE, bo w repo jest package-lock.json (czysty, deterministyczny install).
  Jeśli na serwerze brak package-lock lub `npm ci` zawiedzie → fallback `npm install`.
- W pnedu NIE uruchamiamy migracji dla tego etapu.
- Rekomendacja: deploy pnedu bez maintenance mode (portal sprzedażowy) — zmiana addytywna i fail-safe.
- Realny prod PNEdu używa alt-php: zamiast `php` bywa /opt/alt/php82/usr/bin/php.
  Node/npm na DirectAdmin/SeoHost mogą być pod osobną ścieżką lub wymagać włączenia —
  zweryfikuj `node -v` / `npm -v` PRZED deployem (7.3).
- Jeśli Node na produkcji NIE jest dostępny: B2 i tak działa (collector inline) — pomiń krok npm,
  a włączenie buildu na prod potraktuj jako osobny etap porządkowy (7.3).
```

### 7.3 `public/build` na produkcji — pre-check Node i ryzyko „dirty working tree"

`public/build/*` jest **śledzone w Git** (`public/build/manifest.json`, `public/build/assets/app-*.css`,
`public/build/assets/app-*.js`). Dotychczas produkcja **nie** wykonywała `npm run build` i serwowała
commitowane assety. Od B2 deploy ma robić build — to rodzi konsekwencje opisane niżej.

**Pre-check środowiska (na serwerze, PRZED pierwszym buildem):**

```bash
node -v          # czy Node jest dostępny i w jakiej wersji (lokalnie: v22)
npm -v           # lokalnie: 11.x
which node npm   # ścieżki (na alt-php/DirectAdmin/SeoHost mogą być nietypowe lub Node wyłączony)
```

```text
Jeśli Node/npm NIE są dostępne na produkcji:
- B2 i tak DZIAŁA (collector inline w Blade) → deploy B2 nie jest zablokowany,
- pomiń krok npm i wdróż resztę; assety zostają jak w repo (poprzedni bundel),
- włączenie Node/buildu na produkcji potraktuj jako osobny, przyszły etap porządkowy (niżej).
```

**Konsekwencje uruchomienia `npm run build` na produkcji (zweryfikowane lokalnie 2026-06-25):**

1. Build **zmienia pliki śledzone**: nadpisuje `public/build/manifest.json` i generuje
   `public/build/assets/app-*.js` z **nowym hashem treści** (lokalnie: `app-yYbHl3fg.js` → `app-CsKfMtAR.js`),
   stary plik znika. CSS bez zmian (`app-DAs5Lhk2.css`).
2. Po buildzie `git status` na produkcji **będzie „dirty"**: zmodyfikowany `manifest.json`,
   usunięty stary `app-*.js`, nieśledzony nowy `app-*.js`.
3. **Przyszły `git pull` może zostać zablokowany** komunikatem typu
   *„Your local changes to the following files would be overwritten by merge: public/build/…"*.

> OSTRZEŻENIE (do procesu deployu): Ponieważ public/build jest śledzone w Git, uruchomienie
> npm run build na produkcji może zostawić lokalne zmiany w public/build. Przed kolejnymi deployami
> trzeba sprawdzić git status i nie dopuścić, aby zmodyfikowany build blokował git pull.

**Obejście na teraz (NIE zmieniamy architektury assetów w tym kroku):**

```bash
# Jeśli przed git pull working tree jest „dirty" tylko przez public/build:
git checkout -- public/build      # odrzuć lokalny build (wróci wersja z repo)
git pull
npm ci && npm run build           # odtwórz build po pullu
# Alternatywnie: git stash push -- public/build   (i ewentualnie git stash drop)
```

**Przyszły etap porządkowy (NIE wdrażać teraz)** — rekomendowany, gdy build na prod się ustabilizuje:

```text
chore(assets): standardize production asset build process
- albo: usunąć public/build z Gita + dodać do .gitignore + budować deterministycznie na deployu/CI,
- albo: budować assety w CI i wgrywać artefakt (produkcja bez Node).
Cel: koniec z „dirty working tree" i ryzykiem blokady git pull.
```

---

## 8. Kontrola workerów queue analytics

### 8.1 Sprawdzenie, czy worker działa

```bash
supervisorctl status | grep -i queue
ps aux | grep "queue:work" | grep -v grep
```

### 8.2 Produkcja z `QUEUE_CONNECTION=database` (rekomendowane — typowa konfiguracja PNEdu)

Worker **musi** obsługiwać kolejkę `analytics` na połączeniu `database`:

```bash
php artisan queue:work database --queue=default,analytics
```

```text
W .env (oba projekty) ustaw:
  ANALYTICS_QUEUE_CONNECTION=database
  ANALYTICS_QUEUE=analytics

Jeśli worker słucha tylko --queue=default, eventy analityki będą czekać w tabeli jobs
i NIE pojawią się w /analytics/debug-events. Sprzedaż działa normalnie.

Sprawdzenie w bazie (opcjonalnie): czy w tabeli jobs są rekordy z queue=analytics.
```

Przykład wpisu Supervisor (placeholder — dostosuj ścieżki):

```ini
[program:pnedu-queue]
command=php /path/to/pnedu.pl/artisan queue:work database --queue=default,analytics --sleep=3 --tries=3
```

(analogicznie osobny worker dla `pneadm`, jeśli pneadm też produkuje eventy analityki)

### 8.3 Wariant alternatywny: redis

Tylko jeśli produkcja świadomie używa redis do kolejek:

```bash
php artisan queue:work redis --queue=analytics
```

```env
ANALYTICS_QUEUE_CONNECTION=redis
```

### 8.4 Restart workera po deployu

Jeśli po deployu worker wymaga przeładowania kodu:

```bash
php artisan queue:restart
```

```text
queue:restart nie zabija jobów natychmiast — każe workerom zakończyć bieżący job i wystartować ponownie
(przez Supervisor). Wykonać po deployach, które zmieniają kod obsługiwany przez worker.
```

### 8.5 Stały worker bez Supervisora (cron + flock) — PRODUKCJA PNEdu

Na produkcyjnym hostingu PNEdu **nie ma Supervisora** (`supervisorctl: command not found`).
Stały worker utrzymujemy cronem co minutę z blokadą `flock` (auto-restart, przeżywa reboot,
brak duplikatów). `--max-time=3600` powoduje, że worker sam kończy po godzinie, a cron go wznawia
(dzięki temu po deployu łapie nowy kod — nie trzeba osobnego `queue:restart`).

Ścieżki produkcyjne (dostosuj do realnych):

```text
pnedu:  /home/srv66127/domains/pnedu.pl/app
pneadm: /home/srv66127/domains/adm.pnedu.pl/pneadm
```

Katalogi na pliki blokad:

```bash
mkdir -p /home/srv66127/domains/pnedu.pl/app/storage/locks
mkdir -p /home/srv66127/domains/adm.pnedu.pl/pneadm/storage/locks
```

Wpisy crontab (`crontab -e`):

```bash
# Worker kolejki analityki — pnedu.pl
* * * * * cd /home/srv66127/domains/pnedu.pl/app && flock -n storage/locks/queue-analytics.lock -c "php artisan queue:work database --queue=default,analytics --sleep=3 --tries=3 --max-time=3600" >> storage/logs/queue-worker.log 2>&1

# Worker kolejki analityki — adm.pnedu.pl / pneadm
* * * * * cd /home/srv66127/domains/adm.pnedu.pl/pneadm && flock -n storage/locks/queue-analytics.lock -c "php artisan queue:work database --queue=default,analytics --sleep=3 --tries=3 --max-time=3600" >> storage/logs/queue-worker.log 2>&1
```

```text
UWAGA: przed włączeniem crona zatrzymaj ręcznie uruchomione workery (nohup/screen),
żeby nie dublować procesów:
  ps aux | grep "queue:work" | grep -v grep
  kill <PID>
flock i tak blokuje duplikat z crona, ale stary ręczny proces należy zamknąć.
```

Weryfikacja:

```bash
ps aux | grep "queue:work" | grep -v grep         # powinny być 2 procesy (pnedu + pneadm)
tail -n 20 storage/logs/queue-worker.log
php artisan tinker --execute="echo DB::connection('analytics')->table('analytics_events')->count();"
```

> Uwaga: na realnej produkcji PNEdu workery są wpięte w stylu DirectAdmin/SeoHost:
> `/usr/bin/flock -n /tmp/<nazwa>.lock /opt/alt/php82/usr/bin/php <ścieżka>/artisan queue:work database --queue=default,analytics ...`
> (lock w `/tmp`, jawna ścieżka PHP `/opt/alt/php82`). Powyższe przykłady ze `storage/locks` są
> równoważne — utrzymuj jeden, spójny styl. Jeden worker `pneadm` i jeden `pnedu` (bez duplikatów).

### 8.6 Agregacja dzienna (lejek sprzedaży) — cron `analytics:aggregate-daily`

Dashboard **Lejek sprzedaży** (`/analytics/sales-funnel`) NIE czyta surowych eventów —
czyta **dzienne agregaty** (`analytics_daily_course_stats`, `analytics_daily_campaign_stats`).
Agregaty powstają wyłącznie po uruchomieniu komendy `analytics:aggregate-daily` (tylko `pneadm`).

Zachowanie komendy:

- bez opcji → liczy **dzień poprzedni** w strefie `Europe/Warsaw` (`config('analytics.aggregation.timezone')`),
- `--date=YYYY-MM-DD` → jeden dzień; `--from=YYYY-MM-DD --to=YYYY-MM-DD` → zakres,
- **idempotentna**: dla danego `stat_date` kasuje istniejące wiersze i liczy od zera (ponowne uruchomienie nie duplikuje),
- liczy datę w `Europe/Warsaw` i tłumaczy granice doby na UTC przy odpytywaniu `occurred_at` — **poprawna niezależnie od strefy serwera**,
- nie rusza starych tabel `marketing_campaign_stats_daily` / `course_page_stats_daily`, brak PII.

**Mechanizm wybrany: zwykły cron z `flock` (NIE Laravel Scheduler).** Powód:
`pneadm` na produkcji nie ma crona `schedule:run` (działa tylko bezpośredni `flock queue:work`).
Włączenie `schedule:run` aktywowałoby też wpis `Schedule::command('queue:work ...')->everyMinute()`
z `routes/console.php` → drugi worker kolejki (duplikat). Komenda agregacji i tak sama liczy datę
w `Europe/Warsaw`, więc scheduler nie jest potrzebny do poprawnej strefy.

Wpis crontab (`crontab -e`) — styl realnej produkcji PNEdu:

```bash
# Agregacja dzienna analityki — adm.pnedu.pl / pneadm (02:15 czasu serwera = Europe/Warsaw)
15 2 * * * /usr/bin/flock -n /tmp/pneadm-aggregate.lock /opt/alt/php82/usr/bin/php /home/srv66127/domains/adm.pnedu.pl/pneadm/artisan analytics:aggregate-daily >> /home/srv66127/domains/adm.pnedu.pl/pneadm/storage/logs/analytics-aggregate.log 2>&1
```

```text
TIMEZONE: serwer PNEdu działa w Europe/Warsaw (CEST/CET), więc 02:15 w cronie = 02:15 czasu polskiego,
a zmiana czasu (DST) jest obsłużona przez strefę systemu. Nawet gdyby serwer kiedyś przeszedł na UTC,
komenda i tak agreguje właściwą dobę polską (datę liczy w Europe/Warsaw) — przesunie się tylko
godzina odpalenia. Uruchamiamy o 02:15, by mieć pewność, że poprzednia doba jest już zamknięta.
```

#### Przeliczanie z panelu (przycisk „Przelicz teraz")

Na `/analytics/sales-funnel` (góra strony) jest przycisk **„Przelicz teraz"** — ręczne przeliczenie
agregatów dla **aktualnie widocznego zakresu dat** (z filtra), bez wchodzenia do konsoli.

- POST, **tylko admin** (middleware `analytics.debug.access`), idempotentne,
- potwierdzenie przez **modal Bootstrap** (pokazuje wybrany zakres dat), nie natywne `confirm()`,
- limit bezpieczeństwa **konfigurowalny**: `ANALYTICS_SALES_FUNNEL_RECOMPUTE_MAX_DAYS`
  (config `analytics.sales_funnel_dashboard.recompute_max_days`, domyślnie **366** = rok);
  większy zakres → komunikat o zawężeniu lub użyciu konsoli,
- po wykonaniu: komunikat z liczbą przeliczonych dni/wierszy, zapis do `ActivityLog`
  (`analytics_aggregates_recomputed`), powrót na dashboard z zachowanymi filtrami,
- fail-safe: błąd przeliczenia nie wywala strony (komunikat + sugestia konsoli).

Konsola pozostaje dostępna do dużych zakresów / automatyzacji (poniżej).

#### Catch-up po wdrożeniu (jednorazowo)

Najpierw ustal, od kiedy produkcja ma eventy:

```sql
SELECT MIN(occurred_at), MAX(occurred_at), COUNT(*) FROM pne_analytics.analytics_events;
```

lub przez artisan (bez wchodzenia do SQL):

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan tinker --execute="echo DB::connection('analytics')->table('analytics_events')->min('occurred_at') . ' .. ' . DB::connection('analytics')->table('analytics_events')->max('occurred_at');"
```

Następnie przelicz zakres (przykład: ostatnie 7 dni — podstaw realne daty z zapytania wyżej):

```bash
# bieżący dzień (żeby lejek od razu coś pokazał):
/opt/alt/php82/usr/bin/php artisan analytics:aggregate-daily --date=2026-06-25

# zakres historyczny (podstaw daty wg MIN/MAX occurred_at):
/opt/alt/php82/usr/bin/php artisan analytics:aggregate-daily --from=2026-06-19 --to=2026-06-25
```

#### Kontrola po agregacji

```bash
# 1) komenda kończy się sukcesem i wypisuje liczby (Dni / wiersze kursów / wiersze kampanii)
# 2) tabele agregatów mają wiersze:
/opt/alt/php82/usr/bin/php artisan tinker --execute="echo 'course=' . DB::connection('analytics')->table('analytics_daily_course_stats')->count() . ' campaign=' . DB::connection('analytics')->table('analytics_daily_campaign_stats')->count();"
# 3) dashboard: https://adm.pnedu.pl/analytics/sales-funnel pokazuje dane w wybranym zakresie dat
# 4) log agregacji:
tail -n 20 storage/logs/analytics-aggregate.log
# 5) brak nowych failed jobs:
/opt/alt/php82/usr/bin/php artisan tinker --execute="echo DB::table('failed_jobs')->count();"
# 6) ponowne uruchomienie tego samego dnia NIE duplikuje (idempotencja):
/opt/alt/php82/usr/bin/php artisan analytics:aggregate-daily --date=2026-06-25
#    -> liczby wierszy w punkcie 2 pozostają takie same
```

---

## 9. Smoke test po deployu

1. Otwórz stronę kursu.
2. Otwórz formularz zamówienia:

```text
/courses/524/order-form?price_variant_id=78&utm_source=newsletter&utm_medium=email&utm_campaign=2026-06_514-newsletter&utm_content=organic&fb=2026-06_514-newsletter
```

3. Sprawdź, że formularz ładuje się bez błędu.
4. Sprawdź, że wariant ceny jest poprawnie ustawiony.
5. **Nie wysyłaj realnego zamówienia** bez decyzji Waldemara.
6. Wejdź na `/analytics/settings` (jako admin) i ustaw runtime override na **`use_config`**.
7. Sprawdź, że baner nie sygnalizuje przypadkowego `off` (chyba że wynika to z `.env`).
8. Wejdź na `/analytics/debug-events` i sprawdź nowe eventy:
   - `course_description_viewed`,
   - `order_form_viewed`,
   - jeśli wykonano testowe zamówienie: `order_form_submit_attempted`, `online_payment_selected` / `deferred_invoice_selected`, `form_order_created`, `payment_order_created` (jeśli online).
9. Sprawdź logi Laravel `pneadm` (`storage/logs/laravel.log`).
10. Sprawdź logi Laravel `pnedu` (`storage/logs/laravel.log`).
11. Sprawdź `failed_jobs` (brak nowych błędów analityki).
12. Sprawdź worker queue `analytics` (działa, przerabia joby).

### 9.1 Smoke test B2 — JS collector formularza (po deployu z `npm run build`)

```text
1.  Deploy pnedu wykonany (z krokiem npm run build — sekcja 7.2; lub pominiętym, jeśli brak Node — 7.3).
2.  Otwórz formularz zamówienia (URL jak w sekcji 9).
3.  Wejdź w pierwsze pole formularza (focus/wpisanie czegokolwiek).
4.  Wybierz metodę płatności (online / faktura odroczona).
5.  Kliknij „Powrót do szczegółów szkolenia" LUB submit — tylko w kontrolowanym scenariuszu
    (NIE wysyłaj realnego zamówienia bez decyzji Waldemara).
6.  Wejdź na /analytics/debug-events (jako admin).
7.  Oczekiwane eventy:
    - order_form_viewed
    - order_form_started
    - order_form_section_interacted
    - order_form_cta_clicked
    - order_form_submit_clicked   (tylko jeśli kliknięto submit)
8.  Sprawdź metadata eventów: BRAK PII i BRAK wartości pól
    (dozwolone tylko: section_key / cta_key / trigger / course_id / price_variant_id).
9.  Sprawdź logi Laravel pnedu (storage/logs/laravel.log) — brak błędów.
10. Sprawdź failed_jobs — brak nowych błędów analityki.
11. Sprawdź `git status` na produkcji PO buildzie i opisz, czy public/build/* zostało zmienione
    (oczekiwane: TAK — patrz 7.3; zadbać, by nie zablokowało kolejnego git pull).
```

```text
Tryb analityki a widoczność eventów JS:
- efektywny tryb >= standard → widoczne wszystkie 4 eventy JS,
- tryb 'light' (z JS) → CELOWO tylko order_form_started i order_form_submit_clicked
  (section_interacted/cta_clicked pomijane jako zbyt szczegółowe — patrz AnalyticsModeResolver),
- tryb off/aggregate_only → eventy JS nie są zapisywane (fail-silent, endpoint zawsze 204).
```

---

## 10. Rollback

### 10.1 Szybkie wyłączenie analityki BEZ cofania kodu

```text
Panel -> Analityka -> Ustawienia -> runtime override: disabled (lub default_mode_override: off)
```

albo w `.env` (hard kill switch):

```env
ANALYTICS_ENABLED=false
```

a następnie:

```bash
php artisan config:cache
```

```text
To wyłącza analitykę. Nie wpływa na sprzedaż, płatności ani faktury.
```

### 10.2 Rollback kodu

`pnedu`:

```text
revert ccb3e3e   (lub deploy poprzedniej wersji)
```

`pneadm`:

```text
revert 037d05e
revert ee22c83
revert 764e31f
(lub deploy poprzedniej wersji)
```

```text
Tabela analytics_settings może pozostać, jeśli kod już jej nie używa.
Nie usuwać jej bez świadomej decyzji (ewentualnie down() migracji).
```

---

## 11. Finalna checklista GO / NO-GO

```text
[ ] Backup pneadm wykonany
[ ] pneadm .env sprawdzony (w tym ANALYTICS_QUEUE_CONNECTION=database)
[ ] pnedu .env sprawdzony (w tym ANALYTICS_QUEUE_CONNECTION=database)
[ ] CACHE_STORE=database i QUEUE_CONNECTION=database ZOSTAWIONE bez zmian (nie przechodzić na redis)
[ ] pnedu ma SELECT na pneadm.analytics_settings
[ ] worker obsługuje database --queue=default,analytics (lub równoważnie)
[ ] migracja analytics_settings wykonana
[ ] /analytics/settings działa
[ ] runtime override ustawiony na use_config
[ ] formularz pnedu.pl ładuje się
[ ] debug-events pokazuje nowe eventy
[ ] logi bez błędów
[ ] failed_jobs bez nowych błędów
[ ] cron agregacji (analytics:aggregate-daily, 02:15) dodany w pneadm
[ ] catch-up agregacji uruchomiony (lejek sprzedaży pokazuje dane)
```

---

## 12. Uwagi / ryzyka

- Connection `pneadm` w `pnedu` jest krytyczny dla runtime override, ale odczyt jest **fail-safe**:
  brak tabeli / brak uprawnień / niedostępny `pneadm` → resolver użyje `.env/config` i NIE przerwie formularza.
- Panel `pneadm` nie odpytuje `pnedu` (brak health endpointu) — lokalny hard kill switch w `pnedu` nie jest
  widoczny w panelu (informacja o tym jest na stronie ustawień).
- Analityka używa **osobnej** kolejki `analytics` (ANALYTICS_QUEUE=analytics) na połączeniu
  **ANALYTICS_QUEUE_CONNECTION** (na prod rekomendowane: `database`, zgodnie z QUEUE_CONNECTION).
  Jeśli worker nie obsługuje kolejki `analytics`, eventy czekają w tabeli `jobs` — nie wpływa to na sprzedaż.
- Domyślny kod ma ANALYTICS_QUEUE_CONNECTION=redis — na prod z database **trzeba** nadpisać w `.env`.
- `sample_rate` tylko podglądowy; brak edycji w panelu w tym etapie.
- **Lejek sprzedaży zależy od agregacji dziennej** (sekcja 8.6). Bez crona `analytics:aggregate-daily`
  dashboard pozostaje pusty mimo poprawnie zbieranych eventów (debug-events działa niezależnie).
- Agregacja `pneadm` świadomie NIE używa `schedule:run` (brak tego crona na prod; włączenie zdublowałoby
  worker kolejki). Jeśli w przyszłości pneadm dostanie `schedule:run`, najpierw skonsolidować worker kolejki,
  by nie powstał duplikat.
- Bieżąca doba pojawi się w lejku dopiero po nocnej agregacji (lub po ręcznym `--date=dzisiaj`).
