# Konsultacja: błąd URL przy generowaniu mailingu głównego

Data: 2026-10-26  
Kontekst: Growth OS, materiał `main-mail`

## Objaw

Komunikat: „AI wstawiło niedozwolony adres URL do szkicu…”. Model (często przy włączonej „Sieci”) dopisywał `https://…` / `www.…` do body lub preheadera. Walidacja `unexpected_url` odrzucała całą propozycję.

## Naprawa

Zamiast odrzucać szkic, aplikacja **usuwa niedozwolone URL** z odpowiedzi (mailing, YouTube, brief) albo zamienia je na `[LINK DO ZAPISU]` (post Facebook, scenariusz). URL-e już obecne w allowliście wejścia zostają. Prompt nadal zabrania URL.

Bez migracji.
