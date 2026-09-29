# PNE Growth OS — Decisions

## DEC-001

Date: 2026-09-29  
Status: ACTIVE  
Decision: UX zaczyna się od **Zaplanuj TIK**, nie od abstrakcyjnego Topic.  
Rationale: Właściciel chce uczestniczyć od początku procesu, a nie odbierać gotową kolejkę decyzji.  
Consequences: Topic może pojawić się później jako warstwa domenowa, ale nie jest startem prototypu UX.

## DEC-002

Date: 2026-09-29  
Status: ACTIVE  
Decision: Inbox jest pomocniczy, nie główny.  
Rationale: Główny flow ma prowadzić przez projekt webinaru, a Inbox ma tylko pokazywać, co wymaga decyzji.  
Consequences: Element Inboxa musi prowadzić do konkretnego miejsca w projekcie.

## DEC-003

Date: 2026-09-29  
Status: ACTIVE  
Decision: Projekt webinaru jest głównym workspace.  
Rationale: To tam użytkownik widzi cel, etap, materiały, checklistę i następny krok.  
Consequences: Dashboard ma być operacyjny i prosty, nie ma zastępować workspace.

## DEC-004

Date: 2026-09-29  
Status: ACTIVE  
Decision: AI draftuje, człowiek zatwierdza.  
Rationale: AI może pomagać w analizie i tworzeniu wariantów, ale nie może publikować ani wysyłać bez człowieka.  
Consequences: Operacje wykonawcze wymagają jawnej decyzji użytkownika.

## DEC-005

Date: 2026-09-29  
Status: ACTIVE  
Decision: Moduł działa fail-closed.  
Rationale: Growth OS jest nową, eksperymentalną warstwą i nie może wpływać na istniejące procesy, gdy flaga jest wyłączona.  
Consequences: Przy `PNE_GROWTH_OS_ENABLED=false` moduł ma być niedostępny.

## DEC-006

Date: 2026-09-29  
Status: ACTIVE  
Decision: W prototypie nie ma side effectów.  
Rationale: Najpierw testujemy UX i proces, nie integracje.  
Consequences: Brak DB, API, publikacji, wysyłek, jobów i zewnętrznych operacji.

## DEC-007

Date: 2026-09-29  
Status: ACTIVE  
Decision: Etap 0.3 pozostaje bez DB i API.  
Rationale: Model danych powinien powstać po walidacji flow z właścicielem.  
Consequences: Stan projektu i materiałów jest tylko w sesji HTTP.

## DEC-008

Date: 2026-09-29  
Status: ACTIVE  
Decision: „Następny krok” ma być zawsze widoczny.  
Rationale: Użytkownik nie powinien zastanawiać się, co ma zrobić teraz.  
Consequences: Każdy workspace musi mieć jedno dominujące CTA.

## DEC-009

Date: 2026-09-29  
Status: ACTIVE  
Decision: Nie budujemy dużego dashboardu na zapas.  
Rationale: Dashboard ma służyć pracy dzisiaj, a nie być katalogiem przyszłych możliwości.  
Consequences: Menu i ekran Dzisiaj pozostają małe.

## DEC-010

Date: 2026-09-29  
Status: ACTIVE  
Decision: Growth OS ma prowadzić użytkownika krok po kroku.  
Rationale: To ma być inteligentny producent/asystent, nie CRM ani system ticketowy.  
Consequences: Ekrany mają pokazywać kontekst, stan i następny krok, a nie tylko listy rekordów.

## DEC-011

Date: 2026-09-29  
Status: ACTIVE  
Decision: W etapie Koncepcja AI tworzy wariant do przyjęcia lub odrzucenia, a nie nadpisuje treści automatycznie.  
Rationale: Użytkownik musi zachować kontrolę nad kierunkiem i porównać propozycję z obecną wersją.  
Consequences: „Poproś AI o zmianę” zapisuje `concept_ai_proposal` w sesji; dopiero „Zastosuj” zmienia bieżącą koncepcję.

## DEC-012

Date: 2026-09-29  
Status: ACTIVE  
Decision: Status etapu Koncepcja w prototypie to **Do dopracowania** / **Gotowe**, z możliwością cofnięcia.  
Rationale: Prostszy model niż Draft / Review / Approved wystarcza do walidacji UX przed bazą danych.  
Consequences: Cofnięcie usuwa zatwierdzenie z sesji i wraca do pracy nad treścią.
