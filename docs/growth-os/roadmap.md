# PNE Growth OS — Roadmap

Status: roadmapa kierunkowa, nie sztywny harmonogram.

## NOW

Projekt modelu danych v0.1 jest dokumentowany przed migracjami. Zakres minimalny: GrowthCampaign, Topic, Expert, Artifact, Task i Decision.

## NEXT

Po akceptacji dokumentacji modelu v0.1: przygotować migracje w `pneadm/database/migrations/`, bez przenoszenia jeszcze całego UX z sesji do DB.

## LATER

Po walidacji UX: model danych v0.1, AI drafts/research, integracje wykonawcze, post-produkcja, analityka i grafy relacji.

## Etap 0.3 — Prototyp UX Webinaru TIK

Zakres:

- UX webinaru TIK od zera,
- session-only,
- fake AI,
- fake approval,
- checklista czasowa,
- Inbox pomocniczy,
- follow-up jako etap procesu.

Cel:

- sprawdzić, czy właściciel chce pracować w takim flow,
- sprawdzić czy jasne jest: co robię, gdzie jestem, co jest gotowe i co następne.

## Etap 0.3.1 — Edycja I Praca Nad Koncepcją — wdrożony lokalnie

Zakres:

- edycja ręczna koncepcji w sesji,
- cofnięcie zatwierdzenia,
- symulowane „Poproś AI o zmianę”,
- propozycja AI do przyjęcia albo odrzucenia,
- prosta historia wersji w sesji.

## Etap 0.3.2 — Pilotaż OpenAI Dla Koncepcji — zaimplementowany, oczekuje na smoke test

Zakres:

- osobna flaga `GROWTH_AI_ENABLED`,
- OpenAI Responses API i centralnie ustawiany model,
- jedno zadanie `concept_revision`,
- abstrakcja providera bez drugiego dostawcy i bez fallbacku,
- structured output, walidacja i allowlista danych koncepcji,
- propozycja w sesji z obowiązkowym Zastosuj / Odrzuć,
- timeout, jeden retry, rate limit, limit dzienny i prosty circuit breaker,
- bezpieczne logowanie metadanych oraz testy bez prawdziwego API.

## v0.1 — Model Danych

Zakres minimalny:

- DB,
- GrowthCampaign,
- Topic,
- Expert,
- Artifact,
- Task,
- Decision.

Warunek:

- zaakceptowana dokumentacja modelu danych w `architecture.md`.
- migracje w `pneadm`, bo tabele należą do bazy `pneadm`.
- bez Customer Graph, Metrics, Content/Product Graph, publikacji i wysyłek.

## v0.2 — AI Drafts I Research

Zakres:

- rozwinięcie sprawdzonej warstwy providerów poza pilotaż OpenAI,
- drafty treści,
- research,
- wersjonowanie promptów,
- audyt źródeł i zakresu danych.

## v0.3 — YouTube Integration

Zakres:

- przygotowanie opisu,
- draft metadanych,
- status integracji,
- bezpieczny tryb preview / dry-run przed publikacją.

## v0.4 — Sendy Integration

Zakres:

- draft mailingu,
- test mailingowy,
- zatwierdzana wysyłka,
- status i log wykonania.

## v0.5 — Canva / Graphics

Zakres:

- brief grafiki,
- warianty tekstów na grafikę,
- eksport / przekazanie do narzędzia graficznego.

## v0.6 — Post-Production / Transcription / Repurposing

Zakres:

- transkrypcja,
- wyciąganie fragmentów,
- repurposing na artykuł, Shorts/Reels, newsletter, follow-up,
- materiał dla uczestnika.

## v0.7 — Analytics / Customer Graph / Relationship Engine

Zakres:

- analityka skuteczności treści,
- Customer Graph,
- Relationship Engine,
- Next Best Experience dla odbiorcy lub segmentu.

## Dalsze Kierunki

- PNE Radar,
- Expert Graph,
- Product Graph,
- B2B CRM,
- Next Best Experience,
- rozwój relacji z organizacjami i szkołami.
