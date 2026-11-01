# Konfiguracja nowego komputera deva: runbook dla agenta AI

Data: 2026-11-01 · Sprawdzone na dwóch komputerach (PNE-YOGA, LAPTOP-33SJB4MI) · Opis ogólny: [`DEV_SYNC.md`](./DEV_SYNC.md)

Ten dokument przeprowadza agenta AI (Cursor) przez konfigurację `dev-pull` i powiadomień na nowym komputerze (WSL2 + Docker + Sail). Agent robi sam wszystko, co może. Człowiek (Waldemar) robi **tylko** kroki oznaczone **[CZŁOWIEK]**, czyli to, czego agent nie może i nie powinien robić (hasło do klucza, terminal SeoHost, port SSH).

## Prompt do wklejenia w Cursorze na nowym komputerze

> Przeczytaj `pneadm/docs/deploy/DEV_SYNC_NEW_COMPUTER.md` oraz sekcję „Dla agentów AI” w `pneadm/docs/deploy/DEV_SYNC.md`. Przeprowadź konfigurację tego komputera dokładnie według runbooka. Rób sam wszystko, co możesz. Mnie wołaj tylko przy krokach [CZŁOWIEK], jeden krok na raz, z gotową komendą. Nie czytaj kluczy prywatnych, `~/.config/pne/` ani `.env` (pokazuj tylko nazwy kluczy i „ustawione/puste”).

## Zasady (obowiązują agenta w całym runbooku)

- Nigdy nie czytaj ani nie wypisuj: `~/.ssh/` (poza plikiem **`.pub`**, który jest publiczny), `~/.config/pne/`, `~/.config/cursor-notify/`, plików `.env`, zrzutów z `~/pne-dev-sync/`. Nie pytaj o hasło do klucza i nie wpisuj go. Hasło zna tylko człowiek.
- Nigdy nie łącz się z produkcją inaczej niż przez `scripts/dev-sync/dev-pull.sh`. Nic nie zapisuj na produkcji.
- Nie wklejaj numeru portu SSH do repozytorium ani do plików w repo.
- Import baz (`--db`, `--all` bez `--dry-run`) **zastępuje lokalne bazy**. Uruchamiaj go tylko po wyraźnej zgodzie człowieka w czacie (np. „importuj”), z `--yes`. Przed zastąpieniem skrypt robi snapshot.
- Jeden krok na raz, najpierw sprawdź wynik, potem następny.

## Faza A: przygotowanie (agent)

1. `cd ~/WEB-APP/pneadm && git pull origin main` oraz to samo w `~/WEB-APP/pnedu`. Jeśli repozytoria nie istnieją, sklonuj je (`git clone git@github.com:hostnetwg/pneadm-bootstrap.git pneadm`, `git clone git@github.com:hostnetwg/pnedu-bootstrap.git pnedu` w `~/WEB-APP`). Jeśli `git pull` zgłasza lokalne zmiany, zatrzymaj się i zapytaj.
2. Sprawdź, czy kontenery Sail działają: `docker ps --format '{{.Names}}'` ma pokazać m.in. `pneadm-mysql`. Jeśli nie: `./vendor/bin/sail up -d` w `pneadm` i w `pnedu`.
3. Sprawdź stan (bez czytania zawartości): czy istnieją `~/.ssh/pne_devsync_ed25519`, `~/.ssh/pne_devsync_ed25519.pub`, `~/.config/pne/dev-pull.env` (`test -e`). Zapamiętaj, których brakuje.
4. Uruchom agenta SSH pod stałą ścieżką (bezpieczne, bez hasła):

   ```bash
   export SSH_AUTH_SOCK=~/.ssh/pne-agent.sock
   ssh-add -l >/dev/null 2>&1; [ $? -eq 2 ] && { rm -f "$SSH_AUTH_SOCK"; ssh-agent -a "$SSH_AUTH_SOCK" >/dev/null; }
   ```

## Faza B: klucz SSH dla tego komputera

5. **[CZŁOWIEK]**, tylko jeśli brak klucza (krok A.3). Agent podaje komendę do wklejenia w terminalu WSL; człowiek wpisuje hasło do klucza dwa razy (nie wklejać agentowi):

   ```bash
   mkdir -p ~/.ssh && chmod 700 ~/.ssh
   [ -e ~/.ssh/pne_devsync_ed25519 ] && echo "KLUCZ JUZ ISTNIEJE" || ssh-keygen -t ed25519 -f ~/.ssh/pne_devsync_ed25519 -C "pne-devsync-$(hostname)"
   ```

6. Agent wyświetla **klucz publiczny** (`cat ~/.ssh/pne_devsync_ed25519.pub`) i buduje z niego gotową linię dla SeoHost, podstawiając klucz i komentarz:

   ```text
   command="/bin/bash /home/srv66127/bin/pne-devsync-readonly.sh",no-pty,no-port-forwarding,no-agent-forwarding,no-X11-forwarding ssh-ed25519 AAAA…(klucz z .pub) pne-devsync-NAZWA_KOMPUTERA
   ```

7. **[CZŁOWIEK]**: w DirectAdmin → terminal na seohost.pl wkleja (agent przygotowuje całość z podstawionym kluczem):

   ```bash
   cp -p ~/.ssh/authorized_keys ~/.ssh/authorized_keys.bak-$(date +%Y%m%d-%H%M)
   [ -n "$(tail -c1 ~/.ssh/authorized_keys)" ] && echo >> ~/.ssh/authorized_keys
   printf '%s\n' '<GOTOWA LINIA Z KROKU 6>' >> ~/.ssh/authorized_keys
   awk '{print NR": komentarz klucza = " $NF}' ~/.ssh/authorized_keys
   ```

   Oczekiwane: nowy wiersz z komentarzem `pne-devsync-NAZWA_KOMPUTERA`, istniejące wiersze bez zmian. Nie używać „Klucze SSH → Wklej klucz” w DirectAdmin (dodaje klucz bez ograniczenia).

## Faza C: konfiguracja lokalna

8. Agent kopiuje plik konfiguracji, jeśli go brak: `mkdir -p ~/.config/pne && cp ~/WEB-APP/pneadm/scripts/dev-sync/dev-pull.env.example ~/.config/pne/dev-pull.env && chmod 600 ~/.config/pne/dev-pull.env`.
9. **[CZŁOWIEK]**: wpisuje port SSH (z maila SeoHost o aktywacji SSH), wartość nie trafia do repo ani do czatu:

   ```bash
   read -rp "Port SSH SeoHost (z maila): " P && sed -i "s/^SSH_PORT=.*/SSH_PORT=\"$P\"/" ~/.config/pne/dev-pull.env && unset P
   ```

   Agent sprawdza tylko, że jest ustawiony: `grep -c '^SSH_PORT="[0-9][0-9]*"' ~/.config/pne/dev-pull.env` (ma być `1`).
10. Agent ufa kluczowi serwera **z weryfikacją odcisku** (skrypt ma ścisłe sprawdzanie, więc bez tego kończy się `Host key verification failed`). Odcisk ED25519 serwera `h30.seohost.pl` (publiczny, nie jest sekretem):

    ```bash
    . ~/.config/pne/dev-pull.env
    EXPECTED='SHA256:Iur9IkDT8iiJzT4KcQ9JS9fBN3gJPic+utj1QaC5Ke4'
    ssh-keyscan -p "$SSH_PORT" -t ed25519 "$SSH_HOST" 2>/dev/null > /tmp/h30.key
    GOT=$(ssh-keygen -lf /tmp/h30.key 2>/dev/null | awk '{print $2}')
    echo "oczekiwany: $EXPECTED"; echo "otrzymany:  ${GOT:-(pusty)}"
    [ -n "$GOT" ] && [ "$GOT" = "$EXPECTED" ] && cat /tmp/h30.key >> ~/.ssh/known_hosts && echo ZGODNE || echo "NIC nie dopisano"
    rm -f /tmp/h30.key
    ```

    Przy `NIEZGODNE` zatrzymaj się i powiedz człowiekowi (możliwa ingerencja w sieci albo zmiana klucza serwera). Przy pustym wyniku odczekaj 10 minut (chwilowy limit serwera).
11. Agent ustawia płatności lokalnie na sandbox (bez wypisywania sekretów): `sed -i -E 's/^(PAYU_SANDBOX)=.*/\1=true/; s/^(PAYNOW_SANDBOX)=.*/\1=true/' ~/WEB-APP/pneadm/.env ~/WEB-APP/pnedu/.env`, potem `grep -nE '^(PAYU_SANDBOX|PAYNOW_SANDBOX)=' ~/WEB-APP/pneadm/.env ~/WEB-APP/pnedu/.env` (ma być 4 × `=true`).

## Faza D: odblokowanie klucza i test

12. **[CZŁOWIEK]**: w terminalu WSL odblokowuje klucz (hasło wpisuje tylko w terminalu). Zwróć uwagę na `SSH_AUTH_SOCK` w tej samej linii: agent AI używa tego samego gniazda:

    ```bash
    SSH_AUTH_SOCK=~/.ssh/pne-agent.sock ssh-add -t 14400 ~/.ssh/pne_devsync_ed25519
    ```

    Oczekiwane: `Identity added … Lifetime set to 14400 seconds`. Klucz działa 4 godziny.
13. Agent weryfikuje: `SSH_AUTH_SOCK=~/.ssh/pne-agent.sock ssh-add -l` (ma pokazać `pne-devsync-NAZWA_KOMPUTERA`), potem test:

    ```bash
    cd ~/WEB-APP/pneadm && ./scripts/dev-sync/dev-pull.sh --all --dry-run
    ```

    Oczekiwane: `OK (srv66127@h30.seohost.pl:…)`, wersje kodu, zestaw 4 zrzutów, `Number of files` dla `pneadm` (ok. 994) i `pnedu` (15). Przy `Not allowed at this time` odczekaj 5 do 10 minut (to limit serwera, nie błąd klucza).

## Faza E: pierwszy import i powiadomienia

14. Agent prosi człowieka o zgodę na import (zastępuje lokalne bazy `pneadm`, `certgen`, `pne_analytics`, `pnedu`; poprzedni stan trafia do snapshotu w `~/pne-dev-sync/snapshots`). Po wyraźnym „importuj” uruchamia:

    ```bash
    cd ~/WEB-APP/pneadm && ./scripts/dev-sync/dev-pull.sh --all --yes
    ```

    Oczekiwane na końcu: `Gotowe: lokalna baza = produkcja z DATA + lokalne migracje.` Sprawdź też, że `http://localhost:8083/login` i `http://localhost:8081/` zwracają kod 200 (`curl -s -o /dev/null -w '%{http_code}'`).
15. Agent instaluje powiadomienia (dźwięk + telefon): `cd ~/WEB-APP/pneadm && scripts/dev-notify/install.sh Cursor_powiadomienia --test` (opis w [`DEV_NOTIFICATIONS.md`](./DEV_NOTIFICATIONS.md)). Na telefonie ma przyjść „Cursor: gotowe (NAZWA_KOMPUTERA, pneadm)”. Człowiek potwierdza, że przyszło.
16. Agent aktualizuje tabelę poniżej (nazwa komputera, data) i podsumowuje: co zrobione, co zostało. Zmiany w repozytorium (tylko tabela) commituje dopiero na prośbę człowieka.

## Typowe problemy

| Objaw | Przyczyna i rozwiązanie |
|-------|-------------------------|
| `No ED25519 host key is known … Host key verification failed` | brak klucza serwera w `known_hosts`: krok C.10 |
| `Not allowed at this time` / `Connection closed` | chwilowy limit serwera (kary za serię połączeń); odczekaj 5 do 10 minut, nie ponawiaj w pętli |
| `Permission denied (publickey)` | linii klucza nie ma w `authorized_keys` na SeoHost (np. nadpisało ją „Autoryzacja/Usuń” w DirectAdmin) albo klucz nie jest odblokowany: faza B.7 i D.12 |
| `BŁĄD: Lokalne płatności są w trybie PRODUKCYJNYM` | brak `*_SANDBOX=true` w `.env`: krok C.11 |
| `niezacommitowane zmiany — NIE robię pull` | lokalna zmiana w repo (np. `.phpunit.result.cache`); przywróć plik lub zacommituj |
| Skrypt nie widzi klucza w agencie | `SSH_AUTH_SOCK` w innym oknie terminala; użyj stałego gniazda (krok A.4 i D.12) |

## Komputery skonfigurowane

| Komputer | Data | Uwagi |
|----------|------|-------|
| PNE-YOGA | 2026-11-01 | pierwsza konfiguracja, import wykonany |
| LAPTOP-33SJB4MI | 2026-11-01 | import wykonany, powiadomienia zainstalowane |
| komputer stacjonarny | (do konfiguracji) | według tego runbooka |

Odcięcie zgubionego komputera: usuń jego jedną linię z `~/.ssh/authorized_keys` na SeoHost (wiersz z komentarzem `pne-devsync-NAZWA`).
