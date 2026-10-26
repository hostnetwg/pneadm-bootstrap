# Konsultacja: PDF aktualnego stanu projektu do ChatGPT

Data: 2026-10-26  
Kontekst: Growth OS, karta `/growth/projects/{id}`

## Prośba Waldemara

Na karcie projektu ikona/przycisk „Zapisz do PDF aktualny stan projektu” z zatwierdzonymi elementami webinaru — żeby w ChatGPT.com (lub innym zewnętrznym AI) generować kolejne materiały (opis YouTube, grafikę itd.).

## Rozwiązanie

1. Przycisk z ikoną PDF w hero karty projektu.
2. Trasa `GET /growth/projects/{project}/chatgpt-pdf`.
3. PDF zawiera: meta kampanii, zatwierdzony kierunek, zatwierdzoną koncepcję, zatwierdzone/opublikowane materiały (treść szkicu), listę materiałów jeszcze do zrobienia oraz pełny JSON snapshotu.
4. Niezatwierdzony kierunek/koncepcja: tylko informacja, że niezatwierdzone (bez treści roboczej). Szkice DRAFT/REVIEW nie trafiają do sekcji zatwierdzonych. Status „Nie dotyczy” osobno.

Bez migracji.
