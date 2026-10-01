# PNE Growth OS — deploy Etapu 0.1

Data: 2026-09-29
Status: sekcje „Etap 0.1” poniżej są historyczne. Aktualny stan i wymagane komendy opisuje sekcja „Model danych v0.1” na końcu pliku.

## Zakres

Etap 0.1 dodaje wyłącznie:

- fail-closed feature flag,
- trasę `/growth`,
- dostęp dla `super_admin`,
- warunkową pozycję menu,
- demonstracyjny pulpit read-only.

Nie ma migracji, zmian danych, kolejek, cronów, integracji, AI ani zmian w `pnedu.pl`.

## Backup

Zmiana nie dotyka bazy danych. Standardowy backup wdrożeniowy pozostaje zalecany, ale nie ma backfillu ani migracji do odtworzenia.

## Konfiguracja

Przed pierwszym deployem dodać do `.env` produkcji ADM:

```text
PNE_GROWTH_OS_ENABLED=false
```

Brak zmiennej, `false` albo wartość nieprawidłowa wyłącza moduł.

## Deploy ADM

Na produkcji nie używać Sail.

```bash
cd ~/domains/adm.pnedu.pl/pneadm
git pull origin main

/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
```

Nie uruchamiać migracji dla tego etapu — nie istnieją.

## Smoke przy fladze wyłączonej

1. Zalogować się jako `super_admin`.
2. Potwierdzić brak pozycji „PNE Growth OS” w menu.
3. Wejść ręcznie na `/growth` i potwierdzić HTTP 404.
4. Sprawdzić dashboard, szkolenia i zamówienia bez zmian.

## Opcjonalne kontrolowane włączenie

Tylko po decyzji Waldemara:

```text
PNE_GROWTH_OS_ENABLED=true
```

Następnie:

```bash
cd ~/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan config:cache
```

Smoke:

1. `super_admin` widzi pozycję menu i otwiera `/growth`.
2. Zwykły `admin` nie widzi pozycji menu i otrzymuje HTTP 403 przy ręcznym URL.
3. Ekran pokazuje oznaczenie wersji przygotowawczej i dane demonstracyjne.
4. Nie są wykonywane żadne zapisy ani wywołania zewnętrzne.

## Rollback

Najszybszy kill switch:

```text
PNE_GROWTH_OS_ENABLED=false
```

Potem:

```bash
cd ~/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan config:cache
```

Pełny rollback kodu jest możliwy standardową procedurą Git. Nie usuwać danych — Etap 0.1 żadnych nie tworzy.

## Weryfikacja techniczna

Lokalnie:

```bash
sail artisan test tests/Feature/GrowthOS/GrowthOsAccessTest.php
sail pint config/growth_os.php app/Http/Middleware/GrowthOS/EnsureGrowthOsAccess.php app/Http/Controllers/GrowthOS/DashboardController.php tests/Feature/GrowthOS/GrowthOsAccessTest.php
```

## Model danych v0.1 (aktualne, 2026-10-01)

Od commita `6d54b1a` Growth OS zapisuje dane w bazie `pneadm`. Produkcja pobrała `main` do `c5213db`.

Potwierdzone 2026-10-01: `migrate:status` na produkcji pokazuje trzy migracje Growth OS jako `Ran` (batch 116), a `/growth` otwiera się poprawnie.

Migracje Growth OS:

- `2026_09_29_191500_create_growth_os_v0_1_tables` — `growth_campaigns`, `growth_artifacts`, `growth_tasks`, `growth_decisions`,
- `2026_09_29_204500_add_key_to_growth_tasks_table`,
- `2026_09_29_220500_add_host_name_to_growth_campaigns_table`.

Od `4db7b1b` (szkielet Growth OS) w repozytorium nie dodano innych migracji.

### Objaw bez migracji

Przy `PNE_GROWTH_OS_ENABLED=true` wejście na `/growth` daje HTTP 500. Pulpit szuka ostatniej kampanii właściciela w `growth_campaigns`, a tej tabeli nie ma. Przy wyłączonej fladze middleware zwraca 404 przed odczytem bazy.

### Kolejność na produkcji

Najpierw sprawdzić, co jest do uruchomienia (tylko odczyt):

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan migrate:status | grep -i growth
```

Jeśli przy migracjach Growth OS jest `Pending`, wykonać backup bazy według [MYSQL_BACKUP.md](./MYSQL_BACKUP.md), a potem:

```bash
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart
```

`migrate --force` dokłada tylko brakujące migracje i nie kasuje danych. Nie używać `migrate:fresh`, `migrate:refresh`, `migrate:reset` ani `db:wipe`.

`growth:seed-operational-tasks` nie jest potrzebne przy pierwszym wdrożeniu: na produkcji nie ma jeszcze kampanii, a nowa kampania dostaje 9 zadań sama.

`GROWTH_AI_ENABLED` jest osobną flagą. Bez niej koncepcja używa symulacji lokalnej.

### Smoke po migracji

1. `super_admin` otwiera `/growth` bez błędu.
2. „Zaplanuj webinar TIK” → „Utwórz projekt” tworzy kampanię.
3. Zapis kierunku, koncepcji i jednego materiału; odhaczenie jednego zadania.
4. Wylogowanie i ponowne logowanie (albo inna przeglądarka): wracają kampania, prowadzący, kierunek, koncepcja, materiał i zadanie.
5. Zwykły `admin` dostaje 403.

### Rollback

Najpierw `PNE_GROWTH_OS_ENABLED=false` i `config:cache`. Tabel Growth OS nie usuwać — nie są używane przy wyłączonej fladze.
