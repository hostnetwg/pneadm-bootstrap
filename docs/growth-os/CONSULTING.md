# Consulting PNE Growth OS

## Read Order

1. [CURRENT.md](./CURRENT.md)
2. [decisions.md](./decisions.md) — gdy konsultacja dotyczy wcześniejszych ustaleń
3. [architecture.md](./architecture.md) — gdy konsultacja dotyczy architektury
4. [vision.md](./vision.md) — gdy konsultacja dotyczy kierunku strategicznego
5. [roadmap.md](./roadmap.md) — gdy konsultacja dotyczy kolejności prac
6. [consultations/](./consultations/) — tylko gdy potrzebny jest kontekst historyczny

## Source-Of-Truth Rules

- `CURRENT.md` = aktualny stan projektu.
- `decisions.md` = zaakceptowane decyzje.
- `architecture.md` = model koncepcyjny / techniczny.
- `roadmap.md` = plan kierunkowy.
- `vision.md` = długoterminowy kierunek.
- `consultations/` = historia konsultacji.

Wizja ani roadmapa nie oznaczają, że funkcja została już zaimplementowana.

Jeżeli `CURRENT.md` i kod są niespójne: kod jest źródłem prawdy dla stanu implementacji, a `CURRENT.md` trzeba poprawić.

## Formal Source Hierarchy

1. aktualny kod,
2. `CURRENT.md`,
3. `decisions.md`,
4. `architecture.md`,
5. `roadmap.md`,
6. `vision.md`,
7. historical consultations.

Kod jest nadrzędny w kwestii tego, co faktycznie istnieje. Konsultacje historyczne nie mogą nadpisywać nowszych decyzji.

## Consulting Rules

Podczas konsultacji:

- odróżniaj CURRENT od PROPOSED i FUTURE,
- nie zakładaj, że planowana funkcja istnieje,
- nie zakładaj dostępu do ADM,
- nie generuj kodu, jeśli konsultacja dotyczy UX/strategii,
- uwzględniaj obowiązujące decyzje,
- jeżeli brakuje faktów, zadaj pytania Waldemarowi albo Cursorowi.
