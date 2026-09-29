# PNE Growth OS — Documentation

Ten katalog jest nowym, uporządkowanym źródłem prawdy dla PNE Growth OS.

Najważniejszy plik:

- [CURRENT.md](./CURRENT.md) — aktualny stan projektu.

Pliki referencyjne:

- [CONSULTING.md](./CONSULTING.md) — instrukcja dla zewnętrznego ChatGPT / konsultanta AI,
- [vision.md](./vision.md) — wizja strategiczna,
- [architecture.md](./architecture.md) — architektura koncepcyjna,
- [decisions.md](./decisions.md) — log decyzji,
- [roadmap.md](./roadmap.md) — roadmapa kierunkowa,
- [consultations/](./consultations/) — pakiety konsultacyjne dla zewnętrznego ChatGPT.

## Reguła Aktualizacji Dokumentacji

Po każdej istotnej zmianie funkcjonalnej, UX lub architektonicznej w PNE Growth OS Cursor ma:

1. zaktualizować [CURRENT.md](./CURRENT.md),
2. jeżeli zmiana dotyczy decyzji — zaktualizować [decisions.md](./decisions.md),
3. jeżeli zmienia architekturę — zaktualizować [architecture.md](./architecture.md),
4. jeżeli zmienia kolejność prac — zaktualizować [roadmap.md](./roadmap.md).

`CURRENT.md` musi zawsze odzwierciedlać faktyczny stan kodu.

Nie wpisujemy do `CURRENT.md` rzeczy planowanych jako już wykonanych.

Jeżeli kod i `CURRENT.md` są niespójne, traktujemy to jako błąd wymagający poprawy przed zakończeniem zadania.

## CURRENT != PLAN

Do sekcji „Co już działa” w `CURRENT.md` wolno wpisywać wyłącznie elementy faktycznie istniejące w aktualnym kodzie/prototypie.

Nie wpisujemy tam:

- wizji,
- przyszłych funkcji,
- planowanych API,
- przyszłych modeli,
- pomysłów konsultacyjnych.

Planowane elementy należą do `roadmap.md` albo `vision.md`.

## Hierarchia Źródeł Prawdy

1. aktualny kod,
2. [CURRENT.md](./CURRENT.md),
3. [decisions.md](./decisions.md),
4. [architecture.md](./architecture.md),
5. [roadmap.md](./roadmap.md),
6. [vision.md](./vision.md),
7. historyczne konsultacje.

Kod jest nadrzędny w kwestii tego, co faktycznie istnieje. `CURRENT.md` opisuje aktualny stan operacyjny. Decyzje opisują zaakceptowane ustalenia. Konsultacje historyczne nie mogą nadpisywać nowszych decyzji.

## Pakiety Konsultacyjne

Jeżeli pojawia się większa decyzja wymagająca konsultacji z ChatGPT, Cursor może utworzyć:

```text
docs/growth-os/consultations/YYYY-MM-DD-topic.md
```

Pakiet powinien zawierać:

- current state relevant to the question,
- problem,
- constraints,
- existing decisions,
- options,
- question for consultant,
- expected output,
- relevant files / screenshots.

Pakiet nie powinien kopiować całej dokumentacji. Ma zawierać tylko kontekst potrzebny do konkretnej konsultacji.

## Relacja Do Starszej Dokumentacji

Starszy katalog `docs/pne-growth-os/` pozostaje historycznym i szczegółowym kanonem wcześniejszych etapów.

Od teraz szybki kontekst dla Cursor AI i ChatGPT powinien zaczynać się od:

```text
docs/growth-os/CURRENT.md
```
