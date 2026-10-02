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

## Historia wersji materiałów (DEC-027, 2026-10-01)

Potwierdzone 2026-10-01: migracja wykonana na produkcji, smoke OK (Waldemar).

Nowa migracja: `2026_10_01_230000_create_growth_artifact_versions_table` (tabela `growth_artifact_versions`). Tylko dodaje tabelę; nie zmienia istniejących danych.

**Wymaga migracji przed użyciem.** Bez niej przy włączonej fladze „Zapisz materiał” i „Zastosuj” szkicu AI dają HTTP 500, bo zapis materiału dopisuje wersję do brakującej tabeli.

Kolejność jak wyżej: `migrate:status | grep -i growth`. Jeśli migracja jest `Pending`, zrobić backup według [MYSQL_BACKUP.md](./MYSQL_BACKUP.md), a potem `migrate --force` i komendy cache.

Istniejących materiałów nie trzeba uzupełniać. Przy pierwszej zmianie treści materiał dostaje wersję „Stan sprzed historii” z dotychczasowym tekstem, a potem nową wersję.

Smoke:

1. Zmienić treść jednego materiału i zapisać. Pod szkicem pojawia się „Historia wersji” z dwiema pozycjami.
2. „Podgląd i przywróć” przy starszej wersji, potem „Przywróć tę wersję”. Szkic wraca, status to Draft, w historii jest nowa pozycja „Przywrócenie z vN”.
3. Sama zmiana statusu nie dodaje pozycji do historii.

Rollback: kod bez tej funkcji nie czyta tabeli, więc wystarczy cofnąć kod. Tabeli nie usuwać.

## Generator obrazu grafiki głównej (DEC-029, 2026-10-02)

Nowa migracja: `2026_10_02_000000_create_growth_artifact_images_table` (tabela `growth_artifact_images`). Tylko dodaje tabelę.

**Wymaga migracji przed użyciem.** Bez niej strona „Grafika główna” daje HTTP 500, bo czyta galerię z brakującej tabeli.

Kolejność jak wyżej: `migrate:status | grep -i growth`, backup, `migrate --force` i komendy cache.

Konfiguracja (`.env`, opcjonalna — domyślne wartości są w `config/growth_ai.php`): `GROWTH_AI_IMAGE_MODEL=gpt-image-2` (DEC-030), `GROWTH_AI_IMAGE_QUALITY=medium`, `GROWTH_AI_IMAGE_DAILY_LIMIT_PER_USER=10`, `GROWTH_AI_IMAGE_TIMEOUT_SECONDS=180`, koszty szacunkowe `GROWTH_AI_IMAGE_COST_LANDSCAPE=0.042`, `GROWTH_AI_IMAGE_COST_SQUARE=0.053`, `GROWTH_AI_IMAGE_COST_ADAPT=0.07`. Klucz to ten sam `OPENAI_API_KEY`. Konto OpenAI może wymagać weryfikacji organizacji, zanim `gpt-image-2` zadziała. Bez niej generowanie kończy się komunikatem o konfiguracji AI.

Pliki trafiają do `storage/app/private/growth-os/images/`. Katalog `storage` musi być zapisywalny dla PHP, tak jak dla logów. Pliki nie są publiczne i nie potrzebują `storage:link`. Wchodzą do backupu plików aplikacji, a nie do backupu MySQL.

Generowanie trwa do 1–2 minut w jednym żądaniu. Aplikacja podnosi `set_time_limit`, ale serwer WWW lub proxy też nie może przerwać żądania wcześniej. Jeśli po około minucie pojawia się błąd 502/504, trzeba podnieść limit czasu żądania w hostingu.

Smoke:

1. „Grafika główna” otwiera się bez błędu, pod szkicem jest „Generator obrazu”.
2. „Generuj obraz” w formacie poziomym: w galerii pojawia się obraz 1920×1080, „Pobierz” zapisuje plik JPEG.
3. „Wybierz jako grafikę główną” daje zielone obramowanie i znaczek „✓ Grafika główna”.
4. „Usuń” pyta w modalu i usuwa obraz z galerii.
5. W logu `growth_ai` jest wpis `material_image` z rozmiarem i kosztem, bez opisu obrazu.
6. „Utwórz wersję kwadratową” przy obrazie poziomym (DEC-030): w galerii pojawia się kwadrat 1080×1080 ze znaczkiem „Kwadrat z poziomego #id” i tymi samymi elementami, nie przycięty.
7. Licznik „wykorzystano X z Y” rośnie po każdym obrazie, a „Zresetuj limit” po potwierdzeniu w modalu wraca do 0.

Rollback: cofnąć kod. Tabeli i plików nie usuwać.

## Status materiału „Nie dotyczy” (DEC-031, 2026-10-02)

Bez migracji i bez nowych zmiennych `.env`. Po `git pull` wystarczą komendy cache z sekcji „Deploy ADM”.

Smoke:

1. W dowolnym materiale wybrać status „Nie dotyczy” i „Zapisz materiał”. Pojawia się komunikat o wyłączeniu, szkic jest tylko do odczytu, a przyciski AI są nieaktywne.
2. Na liście materiałów w projekcie materiał jest w tym samym miejscu, wyszarzony, ze znaczkiem „Nie dotyczy”. „Następny krok” go pomija.
3. Zmiana statusu na „Draft” włącza materiał ze szkicem sprzed wyłączenia.

Rollback: cofnąć kod. Materiały zapisane jako „Nie dotyczy” wrócą wtedy do statusu z szablonu, bo stary kod nie zna `SKIPPED`. W bazie zostają z `archived`.
