# PNE Growth OS — Roadmap

Status: roadmapa kierunkowa, nie sztywny harmonogram.

## NOW

v0.2, drugi wycinek: szkic AI dla materiału **Post Facebook** (`facebook-post`), tym samym wzorcem co opis YouTube (DEC-026), zweryfikowany przez Waldemara 2026-10-01. Pierwszy wycinek (opis YouTube) Waldemar zweryfikował 2026-10-01, z prawdziwym AI i w symulacji.

## NEXT

Historia wersji materiałów (DEC-027): działa na produkcji od 2026-10-01.

Potem grafika główna, w etapach:

1. Tekstowy brief grafiki przez AI (DEC-028), od DEC-029 z zatwierdzonym opisem YouTube: wdrożony lokalnie, czeka na weryfikację Waldemara.
2. Obraz z AI przez OpenAI (DEC-029): generator z podglądem i galerią, od DEC-030 `gpt-image-2` medium z natywnym 16:9 i wersją kwadratową przekomponowaną z obrazu poziomego, domyślnie bez tekstu na obrazie, napis z nagłówkiem i terminem jako opcja. Wdrożony lokalnie, czeka na weryfikację Waldemara i migrację na produkcji.
3. Projekt w Canvie: szablon marki z polami do wypełnienia przez Canva Connect Autofill API. Według ogłoszenia Canvy z 2026-09-23 Autofill działa od planu Pro, także w Canva Education; Waldemar ma Pro i Education. Wymaga integracji w Canva Developer Portal (MFA), połączenia konta przez OAuth i przechowywania tokenów. Część dokumentacji Canvy nadal wymienia Enterprise, więc trzeba to potwierdzić przed wdrożeniem.

Przełącznik trybu grafiki (obraz AI / projekt w Canvie / połączenie) dopiero, gdy działają co najmniej dwa tryby.

Mailing główny (DEC-032): szkic AI z 3 tematami, preheaderem, treścią i przełącznikiem długości, wdrożony na produkcji. Mailing przypominający (DEC-033): ten sam wzór, przełącznik „jutro” / „dziś”, `[LINK DO POKOJU]` i `[LINK DO ZAPISU]`, źródłem jest też zatwierdzony mailing główny; wdrożony lokalnie, czeka na weryfikację Waldemara. Scenariusz prowadzącego (DEC-034): plan blokami z godzinami dla 45, 60, 90 minut albo własnego czasu; od 2026-10-06 bez opisu YouTube w wejściu AI oraz z eksportem PDF do ChatGPT.com (prompt+dane / same dane). Wdrożony lokalnie / na `main`. Potem zapis propozycji AI w bazie albo kolejne materiały (landing, follow-up). Propozycje AI zostają w sesji. Zadania operacyjne nie sterują głównym CTA.

Operator ≠ Prowadzący ≠ Głos komunikacji (DEC-035) i tryby opisu YouTube generate / refine / iterate (DEC-036): wdrożone lokalnie, czekają na weryfikację Waldemara i dwie migracje na produkcji. Później, jeśli się sprawdzi: głos i tryby dla kolejnych materiałów (każdy osobną decyzją), zapis głosu przy wersji materiału (dziś głos jest tylko przy kampanii). Świadomie nie robimy: kont i logowania instruktorów, biblioteki stylu, wyszukiwania w starych mailach, RAG, embeddingów, fine-tuningu ani automatycznego uczenia profilu.

Asystent kierunku w otwartym projekcie (DEC-039): na karcie „Pomysł i kierunek” te same tryby iterate i refresh. Propozycja obok pól, „Zastosuj” zapisuje szkic. Bez migracji.

Koncepcja z kierunku (DEC-040): przy pustej koncepcji pierwsza opcja to „Wygeneruj na podstawie pomysłu i kierunku”. Ten sam task `concept_revision`, bez wyszukiwania. Tytuł koncepcji nie zmienia tematu kierunku. Bez migracji.

Kierunek przy każdej poprawce koncepcji (DEC-041): pozostałe opcje listy też dostają zapisany kierunek jako granicę sensu. „Zastosuj” nie zmienia kierunku. Bez migracji.

Logo na grafice (DEC-042): pliki logo Platformy i sponsora są nakładane po wygenerowaniu obrazu. Migracja `2026_10_03_120000`.

Poprawka grafiki (DEC-043): brief i opis obrazu poprawia się osobno, nagłówek bierze się z tematu, a gotowe zdjęcie można poprawić jedną uwagą. Bez migracji.

Post Facebook (DEC-044): te same trzy kroki poprawki co opis YouTube. Bez migracji.

Mailing główny (DEC-045): te same trzy kroki. Poprawka bierze temat, preheader i treść. Bez migracji.

Reset dziennego limitu AI (DEC-046): przy komunikacie o limicie jest „Zresetuj limit”. Limit obrazów zostaje osobny. Bez migracji.

HTML mailingu głównego (DEC-047): checkbox domyślnie włączony, aplikacja składa HTML do Sendy. Bez migracji.

Edytor treści maila (DEC-048): Edycja / Kod HTML i podstawowe formatowanie. Od DEC-049 mailing główny używa Tiptap i trzech szablonów. Mailing przypominający zostaje przy własnym oknie. Bez migracji.

Asystent planowania kierunku (DEC-037): zadanie `direction_planning` z `web_search` na formularzu Zaplanuj webinar, wdrożone lokalnie, czeka na ręczną weryfikację Waldemara (NotebookLM). Bez migracji. Świadomie nie robimy: czatu, odkrywania tematu, głosu komunikacji w tym zadaniu ani automatycznego zatwierdzania kierunku.

Lista i usuwanie projektów (DEC-038): `/growth/projects` pokazuje wszystkie kampanie właściciela, otwieranie i trwałe usuwanie z modalem. Bez kosza.

## LATER

AI dla kolejnych materiałów, odkrywanie tematu, integracje wykonawcze, post-produkcja, analityka i grafy relacji. Model danych v0.1 jest już wdrożony. Research na starcie webinaru (DEC-037) nie jest już „later”.

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
