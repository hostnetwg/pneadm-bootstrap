# Wdrożenie produkcyjne — Analityka PNEdu (czerwiec 2026)

Status: **GO na przygotowanie deploya** (Waldemar). Produkcyjnego deploya **nie wykonujemy bez ostatniego potwierdzenia na serwerze**.

Ten plik opisuje wdrożenie zakresu:

- `pneadm`: event `invoice_created` (2C-1), panel ustawień analityki (B+C), baner stanu analityki.
- `pnedu`: runtime override trybu analityki czytany z bazy `pneadm`.

> Bez sekretów. Wszystkie loginy/hosty/hasła to placeholdery (`USER_PNEDU`, `HOST`, `...`).

---

## 1. Commity do wdrożenia

### pneadm (gałąź `main`, nad `3b43005`)

```text
764e31f — feat(analytics): add invoice_created event (stage 2C-1)
ee22c83 — feat(analytics): add analytics settings panel with runtime override (B+C)
037d05e — feat(analytics): add analytics status warning banner
```

### pnedu (gałąź `main`, nad `db5a520`)

```text
ccb3e3e — feat(analytics): apply runtime analytics mode override from pneadm
```

### Wynik testów lokalnych

```text
pneadm --filter=Analytics: 89 passed (244 assertions)
pnedu  --filter=Analytics: 75 passed (598 assertions)
pnedu  sanity formularza:   15 passed (OrderEntryPlacement 4 + FormOrderCheckoutResumeService 5 + PaymentDisplayOptionOrderFormTestMode 6)
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

### 7.2 Deploy `pnedu`

```bash
cd /path/to/pnedu.pl

git status
git pull

# Maintenance mode na portalu sprzedażowym BYWA RYZYKOWNE — zwykle pomijamy 'down'.
# Wariant z maintenance (opcjonalny):
# php artisan down --render="errors::503" || true

php artisan config:cache
php artisan route:cache
php artisan view:cache
# (lub: php artisan optimize)

# Jeśli użyto 'down':
# php artisan up
```

```text
W pnedu NIE uruchamiamy migracji dla tego etapu.
Rekomendacja: deploy pnedu bez maintenance mode (portal sprzedażowy), bo zmiana jest addytywna i fail-safe.
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
