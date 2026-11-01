# Synchronizacja PROD → DEV (kod, bazy, pliki)

Data: 2026-11-01
Status: wdrożone w repo i przetestowane na produkcji w trybie **tylko odczyt**. Pierwszy właściwy import lokalnej bazy: do wykonania przez Waldemara (patrz „Pierwsze uruchomienie”).

Jedna komenda `dev-pull` na każdym komputerze deva (dwa laptopy, stacjonarny). Kierunek jest **zawsze tylko w dół**: z produkcji na dev. Skrypt niczego na produkcji nie zapisuje, nie kasuje i nie uruchamia w aplikacjach.

## Co robi

| Komenda | Efekt |
|---------|-------|
| `dev-pull` (= `--code`) | raport wersji i migracji, `git pull --ff-only` w `pneadm` i `pnedu` (tylko przy czystym drzewie), `composer`/`npm install` gdy zmienił się lockfile, lokalne `migrate` |
| `dev-pull --db` | najnowszy **kompletny** zestaw czterech zrzutów z produkcji → import do lokalnego MySQL (`pneadm`, `certgen`, `pne_analytics`, `pnedu`) → `migrate` |
| `dev-pull --files` | `rsync` `storage/app` obu aplikacji (bez `public/certificates`) |
| `dev-pull --all` | kod + baza + pliki |
| `--delete-files` | lustro: kasuje lokalne pliki, których nie ma na produkcji (domyślnie nie) |
| `--dry-run` | tylko raport, nic nie zmienia |
| `--yes` | bez pytań (automatyzacja) |
| `--scratch` | test importu do baz `*_scratch`, bez ruszania właściwych baz |

Skrypt: `scripts/dev-sync/dev-pull.sh`. Zalecany alias: `alias dev-pull='~/WEB-APP/pneadm/scripts/dev-sync/dev-pull.sh'`.

## Gdy repo jest przed produkcją

Zrzut produkcji ma schemat z wersji kodu wdrożonej na prod, a tabela `migrations` w zrzucie mówi, co już wykonano. Po imporcie lokalne `migrate` dokłada tylko brakujące migracje. Kolejność jest stała: **kod → import → migrate (pneadm, potem pnedu)**.

- Raport przed zmianami pokazuje wersje (HEAD lokalny i produkcyjny, ile commitów przed/za) oraz migracje z repo, których produkcja jeszcze nie ma.
- Lokalne `migrate` na prawdziwych danych jest próbą generalną wdrożenia: jeśli migracja padnie lokalnie, produkcja jest bezpieczna.
- Dane utworzone tylko lokalnie (kliknięte w panelu, a nieprzeniesione migracją/seederem) **znikają** przy imporcie. Przed każdym importem powstaje snapshot dotychczasowych lokalnych baz w `~/pne-dev-sync/snapshots/` (ostatnie 3 zestawy). Cofnięcie: komenda wypisywana na końcu importu.
- Pliki z `rsync` bez `--delete` nie kasują plików, które masz tylko lokalnie. Baza i pliki powinny być odświeżane razem (`--all`), bo wiersze w bazie odwołują się do plików.

## Bezpieczeństwo produkcji i kluczy

1. **Osobny klucz SSH na każdy komputer** (`~/.ssh/pne_devsync_ed25519`, ed25519, z hasłem). Klucz prywatny nie opuszcza komputera. Na serwer trafia tylko klucz publiczny.
2. **Klucz ograniczony po stronie serwera** (`command=` w `~/.ssh/authorized_keys`, plus `no-pty,no-port-forwarding,no-agent-forwarding,no-X11-forwarding`). Zamiast powłoki uruchamia się wyłącznie `~/bin/pne-devsync-readonly.sh`. Zweryfikowane na prodzie: `ls`, `cat`, powłoka i tunel do MySQL są odrzucane.
3. **Skrypt serwerowy `docs/deploy/scripts/prod-devsync-readonly.sh`** dopuszcza tylko: `ping`, `list` (zrzuty), `cat <zrzut>` (nazwa walidowana wzorcem), `heads` (HEAD gita), `rsync --server --sender` dla dwóch ścieżek `storage/app`. Biała lista znaków i opcji, brak `eval`, limit prędkości 4 MB/s, `nice`, serwer sam pomija `.env*`, `*.pem`, `*.key`, `*credential*`, `*service-account*`. Każde żądanie trafia do `~/backups/devsync-access.log`. Test lokalny: `bash scripts/dev-sync/test-prod-readonly.sh` (35 sprawdzeń).
4. **Skrypt serwerowy wgrywamy ręcznie** do `~/bin/` (poza aplikacjami i poza gitem). `git pull` na prodzie go nie zmienia. Po zmianie w repo trzeba go wgrać ponownie i sprawdzić sumę `sha256sum` (stan z 2026-11-01: `647ebfd9fc3760b2b3618f7d8b4c58dec4f2466a53a2c3e3e98dff3094f981ce`).
5. **Zrzuty i snapshoty poza repo**: `~/pne-dev-sync/` (700, pliki 600). Zawierają dane osobowe. Laptopy powinny mieć szyfrowany dysk.
6. **Kolejka, sesje i cache z produkcji nie trafiają do lokalnej bazy** (filtr w strumieniu importu: `jobs`, `job_batches`, `failed_jobs`, `sessions`, `cache`, `cache_locks`). Zadania z produkcji nie mogą się wykonać lokalnie.
7. **Bezpieczniki lokalnego `.env` przed importem** (`--db`):
   - `APP_ENV=local`, `DB_HOST` lokalny, inaczej odmowa;
   - poczta tylko `log`/`array` albo `smtp` → Mailpit, inaczej odmowa;
   - `PAYU_SANDBOX=false` albo `PAYNOW_SANDBOX=false` w lokalnym `.env` → **odmowa** (po imporcie prawdziwych zamówień groziłaby prawdziwa płatność). Świadomy wyjątek: `ALLOW_LIVE_PAYMENTS=1` w `~/.config/pne/dev-pull.env`;
   - klucze ClickMeeting, Sendy, Publigo, iFirma w lokalnym `.env` → ostrzeżenie (akcje w panelu mogą trafić do prawdziwych kont).
8. Import wymaga potwierdzenia słowem `TAK` (poza `--yes`).
9. Skrypt używa jednego połączenia SSH (ControlMaster) i ponawia próby (3 × z przerwą), bo serwer SeoHost (OpenSSH z `PerSourcePenalties`) chwilowo odrzuca serię szybkich połączeń z tego samego IP komunikatem `Not allowed at this time`.

## Pliki: dlaczego bez certyfikatów

`storage/app` w `pneadm` ma 10 933 plików i **15,2 GB**, z czego **15 GB to `public/certificates`** (wygenerowane PDF-y). Reszta to ok. 160 MB (`courses`, `articles`, `instructors`, `training-offers`, `private`, `exports`). Domyślnie pobieramy resztę (994 pliki, 158 MB). `pnedu` ma 107 KB. **Certyfikatów na deva nie pobieramy nigdy** (decyzja Waldemara 2026-11-01; skrypt nie ma na to flagi): PDF można wygenerować ponownie z danych w bazie, a lokalnie nie ma potrzeby ich mieć. Agent AI nie dodaje flagi ani nie pobiera ich ręcznie.

## Konfiguracja nowego komputera (krok po kroku)

**Dla agenta AI jest gotowy runbook z podziałem „agent / człowiek”: [`DEV_SYNC_NEW_COMPUTER.md`](./DEV_SYNC_NEW_COMPUTER.md)** (zawiera prompt do wklejenia w Cursorze na nowym komputerze). Poniżej ta sama procedura w skrócie.

Na każdym z trzech komputerów, w WSL:

1. Sklonuj/zaktualizuj `pneadm` i `pnedu`, uruchom Sail (`sail up -d` w obu projektach).
2. Wygeneruj klucz: `ssh-keygen -t ed25519 -f ~/.ssh/pne_devsync_ed25519 -C "pne-devsync-$(hostname)"` (ustaw hasło).
3. W terminalu SeoHost (DirectAdmin → Informacje o systemie i pliki → Terminal): kopia `cp -p ~/.ssh/authorized_keys ~/.ssh/authorized_keys.bak-DATA`, potem dopisz **nową** linię (nie zmieniaj istniejących):

   ```text
   command="/bin/bash /home/srv66127/bin/pne-devsync-readonly.sh",no-pty,no-port-forwarding,no-agent-forwarding,no-X11-forwarding ssh-ed25519 AAAA...(klucz publiczny z .pub) pne-devsync-NAZWA
   ```

   Nie używaj „Klucze SSH → Wklej klucz” w DirectAdmin: to dodałoby klucz **bez** ograniczenia. Uwaga: operacje „Autoryzacja/Usuń” w tym panelu mogą przepisać `authorized_keys`, więc po nich sprawdź, czy linia nadal jest.
4. Skopiuj konfigurację: `mkdir -p ~/.config/pne && cp scripts/dev-sync/dev-pull.env.example ~/.config/pne/dev-pull.env && chmod 600 ~/.config/pne/dev-pull.env` i wpisz `SSH_PORT` (port z maila SeoHost o aktywacji SSH).
5. **Zaufaj kluczowi serwera SeoHost (raz na komputer).** Skrypt używa ścisłego sprawdzania (`StrictHostKeyChecking=yes`), więc na nowym komputerze pierwsza próba kończy się `No ED25519 host key is known … Host key verification failed`. Nie akceptuj klucza na ślepo, tylko porównaj odcisk z komputerem, który już się łączył (`ssh-keygen -F "[h30.seohost.pl]:PORT" | grep -v '^#' | ssh-keygen -lf -`, wiersz ED25519), a potem dopisz klucz do `~/.ssh/known_hosts` tylko przy zgodnym odcisku:

   ```bash
   . ~/.config/pne/dev-pull.env
   EXPECTED='SHA256:…odcisk ED25519 z zaufanego komputera…'
   ssh-keyscan -p "$SSH_PORT" -t ed25519 "$SSH_HOST" 2>/dev/null > /tmp/h30.key
   GOT=$(ssh-keygen -lf /tmp/h30.key 2>/dev/null | awk '{print $2}')
   [ -n "$GOT" ] && [ "$GOT" = "$EXPECTED" ] && cat /tmp/h30.key >> ~/.ssh/known_hosts && echo ZGODNE || echo "NIC nie dopisano"
   rm -f /tmp/h30.key
   ```

   Komendę wykonuj w terminalu **WSL na dev-komputerze**, nie w terminalu SeoHost. Odcisk jest publiczny (nie jest sekretem), ale źródłem jest komputer, który zaakceptował klucz przy pierwszym połączeniu, więc to jest porównanie, a nie weryfikacja u źródła.
6. Uruchom agenta SSH pod stałą ścieżką i odblokuj klucz (hasło wpisuje człowiek; `dev-pull` sam używa `~/.ssh/pne-agent.sock`, gdy `SSH_AUTH_SOCK` jest pusty):

   ```bash
   export SSH_AUTH_SOCK=~/.ssh/pne-agent.sock
   ssh-add -l >/dev/null 2>&1; [ $? -eq 2 ] && { rm -f "$SSH_AUTH_SOCK"; ssh-agent -a "$SSH_AUTH_SOCK" >/dev/null; }
   ssh-add -t 14400 ~/.ssh/pne_devsync_ed25519   # 4 godziny
   ```

   `export` działa tylko w tym oknie terminala. W nowym oknie wpisz go ponownie albo jeszcze raz odblokuj klucz.
7. Test: `dev-pull --dry-run --all`.
8. Lokalne `.env` musi mieć **`PAYU_SANDBOX=true` i `PAYNOW_SANDBOX=true` w `pneadm` i w `pnedu`**. Inaczej import zatrzyma się przy sprawdzaniu bezpieczników (`BŁĄD: Lokalne płatności są w trybie PRODUKCYJNYM`), zanim cokolwiek zmieni: `sed -i -E 's/^(PAYU_SANDBOX)=.*/\1=true/; s/^(PAYNOW_SANDBOX)=.*/\1=true/' pneadm/.env pnedu/.env`.
9. Pierwszy import: `dev-pull --all` (bez `--yes`, potwierdzasz `TAK`; przed zastąpieniem baz powstaje snapshot).
10. Powiadomienia o końcu zadania agenta (dźwięk + telefon): [`DEV_NOTIFICATIONS.md`](./DEV_NOTIFICATIONS.md).

Odcięcie zgubionego laptopa: usuń jego jedną linię z `~/.ssh/authorized_keys` na SeoHost.

## Pierwsze uruchomienie

1. Ustaw w lokalnym `pnedu/.env`: `PAYU_SANDBOX=true` i `PAYNOW_SANDBOX=true` (oraz klucze sandboxowe, jeśli ich używasz).
2. `ssh-add -t 14400 ~/.ssh/pne_devsync_ed25519`.
3. `dev-pull --all --dry-run`, potem `dev-pull --db` (lub `--all`).

## Rozwiązywanie problemów

| Objaw | Przyczyna / działanie |
|-------|------------------------|
| `Not allowed at this time` lub `Connection closed` przed logowaniem | chwilowy limit serwera po serii połączeń; odczekaj kilka minut (do ok. 10), nie ponawiaj w pętli |
| `Permission denied (publickey)` | klucz nie jest w agencie (`ssh-add`), albo linia w `authorized_keys` została przepisana przez DirectAdmin |
| `odmowa (…)` ze skryptu serwerowego | polecenie spoza białej listy; zgłoś, nie obchodź |
| Import przerwany błędem składni SQL | zrzut zmienił format; zgłoś, w razie potrzeby poprawimy filtr w `import_dump` |
| Brak migracji w raporcie (`nie wykryto tabeli migrations`) | zmieniony format zrzutu |

## Dla agentów AI (Cursor i inni) — zasady obowiązkowe

Ten rozdział czytaj, zanim cokolwiek zrobisz z synchronizacją, produkcją albo kluczami SSH.

1. **Synchronizację dev robisz wyłącznie przez `scripts/dev-sync/dev-pull.sh`.** Nie łącz się z produkcją inaczej: nie używaj pełnego klucza SSH, nie rób `mysqldump` na prodzie, nie odpalaj ręcznie `rsync`/`scp` z innymi opcjami. Kierunek jest tylko prod → dev. **Nic nie zapisuj na produkcji** (poza ręcznymi krokami, które wykonuje człowiek w terminalu DirectAdmin).
2. **Sekrety.** Nigdy nie czytaj, nie wypisuj, nie kopiuj ani nie wklejaj do czatu, dokumentów i commitów: zawartości `~/.ssh/`, `~/.config/pne/`, plików `.env`, zrzutów z `~/pne-dev-sync/` ani wartości kluczy API. Numer portu SSH też nie trafia do repo. Przy diagnozie `.env` pokazuj tylko nazwy kluczy i informację „ustawione/puste”.
3. **Hasło do klucza SSH zna tylko człowiek.** Klucz odblokowuje człowiek w agencie (`ssh-add -t …`). Gdy wygasł, poproś o ponowne `ssh-add`; nie próbuj go obchodzić.
4. **`dev-pull --db` zastępuje lokalne bazy** (z lokalnym snapshotem). Uruchamiaj tylko po wyraźnej zgodzie użytkownika; `--yes` tylko wtedy. Nigdy `migrate:fresh`, `migrate:refresh`, `db:wipe` (patrz [`DATA_SAFETY.md`](../DATA_SAFETY.md)).
5. **Nie edytuj `~/.ssh/authorized_keys` na produkcji.** To robi człowiek w terminalu DirectAdmin według sekcji „Konfiguracja nowego komputera”. Nie dodawaj kluczy bez ograniczenia `command=`.
6. **Błąd `Not allowed at this time` lub `Connection closed` przed logowaniem** to chwilowy limit serwera, a nie błąd klucza. Odczekaj kilka minut. Nie ponawiaj w pętli, bo kary się wydłużają.
7. **Nie osłabiaj zabezpieczeń.** Skrypt serwerowy ma białą listę poleceń, opcji `rsync` i ścieżek. Rozszerzasz ją tylko po decyzji Waldemara, po teście `scripts/dev-sync/test-prod-readonly.sh` i z aktualizacją sumy SHA w tym dokumencie. Bezpieczniki lokalnego `.env` w `dev-pull.sh` (płatności, poczta, `APP_ENV`) nie wyłączamy.
8. **Lokalne dane są prawdziwe** (dane osobowe klientów). Z Mailpit i sandboxem płatności pracujesz, ale nie wysyłaj nic na zewnątrz i nie klikaj akcji powiązanych z ClickMeeting, Sendy, Publigo ani iFirma bez wyraźnej zgody. Dopóki lokalne klucze API nie zostaną rozdzielone (patrz „Odłożone”), te integracje mogą dotknąć prawdziwych kont.

### Co warto wiedzieć o implementacji (pułapki, na które już wpadliśmy)

- Zrzuty z nocnego backupu mają **INSERT-y wielolinijkowe** (`INSERT INTO tabela VALUES` + wiersze do linii kończącej się `);`). Filtry w `import_dump` i `migration_diff` na to liczą. Jeśli format zrzutu się zmieni, import zgłosi błąd składni SQL.
- Nazwy migracji bywają bez segmentu z godziną (np. `2024_08_22_create_webhook_logs_table`). Raport migracji uwzględnia oba formaty.
- Po `cmd | sed || true` zmienna `PIPESTATUS` odnosi się do `true`, nie do `cmd`. W `rsync_pull` kod wyjścia czytamy po wyłączeniu `set -e`.
- `rsync` z `--info=…` jest odrzucany przez skrypt serwerowy (nie ma go na białej liście). Używamy `--stats`.
- Skrypt używa jednego połączenia SSH (ControlMaster). `ssh -O exit` wywołuje się na końcu.

## Aktualizacja skryptu serwerowego (`~/bin/pne-devsync-readonly.sh`)

Skrypt leży na prodzie poza gitem i **nie aktualizuje się przy `git pull`**. Zmiana w repo wymaga ręcznego wgrania:

1. Test lokalny: `bash scripts/dev-sync/test-prod-readonly.sh` (wszystko ma być OK).
2. W Cursorze otwórz `docs/deploy/scripts/prod-devsync-readonly.sh`, zaznacz całość i skopiuj.
3. W terminalu DirectAdmin: `set +H`, potem `cat > ~/bin/pne-devsync-readonly.sh <<'PNE_EOF'`, wklej treść, w nowej linii `PNE_EOF`.
4. **Pułapka wklejania:** terminal w przeglądarce dokleja na początku pliku 6 bajtów `ESC[200~` (znacznik wklejania), a niepotrzebny Enter przed `PNE_EOF` dodaje pustą linię na końcu. Napraw:

   ```bash
   sed -i -e '1s/^\x1b\[200~//' -e '${/^$/d}' ~/bin/pne-devsync-readonly.sh
   chmod 700 ~/bin/pne-devsync-readonly.sh
   wc -c ~/bin/pne-devsync-readonly.sh; sha256sum ~/bin/pne-devsync-readonly.sh; bash -n ~/bin/pne-devsync-readonly.sh && echo SKLADNIA-OK
   ```
5. Porównaj `sha256sum` z lokalnym (`sha256sum docs/deploy/scripts/prod-devsync-readonly.sh`). Gdy się różni, porównaj plik linia po linii: `while IFS= read -r l; do printf '%s' "$l" | cksum | cut -d' ' -f1; done < plik | nl -ba`.
6. Zaktualizuj sumę SHA w sekcji „Bezpieczeństwo produkcji i kluczy” (punkt 4).

## Odłożone (świadomie, decyzja Waldemara 2026-11-01)

- **Rozdzielenie kluczy API dev i prod.** Dziś lokalne `.env` mogą zawierać klucze produkcyjnych usług (ClickMeeting, Sendy, Publigo; w `pnedu` także PayU/PayNow, ale tryb sandbox jest włączony lokalnie). Plan: dev dostaje klucze testowe albo puste, a na produkcji z czasem trafią nowo wygenerowane, nieujawnione klucze (rotacja). Do tego czasu obowiązuje punkt 8 z zasad dla agentów AI. Uwaga do rotacji: wartości kluczy Sendy z `.env.example` są w historii gita, więc usunięcie ich z pliku nie wystarczy, trzeba je wymienić u dostawcy.
- **Twardsza blokada integracji w `dev-pull`** (odmowa, gdy lokalny `.env` wskazuje produkcyjne URL-e ClickMeeting/Sendy/Publigo). Dziś jest tylko ostrzeżenie.

## Powiązane

- Powiadomienie o końcu zadania agenta (dźwięk + telefon, per komputer): [`DEV_NOTIFICATIONS.md`](./DEV_NOTIFICATIONS.md)
- Backup produkcji (źródło zrzutów): [`MYSQL_BACKUP.md`](./MYSQL_BACKUP.md)
- Ścieżki produkcyjne: [`PRODUCTION_PATHS.md`](./PRODUCTION_PATHS.md)
- Bezpieczeństwo baz: [`../DATA_SAFETY.md`](../DATA_SAFETY.md)
