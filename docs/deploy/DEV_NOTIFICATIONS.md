# Powiadomienia „agent skończył zadanie” (dźwięk + telefon)

Data: 2026-11-01 · Dotyczy komputerów deva (WSL2 + Cursor). Konfiguracja jest **per komputer**, poza repozytorium.

## Co robi

Hook Cursora na zdarzenie `stop` (moment, gdy agent kończy pracę) uruchamia [`scripts/dev-notify/agent-done-notify.sh`](../../scripts/dev-notify/agent-done-notify.sh):

1. **Dźwięk** na Windowsie (`tada.wav` przez `powershell.exe` z WSL), o ile dostępny. Wyłączenie: `NOTIFY_SOUND=0`.
2. **Push na telefon** przez [ntfy](https://ntfy.sh) (aplikacja ntfy, temat `Cursor_powiadomienia`), o ile ustawiony jest temat.

Tytuł zależy od statusu agenta: `Cursor: gotowe`, `Cursor: przerwane`, `Cursor: blad` (ten z wyższym priorytetem). W treści jest **tylko** nazwa komputera i projektu (np. `Zadanie zakonczone (PNE-YOGA, pneadm)`), dzięki czemu na telefonie widać, który z trzech komputerów skończył. Skrypt **nigdy** nie wysyła treści rozmowy, kodu, ścieżek, e-maili ani danych z baz (lokalne dane są prawdziwe).

## Instalacja na nowym komputerze (jedna komenda)

W katalogu `pneadm` (po `git pull`):

```bash
scripts/dev-notify/install.sh Cursor_powiadomienia --test
```

Instalator:
- kopiuje skrypt do `~/.cursor/hooks/agent-done-notify.sh`,
- zapisuje temat w `~/.config/cursor-notify/ntfy.env` (uprawnienia 600, **poza repo**),
- dopisuje wpis `stop` do `~/.cursor/hooks.json` bez ruszania innych hooków (usuwa starą, tylko dźwiękową wersję),
- z `--test` wysyła powiadomienie próbne.

Wymagania: `curl` i `python3` (w WSL domyślnie są). Po instalacji w razie potrzeby zrestartuj okno Cursora. Podgląd: Cursor → Settings → Hooks albo kanał Output → Hooks.

Telefon: w aplikacji ntfy zasubskrybuj temat `Cursor_powiadomienia` na serwerze `ntfy.sh` (to samo na wszystkich komputerach).

## Bezpieczeństwo (ważne)

- Na publicznym `ntfy.sh` **każdy, kto zna nazwę tematu, może go czytać i wysyłać do niego wiadomości**. Nazwa `Cursor_powiadomienia` jest łatwa do odgadnięcia. Ponieważ wiadomości są ogólne (status, komputer, projekt), ryzyko jest małe, ale ktoś mógłby podglądać Twoją aktywność albo wysyłać fałszywe powiadomienia.
- **Zalecenie:** zmień temat na trudny do odgadnięcia (np. `Cursor_powiadomienia_` + 12 losowych znaków) i ustaw go w aplikacji oraz na każdym komputerze: `scripts/dev-notify/install.sh NOWY_TEMAT`. Alternatywy: temat chroniony tokenem (`NTFY_TOKEN` w `ntfy.env`, konto na ntfy.sh) albo własny serwer ntfy (`NTFY_SERVER`).
- Nie wpisuj nazwy tematu do repozytorium ani do dokumentacji, jeśli zmienisz go na „tajny”.

## Konfiguracja (`~/.config/cursor-notify/ntfy.env`)

| Zmienna | Znaczenie |
|---------|-----------|
| `NTFY_TOPIC` | temat ntfy (bez niego tylko dźwięk) |
| `NTFY_SERVER` | domyślnie `https://ntfy.sh` |
| `NTFY_TOKEN` | opcjonalnie, dla tematu chronionego tokenem |
| `NOTIFY_SOUND` | `0` wyłącza dźwięk |

## Zasady dla agentów AI

- Nie czytaj i nie wypisuj `~/.config/cursor-notify/ntfy.env` (temat może być „tajny”). Pokazuj tylko „ustawione/puste”.
- Nie rozszerzaj treści powiadomienia o fragmenty rozmowy, kod, ścieżki ani dane z baz.
- Wyłączenie: usuń wpis `stop` z `~/.cursor/hooks.json`.

## Powiązane

- Synchronizacja prod → dev: [`DEV_SYNC.md`](./DEV_SYNC.md)
