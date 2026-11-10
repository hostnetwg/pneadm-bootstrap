# Konsultacja: błąd URL przy asystencie kierunku

Data: 2026-11-10  
Kontekst: Growth OS, karta **Pomysł i kierunek** (`#direction`)

## Problem

Komunikat: „Nie udało się przygotować poprawnej propozycji AI. Możesz przygotować kierunek ręcznie.”. Przy włączonej „Sieci” model często dopisywał `https://…` / `www.…` do pól kierunku. Walidacja `unexpected_url` w `DirectionPlanningTask` odrzucała całą propozycję (ten sam mechanizm co wcześniej przy mailingu głównym).

## Decyzja wdrożona

Jak przy materiałach: niedozwolone URL są **usuwane** z pól kierunku / tematu roboczego / sugestii tytułów / podsumowania zmian, zamiast blokować propozycję. URL-e już obecne w allowliście wejścia zostają. Komunikaty błędów kierunku rozróżniają też niedokończoną odpowiedź i ewentualny pozostały `unexpected_url`.
