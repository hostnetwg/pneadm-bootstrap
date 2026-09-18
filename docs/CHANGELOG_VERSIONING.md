# Wersjonowanie i historia zmian (ADM + pnedu.pl)

Data: 2026-09-18  
Status: obowiązujące

## Cel

Krótka historia wersji w gitcie — dla zespołu i AI na różnych komputerach. **Dokumentacja w `docs/` zostaje kanonem.** Changelog nie zastępuje runbooków.

Widok tylko w panelu ADM, dla każdego zalogowanego. **Bez wpisu na froncie pnedu.pl.**

## Źródło prawdy

| Aplikacja | Plik | Numer w menu |
|-----------|------|----------------|
| adm.pnedu.pl | `pneadm/CHANGELOG.md` | pierwsza sekcja `## x.y` |
| pnedu.pl | `pnedu/CHANGELOG.md` | to samo |

Numery obu aplikacji są **niezależne**.

## Zasady wpisów

- Start: **1.0** (18.09.2026) — bez odtwarzania całej historii wstecz.
- Format nagłówka: `## 1.0 — 2026-09-18` (najnowsza wersja na górze).
- Kilka zdań po polsku + link do kanonu w `docs/`.
- Gdy wpis dotyczy ekranu w panelu ADM, daj markdown `[nazwa menu](/ścieżka)` (np. `[Lista ClickMeeting](/clickmeeting/trainings)`). W widoku historii to jest link w **nowej karcie**. Ścieżki `docs/` zostają jako kod, nie jako klikalny URL.
- **Nowy numer** (1.1, 1.2, …) — Cursor **sugeruje** po znaczącym etapie; **Waldemar potwierdza** przed wpisem. Po potwierdzeniu numeru Cursor **pyta, czy wysłać mail** do aktywnych administratorów i superadministratorów (`changelog:notify-admins {adm|pnedu}`). Nie wysyłać przy hotfixie i nigdy samemu bez „tak”.
- **Hotfix** — dopisujemy do bieżącej wersji, bez nowego numeru (też po potwierdzeniu, gdy nieoczywiste). Bez maila — wystarczy czerwone kółko w menu.

## Panel

Menu **Konto** bez zmian (profil, wylogowanie).

Poniżej, po poziomej linii, dwa główne punkty menu:

- `adm.pnedu.pl v …`
- `pnedu.pl v …`

Przy każdej pozycji — czerwone kółko z liczbą punktów historii, których dany operator jeszcze nie otworzył. Wejście w `/changelog/adm` gasi kółko ADM, wejście w `/changelog/pnedu` gasi kółko pnedu. Stan w `users.preferences.changelog_seen` (konto, nie ciasteczko). Przy pierwszym logowaniu widać od razu liczbę z bieżącego changelogu.

Strony: `/changelog/adm`, `/changelog/pnedu`.

Na produkcji ADM czyta changelog pnedu ze ścieżki:

```text
PNEDU_CHANGELOG_PATH=/home/srv66127/domains/pnedu.pl/app/CHANGELOG.md
```

Lokalnie, gdy zmienna pusta: `../pnedu/CHANGELOG.md` obok katalogu `pneadm`.

## Deploy

Brak migracji. Po `git pull` obu repo: `optimize:clear` na `pneadm` (i na `pnedu`, jeśli wrzucasz tam `CHANGELOG.md`).

Mail o nowej wersji (nie hotfix):

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan changelog:notify-admins adm --dry-run
/opt/alt/php82/usr/bin/php artisan changelog:notify-admins adm
```

To samo z `pnedu` zamiast `adm`, gdy nowy numer jest po stronie frontu. Odbiorcy: aktywni `admin` i `super_admin` z [listy użytkowników](https://adm.pnedu.pl/admin/users).
