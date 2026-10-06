# Konsultacja: scenariusz od zera bez obecnego szkicu

Data: 2026-10-06  
Kontekst: Growth OS, materiał `host-script` (DEC-034)

## Problem

Na stronie scenariusza prowadzącego był jeden przycisk „Poproś AI o szkic”. AI zawsze dostawało bieżący szkic (`current_draft`) i traktowało go jako punkt wyjścia. Nie dało się poprosić o całkowicie nowy scenariusz, gdy obecny był niewłaściwy lub zbyt długi.

## Decyzja Waldemara

Daj opcję, żeby AI stworzyło nowy scenariusz **bez** brania pod uwagę obecnego.

## Rozwiązanie (jak w YouTube / Facebook / mailing)

1. Dwa przyciski: „Poproś AI o nowy szkic” (`generate`) i „Popraw mój szkic” (`refine`).
2. Na karcie propozycji: „Popraw ponownie” (`iterate`).
3. Prompt `material_host_script_v3` z sekcją TRYB PRACY.
4. Generate: puste `current_draft`, bez `author_draft`.
5. Refine: `author_draft` z pola szkicu (także niezapisany); puste pole blokuje wywołanie.
6. Ikony PDF do ChatGPT.com zostają obok przycisków.

Bez migracji.
