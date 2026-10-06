# Konsultacja — scenariusz prowadzącego bez YouTube + PDF do ChatGPT.com

Date: 2026-10-06  
Status: wdrożone na `main` (bez migracji)  
Kontekst: Growth OS, materiał `host-script` (DEC-034)

## Problem

1. Przy „Poproś AI o szkic” scenariusz dostawał zatwierdzony opis YouTube. Długi opis + długi bieżący szkic łatwo przekraczały `GROWTH_AI_MAX_INPUT_CHARS` (12 000), więc API odmawiało wywołania.
2. Właściciel chce czasem pracować nad scenariuszem na [ChatGPT.com](https://chatgpt.com/) w ramach abonamentu ChatGPT, a nie przez OpenAI API (osobny koszt).

## Decyzja Waldemara

1. **Nie wysyłać opisu YouTube** do AI przy scenariuszu. Wejście: kierunek, koncepcja, bieżący szkic, czas trwania, opcjonalna instrukcja.
2. Obok „Poproś AI o szkic” dwie ikony PDF:
   - **bundle** — prompt aplikacji + dane projektu (odpowiednik pakietu API),
   - **data** — tylko dane projektu (własny prompt na ChatGPT.com).

## Skutki techniczne

- Prompt: `material_host_script_v2` (bez reguł o `source_materials.youtube_description`).
- `MaterialDraftTask::SOURCE_MATERIALS` — brak wpisu dla `host-script`.
- `MaterialDraftTask::input(..., forExport: true)` pomija limit znaków i filtr PII przy eksporcie PDF.
- Trasa: `GET /growth/projects/{project}/materials/host-script/ai/chatgpt-pdf?mode=bundle|data` (+ opcjonalnie `duration`, `instruction`).
- DomPDF: widok `growth-os/projects/partials/material-ai-chatgpt-export-pdf.blade.php`.

## Co świadomie nie robimy

- Eksport PDF dla innych materiałów (YouTube, mailingi…) — na razie tylko scenariusz.
- Automatyczne wklejanie do ChatGPT — tylko plik do ręcznego wgrania.
- Zmiana limitu `GROWTH_AI_MAX_INPUT_CHARS` na produkcji jako „naprawa” scenariusza — źródłem problemu był YouTube w payloadzie.

## Smoke

1. Strona scenariusza: brak badge „Opis YouTube”.
2. Dwie ikony PDF obok przycisku AI; oba pliki się pobierają.
3. Bundle zawiera sekcję promptu i JSON; data — tylko JSON.
4. Wywołanie API przy krótkim / pustym szkicu nadal działa bez YouTube w wejściu.

## Pytania na później (opcjonalnie)

- Czy ten sam wzorzec PDF (bundle / data) ma pojawić się przy koncepcii i innych materiałach?
- Czy zamiast PDF lepszy byłby `.txt` / `.md` (łatwiejsze kopiowanie)?
