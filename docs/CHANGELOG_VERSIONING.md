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
- **Nowy numer** (1.1, 1.2, …) — Cursor **sugeruje** po znaczącym etapie; **Waldemar potwierdza**.
- **Hotfix** — dopisujemy do bieżącej wersji, bez nowego numeru (też po potwierdzeniu, gdy nieoczywiste).

## Panel

Menu **Konto** bez zmian (profil, wylogowanie).

Poniżej, po poziomej linii, dwa główne punkty menu:

- `adm.pnedu.pl v …`
- `pnedu.pl v …`

Strony: `/changelog/adm`, `/changelog/pnedu`.

Na produkcji ADM czyta changelog pnedu ze ścieżki:

```text
PNEDU_CHANGELOG_PATH=/home/srv66127/domains/pnedu.pl/app/CHANGELOG.md
```

Lokalnie, gdy zmienna pusta: `../pnedu/CHANGELOG.md` obok katalogu `pneadm`.

## Deploy

Brak migracji. Po `git pull` obu repo: `optimize:clear` na `pneadm` (i na `pnedu`, jeśli wrzucasz tam `CHANGELOG.md`).
