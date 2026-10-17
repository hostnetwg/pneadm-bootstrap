# PNE Operations

Data: 2026-10-17  
Status: etap 0 — analiza i dokumentacja. Brak implementacji.

PNE Operations to przedsięwzięcie, w którym `adm.pnedu.pl` ma stopniowo stać się wewnętrznym systemem operacyjnym firmy. Celem jest obsługa powtarzalnych spraw bez polegania na pamięci konkretnych osób.

Nazwa katalogu `operations-os` jest domem dokumentacji. Nie jest nazwą modułu w kodzie i nie oznacza, że budujemy silnik procesów. Pierwszy realny obiekt to **sprawa szkolenia zamkniętego**, nie uniwersalna sprawa z polem typu i nie BPM. Zasada: najpierw praktyka, potem abstrakcja (DEC-OPS-011).

## Źródło prawdy

1. Aktualny kod `pneadm` i `pnedu`.
2. [CURRENT.md](./CURRENT.md) — tylko to, co już istnieje.
3. [DECISIONS.md](./DECISIONS.md) — decyzje Waldemara (propozycje nie są decyzjami).
4. [ARCHITECTURE.md](./ARCHITECTURE.md) — granice i rekomendowany model.
5. [ROADMAP.md](./ROADMAP.md) — kolejność małych etapów.
6. [VISION.md](./VISION.md) — kierunek biznesowy, nie opis stanu.
7. [CONSULTING.md](./CONSULTING.md) — fakty i pytania dla zewnętrznego konsultanta.

Jeżeli kod i `CURRENT.md` się różnią, poprawiamy dokumentację albo kod. Wizja nie może być opisana jako funkcja już działająca.

## Jak czytać

| Pytanie | Plik |
|---|---|
| Co dziś naprawdę działa? | [CURRENT.md](./CURRENT.md) |
| Dokąd zmierzamy? | [VISION.md](./VISION.md) |
| Jak to posadzić na obecnym kodzie? | [ARCHITECTURE.md](./ARCHITECTURE.md) |
| W jakiej kolejności? | [ROADMAP.md](./ROADMAP.md) |
| Co Waldemar już zatwierdził? | [DECISIONS.md](./DECISIONS.md) |
| Co ma ocenić ChatGPT? | [CONSULTING.md](./CONSULTING.md) |

## Powiązane dokumenty (nie duplikujemy ich tutaj)

- [AI_HUMAN_COMMUNICATION.md](../AI_HUMAN_COMMUNICATION.md) — role Waldemar / ChatGPT / Cursor.
- [TRAINING_OFFERS.md](../TRAINING_OFFERS.md) — katalog ofert bez terminu i formularz e-mail.
- [INSTRUCTOR_PORTAL.md](../INSTRUCTOR_PORTAL.md) — parkowany portal prowadzącego.
- [strategy/PNEDU_PLATFORM_FIRST.md](../strategy/PNEDU_PLATFORM_FIRST.md) — uczestnik wraca na pnedu.pl.
- [architecture/SYSTEM_OVERVIEW.md](../architecture/SYSTEM_OVERVIEW.md) — dwie aplikacje i bazy.
- [growth-os/README.md](../growth-os/README.md) — osobna domena rozwoju treści.
- [WINDYKACJA.md](../WINDYKACJA.md) — najbliższy istniejący wzorzec „sprawa + następne działanie”.
- [SURVEYS.md](../SURVEYS.md) — ankiety po szkoleniu.
- [QUEUE_SEOHOST.md](../QUEUE_SEOHOST.md) i [deploy/PRODUCTION_PATHS.md](../deploy/PRODUCTION_PATHS.md) — hosting współdzielony.

Skrót dla repozytorium frontu: `pnedu/docs/OPERATIONS_OS.md`.
