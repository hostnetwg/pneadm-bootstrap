# Portal prowadzącego na pnedu.pl (parkowane)

Data: 2026-09-19  
Status: **plan na później**, bez wdrożenia  
Powiązane: [LIVE_EMBED_RESOURCE_BAR.md](./LIVE_EMBED_RESOURCE_BAR.md), [DASHBOARD_LIVE_EMBED.md](../pnedu/docs/DASHBOARD_LIVE_EMBED.md)

## Po co

Dziś prowadzący dostaje mailem linki z ADM („Prześlij linki prowadzącemu”) i wchodzi do ClickMeeting jak gość/prezenter. Nie ma konta na pnedu.pl związanego z tabelą `instructors`.

Cel na później: prowadzący loguje się **tym samym e-mailem** co w `/instructors` i obsługuje swoje szkolenia bez pełnego ADM.

## Kierunek (nie decyzja produktowa)

1. **Konto instruktora na pnedu.pl** — osobna rola, nie panel ADM. Logowanie e-mailem z `instructors`. Bez dostępu do zamówień, FV, całego katalogu.
2. **Kalendarz** swoich `courses` (tam, gdzie `instructor_id`).
3. **Materiały** — dodawanie `course_file_links` do „swojego” szkolenia (moderacja / akceptacja w ADM do rozstrzygnięcia).
4. **Ankiety** — podgląd wyników ankiet szkoleń, które prowadził.
5. **Live** — jeśli wejdzie w osadzony pokój (osobna ścieżka embed dla trenera, nie token słuchacza): na belce **sterowanie** tymi samymi trzema slotami co operator w `/courses/{id}/live` (albo węższym podzbiorem).
6. Lista obecności / zaświadczenia — podgląd, nie wystawianie FV.

## Świadomie poza tym planem

- Pełny ADM dla trenerów.
- Osadzony live dla nauczycieli ze szkolenia zamkniętego bez konta (nadal link CM).
- Automatyczne tworzenie konta przy każdym `instructors.email` bez zgody Waldemara.

Gdy będzie etap: najpierw decyzja (rola vs osobna aplikacja, kto wpuszcza konto, czy trener może sam włączać ankietę na live), potem kod.
