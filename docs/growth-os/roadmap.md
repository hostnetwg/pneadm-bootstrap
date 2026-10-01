# PNE Growth OS — Roadmap

Status: roadmapa kierunkowa, nie sztywny harmonogram.

## NOW

v0.2, drugi wycinek: szkic AI dla materiału **Post Facebook** (`facebook-post`), tym samym wzorcem co opis YouTube (DEC-026). Wdrożone lokalnie, czeka na ręczną weryfikację Waldemara. Pierwszy wycinek (opis YouTube) Waldemar zweryfikował 2026-10-01, z prawdziwym AI i w symulacji.

## NEXT

Po Post Facebook: pierwszy materiał krytyczny, czyli mailing główny. Propozycje AI zostają w sesji. Zadania operacyjne nie sterują głównym CTA.

## LATER

AI dla kolejnych materiałów, research, integracje wykonawcze, post-produkcja, analityka i grafy relacji. Model danych v0.1 jest już wdrożony.

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

## Etap 0.3.2 — Pilotaż OpenAI Dla Koncepcji — zaimplementowany i zweryfikowany ręcznie

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
- Artifact,
- Task,
- Decision.

Warunek:

- zaakceptowana dokumentacja modelu danych w `architecture.md`.
- migracje w `pneadm`, bo tabele należą do bazy `pneadm`.
- bez Topic Graph, Expert Graph, Customer Graph, Metrics, Content/Product Graph, publikacji i wysyłek.

Kolejność wdrożenia:

1. migracje 4 tabel — zrobione,
2. modele i relacje — zrobione,
3. testy integralności — zrobione,
4. pierwszy vertical slice: Campaign + Concept Artifact — zrobione,
5. trwałe odtworzenie projektu po ponownym wejściu — zrobione dla kampanii i koncepcji,
6. Decision — zrobione dla decyzji przy koncepcji,
7. Task — zrobione dla 9 zadań operacyjnych,
8. dalsze materiały — zrobione dla 10 materiałów roboczych (status i szkic),
9. kierunek — zrobione dla pięciu pól i decyzji zatwierdzenia,
10. prowadzący — zrobione jako `host_name`, bez powiązania z instruktorem.

Kryterium sukcesu:

- Waldemar może rozpocząć projekt webinaru TIK, zapisać koncepcję, zamknąć przeglądarkę, wrócić później i kontynuować ten sam projekt z zachowanymi artifactami, zadaniami i decyzjami człowieka.
- Flow UX pozostaje prosty, a AI nadal wymaga jawnego **Zastosuj / Odrzuć**.

Poza v0.1:

- Topic jako osobna encja,
- Expert Graph jako rozszerzenie istniejącego `Instructor`,
- pełna historia wersji artifactów,
- publikacja, dystrybucja i integracje wykonawcze.

## v0.2 — AI Drafts I Research

Wdrożone (DEC-024): zadanie `material_draft` tylko dla `youtube-description`. Propozycja w sesji, wymagane zatwierdzenie kierunku i koncepcji, Zastosuj / Odrzuć jako decyzje, status po Zastosuj = `DRAFT`, bez innych materiałów w kontekście. Prowadzący trafia do AI od DEC-025.

Zakres docelowy:

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
