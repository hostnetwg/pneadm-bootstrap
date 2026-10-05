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

## Szkic AI mailingu głównego (DEC-032, 2026-10-02)

Bez migracji i bez nowych zmiennych `.env`. Korzysta z tego samego klucza, modelu i limitów co pozostałe szkice AI. Po `git pull` wystarczą komendy cache z sekcji „Deploy ADM”.

Smoke:

1. „Mailing główny” ma kartę „Szkic z pomocą AI” z przełącznikiem „Długość maila” i checkboxem emotikon (domyślnie wyłączonym).
2. „Poproś AI o szkic” w wersji krótkiej: propozycja zaczyna się od „Temat:”, ma dwie inne propozycje tematu, preheader, „Dzień dobry,”, `[LINK DO ZAPISU]` i podpis „Zespół PNE”.
3. Wersja dłuższa daje wyraźnie dłuższą treść. „Zastosuj” zapisuje szkic ze statusem Draft i nową wersją w historii.
4. Po „Zastosuj” szkic jest w polach Temat, Preheader i Treść, a pod tematem lista „Propozycje tematu od AI” ze wszystkimi trzema tematami (aktualny oznaczony „w polu”). Tematy i preheader zaczynają się wielką literą. „Kopiuj kod HTML preheadera” kopiuje ukryty `div`.

Rollback: cofnąć kod. Zapisane szkice mailingu zostają.

## Szkic AI mailingu przypominającego (DEC-033, 2026-10-02)

Bez migracji i bez nowych zmiennych `.env`. Po `git pull` wystarczą komendy cache z sekcji „Deploy ADM”.

Smoke:

1. „Mailing przypominający” ma pola Temat, Preheader i Treść oraz kartę „Szkic z pomocą AI” z przełącznikami „Kiedy wysyłasz przypomnienie” i „Długość maila”.
2. Karta pokazuje, czy AI dostanie zatwierdzony opis YouTube i zatwierdzony mailing główny.
3. „Poproś AI o szkic” z opcją „Dzień przed”: treść mówi o „jutro”, ma termin, `[LINK DO POKOJU]`, `[LINK DO ZAPISU]` i podpis „Zespół PNE”. Opcja „W dniu webinaru” daje „dziś”.
4. „Zastosuj” rozdziela szkic na pola, a pod tematem są 3 propozycje z „Użyj”.

Rollback: cofnąć kod. Zapisane szkice przypomnienia zostają, ale wracają do jednego pola tekstowego.

## Szkic AI scenariusza prowadzącego (DEC-034, 2026-10-02)

Bez migracji i bez nowych zmiennych `.env`. Po `git pull` wystarczą komendy cache z sekcji „Deploy ADM”.

Smoke:

1. „Scenariusz prowadzącego” ma kartę „Szkic z pomocą AI” z przełącznikiem „Czas trwania webinaru” (domyślnie 60 minut) i opcją „Inny” z polem minut.
2. „Inny” bez liczby albo z liczbą spoza 15–240 pokazuje błąd przy polu i nie wywołuje AI.
3. „Poproś AI o szkic” dla 60 minut: szkic zaczyna się od „Checklista przed startem”, bloki mają godziny od początku webinaru do godziny o 60 minut późniejszej, są linie „Pytanie na czat:” i blok „Pytania i odpowiedzi”.
4. „Zastosuj” zapisuje scenariusz ze statusem Draft i nową wersją w historii.

Rollback: cofnąć kod. Zapisane scenariusze zostają.

## Prowadzący, głos komunikacji i tryby opisu YouTube (DEC-035, DEC-036, 2026-10-02)

**Wymaga migracji.** Dwie nowe kolumny, obie `NULL`, bez wypełniania danych:

- `2026_10_02_130000_add_communication_voice_instructor_id_to_growth_campaigns_table` — `growth_campaigns.communication_voice_instructor_id` (klucz obcy do `instructors`, `ON DELETE SET NULL`),
- `2026_10_02_130100_add_ai_voice_profile_to_instructors_table` — `instructors.ai_voice_profile` (TEXT).

Bez nowych zmiennych `.env`. Kolejność na produkcji:

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull
/bin/bash /home/srv66127/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-mysql-nightly-backup.sh
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
```

Sprawdzenie: `/opt/alt/php82/usr/bin/php artisan migrate:status | grep 2026_10_02_1301` pokazuje obie migracje jako `Ran`.

Smoke:

1. Formularz instruktora (jako `super_admin`) ma pole „Profil komunikacji dla AI”; wpisz i zapisz profil Waldemara. Zwykły admin tego pola nie widzi.
2. „Zaplanuj webinar TIK”: „Prowadzący” z bazy albo spoza bazy, „Głos komunikacji” domyślnie „PNE — neutralnie”. Utwórz webinar z prowadzącym spoza bazy i głosem Waldemara.
3. Karta projektu pokazuje prowadzącego i głos; „Zmień prowadzącego lub głos komunikacji” zapisuje zmianę. Po wylogowaniu i zalogowaniu oba wracają.
4. „Opis YouTube”: „Poproś AI o nowy szkic” (emotikony domyślnie wyłączone) i „Popraw mój szkic” na własnym, niezapisanym tekście. Puste pole daje „Najpierw wpisz własny szkic.”.
5. Na karcie propozycji „Co jeszcze poprawić?” → „Popraw ponownie” (np. „popraw tylko CTA”) daje „Poprawkę nr 1”, a materiał się nie zmienia, dopóki nie klikniesz „Zastosuj”.
6. „Odrzuć” po „Popraw mój szkic” przywraca niezapisany tekst do pola.
7. Pozostałe materiały AI (post, grafika, mailingi, scenariusz) działają jak wcześniej.

Rollback: cofnąć kod; kolumny mogą zostać (są `NULL` i nic ich nie wymaga). Pełne cofnięcie: `/opt/alt/php82/usr/bin/php artisan migrate:rollback --step=2 --force` po backupie (usuwa wybrane głosy i wpisane profile).

## Asystent planowania kierunku (DEC-037, 2026-10-02)

**Bez migracji.** Opcjonalne zmienne (mają domyślne wartości w `config/growth_ai.php`):

- `GROWTH_AI_RESEARCH_MODEL` (domyślnie `gpt-5.5`) — tylko Asystent planowania,
- `GROWTH_AI_RESEARCH_TIMEOUT_SECONDS` (120),
- `GROWTH_AI_RESEARCH_REASONING_EFFORT` (medium).

Nie zmienia `GROWTH_AI_MODEL` (koncepcja i materiały zostają na `gpt-5-mini`). Konto OpenAI musi mieć dostęp do `web_search` na Responses API.

Kolejność na produkcji:

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull
/bin/bash /home/srv66127/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-mysql-nightly-backup.sh
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
```

Smoke:

1. `/growth/projects/create`: CTA „Zaplanuj webinar”, Asystent planowania, brak kart Canva z Pomysłów.
2. Przy `GROWTH_AI_ENABLED=false`: temat NotebookLM → „Przeanalizuj temat” → komunikat o symulacji, brak listy źródeł.
3. „Użyj tego kierunku” + „Utwórz projekt webinaru” wypełnia szkic kierunku; status to nie „Gotowe”.
4. Nowy projekt: puste szkice materiałów, koncepcja tylko z wpisanym tematem.
5. Istniejące AI (opis YouTube, post, mailingi, scenariusz, grafika) działa jak wcześniej.

Rollback: cofnąć kod. Brak zmian schematu bazy.

## Usuwanie projektów (DEC-038, 2026-10-03)

**Bez migracji.** `/growth/projects` listuje wszystkie kampanie właściciela. Usunięcie jest trwałe.

Smoke:

1. Lista pokazuje więcej niż jedną kampanię, jeśli są w bazie.
2. „Otwórz” na starszej kampanii pokazuje jej temat w workspace.
3. „Usuń” otwiera modal Bootstrap (nie `confirm()`). Po potwierdzeniu projekt znika z listy i z bazy.
4. Cudzej kampanii nie widać.

Rollback: cofnąć kod. Usunięte kampanie nie wracają.

## Asystent kierunku w projekcie (DEC-039, 2026-10-03)

**Bez migracji.** Na karcie „Pomysł i kierunek” otwartego projektu: „Popraw propozycję” (bez wyszukiwania) i „Popraw propozycję — szukaj w Internecie”. „Zastosuj” zapisuje szkic. Zatwierdzonego kierunku AI nie zmienia.

Smoke:

1. Nowy projekt: pod polami kierunku widać asystenta i model researchu (albo „symulacja lokalna”).
2. Przy `GROWTH_AI_ENABLED=false`: „Popraw propozycję” pokazuje kartę obok, bez listy źródeł. Pola kierunku bez zmian do „Zastosuj”.
3. „Zastosuj” wypełnia szkic. Status to nie „Gotowe”. Temat projektu bez zmian.
4. Po „Zatwierdź kierunek” asystent znika i widać „Najpierw cofnij zatwierdzenie”.

Rollback: cofnąć kod. Zapisany kierunek zostaje. Propozycje z sesji znikają po wylogowaniu.

## Koncepcja z pomysłu i kierunku (DEC-040, 2026-10-03)

**Bez migracji.** Na karcie „Koncepcja webinaru” nagłówek to „Wygeneruj lub zmień koncepcję”. Przy pustych polach lista zaczyna się od „Wygeneruj na podstawie pomysłu i kierunku”.

Smoke:

1. Projekt z uzupełnionym kierunkiem i pustą koncepcją: ta opcja jest wybrana.
2. Przy `GROWTH_AI_ENABLED=false` przycisk „Wygeneruj propozycję” pokazuje kartę obok. Temat w „Pomysł i kierunek” bez zmian do i po „Zastosuj”.
3. Bez kierunku i bez tematu komunikat prosi o uzupełnienie pomysłu. Nie ma wywołania AI.

Rollback: cofnąć kod. Zapisana koncepcja zostaje. Propozycja z sesji znika po wylogowaniu.

## Szablony mailingu głównego (DEC-049, 2026-10-04)

**Bez migracji.** Na mailingu głównym są trzy szablony i edytor Tiptap. Po `git pull`: `npm run build` (albo `npm ci && npm run build`), potem komendy cache. Mailing przypominający bez zmian.

Smoke: widać „Klasyczny PNE”, „Osobisty” i „Minimalny” oraz „Cofnij”. Przełączenie szablonu nie zapisuje się samo. Nie klikać AI, jeśli flaga jest włączona.

Rollback: cofnąć kod. Zapisany mail i klucz szablonu w JSON zostają. Starszy kod klucz ignoruje.

## Sendy PNE (DEC-050, 2026-10-05)

**Wymaga migracji** `2026_10_05_070000_add_registration_and_youtube_urls_to_growth_campaigns_table` oraz `npm run build` (JS edytora maila).

Kolejność na produkcji:

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
/bin/bash /home/srv66127/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-mysql-nightly-backup.sh
git pull
/opt/alt/php82/usr/bin/php /home/srv66127/domains/adm.pnedu.pl/pneadm/composer.phar dump-autoload -o \
  || /opt/alt/php82/usr/bin/php $(which composer) dump-autoload -o \
  || composer dump-autoload -o
/opt/alt/php82/usr/bin/php artisan migrate --force
npm ci && npm run build
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php -r 'function_exists("opcache_reset") && opcache_reset(); echo "opcache_reset done\n";'
```

Jeśli `composer` / `composer.phar` nie jest w PATH, użyj lokalnego:

```bash
/opt/alt/php82/usr/bin/php /usr/local/bin/composer dump-autoload -o
```

Diagnoza 500 na `/growth/projects/.../materials/main-mail`:

```bash
tail -n 120 storage/logs/laravel.log
git rev-parse --short HEAD
/opt/alt/php82/usr/bin/php artisan migrate:status | grep registration
ls -la app/Support/GrowthOS/MailRenderContext.php public/build/manifest.json
```

Smoke: na karcie projektu widać „Linki webinaru”. Na mailingu głównym — Sendy PNE, checkboxy zaświadczenia i oferty. Bez linku zapisów „Kopiuj HTML” jest zablokowane. Reminder ma ten sam układ. Gdy edytor nie wstanie, strona pokazuje komunikat wyjątku zamiast pustego 500.

Rollback: cofnąć kod; kolumny URL mogą zostać (`NULL`). Pełne cofnięcie migracji dopiero po backupie.

## Edytor treści maila (DEC-048, 2026-10-04)

**Bez migracji.** Na mailingu treść ma przełącznik Edycja / Kod HTML i podstawowe formatowanie.

Smoke: widać „Edycja” i „Kod HTML”. Przełączenie nie zapisuje szkicu.

Rollback: cofnąć kod. Zapisany mail zostaje.

## HTML mailingu głównego (DEC-047, 2026-10-04)

**Bez migracji.** Na materiale „Mailing główny” checkbox „Profesjonalny HTML maila” jest zaznaczony. Mailing przypominający go nie ma.

Smoke: checkbox jest włączony. Nie klikać generowania, jeśli flaga AI jest włączona.

Rollback: cofnąć kod. Zapisany mail zostaje.

## Reset dziennego limitu AI (DEC-046, 2026-10-03)

**Bez migracji.** Przy komunikacie „Dzienny limit AI został wykorzystany” jest „Zresetuj limit”. Limit obrazów ma własny przycisk.

Smoke: przycisk otwiera okno z „Anuluj” i „Zresetuj limit”. Po zatwierdzeniu komunikat znika.

Rollback: cofnąć kod. Licznik w cache zostaje do końca doby.

## Poprawka mailingu głównego (DEC-045, 2026-10-03)

**Bez migracji.** Na materiale „Mailing główny” są „Poproś AI o nowy szkic” i „Popraw mój szkic”.

Smoke: te przyciski są widoczne. Nie klikać ich, jeśli flaga AI jest włączona.

Rollback: cofnąć kod. Zapisany mail zostaje.

## Poprawka posta Facebook (DEC-044, 2026-10-03)

**Bez migracji.** Na materiale „Post Facebook” są „Poproś AI o nowy szkic” i „Popraw mój szkic”.

Smoke: te przyciski są widoczne. Nie klikać ich, jeśli flaga AI jest włączona.

Rollback: cofnąć kod. Zapisany post zostaje.

## Poprawka briefu, opisu i obrazu (DEC-043, 2026-10-03)

**Bez migracji.** Brief grafiki: „Poproś AI o nowy brief” i „Popraw mój brief”. Opis obrazu ma osobną propozycję. „Popraw ten obraz” edytuje gotowe zdjęcie.

Smoke: na materiale „Grafika główna” widać te przyciski. Nie klikać ich, jeśli flaga AI jest włączona.

Rollback: cofnąć kod. Zapisany brief i obrazy zostają.

## Logo na grafice głównej (DEC-042, 2026-10-03)

Migracja `2026_10_03_120000_add_logo_overlay_to_growth_artifact_images` dodaje `base_path`, `include_pne_logo` i `include_sponsor_logo`.

Smoke: na materiale „Grafika główna” widać logo Platformy i formularz logo sponsora. „Generuj obraz” zostawić bez klikania, jeśli flaga AI jest włączona.

Rollback: cofnąć kod i migrację. Pliki sponsorów w `storage/app/private/growth-os/logos/` zostają.

## Kierunek przy każdej poprawce koncepcji (DEC-041, 2026-10-03)

**Bez migracji.** Każda opcja „Wygeneruj lub zmień” wysyła zapisany temat i pięć pól kierunku. „Zastosuj” nie zmienia kierunku i nie cofa zatwierdzenia.

Smoke:

1. Projekt z zatwierdzonym kierunkiem i zapisaną koncepcją: opis pod listą mówi, że każda opcja dostaje pomysł z kierunkiem.
2. Po „Zastosuj” propozycji „Skróć i uprość” status kierunku zostaje „Gotowe”, a pola kierunku bez zmian.

Rollback: cofnąć kod. Zapisany kierunek i koncepcja zostają.
