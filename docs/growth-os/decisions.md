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
Status: SUPERSEDED by DEC-013<br>
Decision: W prototypie nie ma side effectów.  
Rationale: Najpierw testujemy UX i proces, nie integracje.  
Consequences: Brak DB, API, publikacji, wysyłek, jobów i zewnętrznych operacji.

## DEC-007

Date: 2026-09-29  
Status: SUPERSEDED by DEC-013<br>
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

## DEC-013

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: Etap 0.3.2 dopuszcza jeden kontrolowany wyjątek od braku API: opcjonalne OpenAI wyłącznie dla zadania `concept_revision`.<br>
Rationale: Chcemy zweryfikować wartość prawdziwego AI w najmniejszym możliwym zakresie bez budowania modelu danych ani automatyzacji całego procesu.<br>
Consequences: Integracja ma osobną, domyślnie wyłączoną flagę; jest dostępna tylko dla `super_admin`; stan projektu i propozycja pozostają w sesji; nie ma publikacji, wysyłek ani innych zewnętrznych działań.

## DEC-014

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: Growth OS wywołuje AI przez warstwę zadania i abstrakcję providera; pierwszym providerem jest OpenAI, bez automatycznego fallbacku.<br>
Rationale: Model ma być konfigurowany centralnie, a przyszłe dodanie Anthropic lub Gemini nie może wymagać przebudowy logiki etapu Koncepcja.<br>
Consequences: Kontroler i stan sesji nie wywołują API OpenAI bezpośrednio. Zmiana providera wymaga jawnej implementacji i decyzji dotyczącej danych, a nie automatycznego przełączenia.

## DEC-015

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: Do zewnętrznego AI wolno wysłać wyłącznie allowlistę pól koncepcji webinaru i instrukcję zmiany; PII, sekrety oraz dane klientów, zamówień i płatności są zabronione.<br>
Rationale: Pilotaż nie wymaga danych operacyjnych ADM, a minimalizacja danych ogranicza ryzyko prywatności.<br>
Consequences: Zadanie buduje nowy payload z dozwolonych pól, waliduje wejście i structured output, ustawia `store: false`, a log techniczny nie zawiera promptu, odpowiedzi ani treści koncepcji.

## DEC-016

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: Prawdziwe AI zachowuje ten sam model akceptacji co symulacja: tworzy propozycję, nigdy nie nadpisuje koncepcji automatycznie.<br>
Rationale: Człowiek pozostaje właścicielem treści i musi móc porównać wariant z bieżącą wersją.<br>
Consequences: Dopiero „Zastosuj” zmienia koncepcję; „Odrzuć”, błędny JSON, timeout, 429 lub 5xx pozostawiają bieżący stan bez zmian.

## DEC-017

Date: 2026-09-29<br>
Status: SUPERSEDED by DEC-018<br>
Decision: Model danych v0.1 zaczynamy od minimalnego fundamentu: GrowthCampaign, Topic, Expert, Artifact, Task i Decision.<br>
Rationale: Po walidacji prototypu i pilotażu AI potrzebujemy trwałego stanu procesu, ale bez budowania pełnego CRM, grafu klientów ani integracji wykonawczych na zapas.<br>
Consequences: Najpierw dokumentacja modelu w `docs/growth-os/architecture.md`, potem migracje w `pneadm/database/migrations/` po akceptacji. Poza zakresem v0.1 pozostają Customer Graph, Metrics, Content/Product Graph, publikacje, wysyłki i RAG.

## DEC-018

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: Model danych v0.1 obejmuje tylko 4 główne tabele: `growth_campaigns`, `growth_artifacts`, `growth_tasks` i `growth_decisions`.<br>
Rationale: Po konsultacji zewnętrznej i decyzji właściciela v0.1 ma utrwalić proces, a nie budować Topic Graph ani Expert Graph. Temat pozostaje w danych kampanii/koncepcji, a ekspert jest oparty o istniejący `App\Models\Instructor` jako opcjonalny główny prowadzący kampanii.<br>
Consequences: W pierwszych migracjach nie tworzymy `growth_topics`, `growth_experts`, `growth_campaign_topic`, `growth_campaign_expert` ani `growth_artifact_versions`. `growth_decisions` pozostaje osobną tabelą jako kanoniczna historia decyzji człowieka. Migracje powstaną dopiero po akceptacji tej dokumentacji.

## DEC-019

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: Artifact ma kontrakt `key` + `type` + `schema_version` + `payload`, osobne statusy encji są kanoniczne, a wersjonowanie artifactów w v0.1 ogranicza się do licznika `version`.<br>
Rationale: `payload` JSON daje elastyczność dla różnych materiałów, ale tylko pod warunkiem walidowanego kontraktu `type + schema_version`. Pełna historia wersji zwiększyłaby zakres v0.1 ponad potrzebę trwałego stanu procesu.<br>
Consequences: `growth_artifacts` ma `unique(growth_campaign_id, key)`. `published` nie jest statusem artifactu; publikacja będzie później osobną domeną. Pełna historia/przywracanie wersji może powstać później w osobnej tabeli.

## DEC-020

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: GrowthTask utrwala prostą checklistę operacyjną kampanii. Pierwszy zestaw to 9 zadań ze stabilnym `key`. W obecnym UX widać tylko `todo` i `done`. Odhaczenie nie tworzy decyzji i nie zmienia „Najważniejszego następnego kroku”.<br>
Rationale: Cała checklista czasowa dublowałaby główny flow, koncepcję i sesyjne materiały. Trwałe mają być tylko czynności operacyjne, których nie opisuje ani artifact, ani decyzja.<br>
Consequences: `growth_tasks.key` jest unikalny w kampanii. Termin `due_at` liczy się od `live_at`. `assignee_user_id` i `growth_artifact_id` zostają puste. Zadania powstają przy utworzeniu kampanii oraz przez jawną komendę `growth:seed-operational-tasks`, nie przy wejściu na ekran. Kierunek i materiały nadal nie są trwałe. Trwałość materiałów opisuje późniejszy DEC-021.

## DEC-021

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: Dziesięć materiałów roboczych webinaru zapisuje się jako artifacty typu `material` ze stabilnym `key`, statusem i szkicem. Zapis następuje przy jawnym „Zapisz materiał”, nie przy wejściu na ekran. Kierunek pozostaje w sesji.<br>
Rationale: To ten sam kontrakt co koncepcja. Status i szkic mają wracać po zalogowaniu, bez nowego ekranu, bez AI i bez decyzji człowieka.<br>
Consequences: Klucze: `youtube-description`, `main-graphic`, `facebook-post`, `main-mail`, `reminder-mail`, `landing`, `host-script`, `participant-material`, `obs-intro`, `follow-up`. `schema_version` = 1. `payload` trzyma status widoczny w UX oraz szkic. Etykieta „Opublikowane / zaplanowane” zostaje w `payload.status`, a kolumna `status` dostaje `approved`, bo `published` nie jest statusem artifactu. Zapis nie tworzy `growth_decisions` i nie zmienia reguł następnego kroku. Kierunek, propozycja AI i prowadzący nadal nie są trwałe. Trwałość kierunku opisuje późniejszy DEC-022.

## DEC-022

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: Kierunek webinaru zapisuje się jako artifact `direction` z pięcioma polami. „Zatwierdź kierunek” i cofnięcie są decyzją `direction_approval`. Propozycja AI przy kierunku i prowadzący zostają w sesji.<br>
Rationale: Po zalogowaniu ma wracać treść kierunku i fakt zatwierdzenia, tak jak przy koncepcji. Stała podpowiedź AI nie jest decyzją ani treścią właściciela.<br>
Consequences: Klucz `direction`, `type=direction`, `schema_version=1`. `payload`: `why_now`, `audience`, `problem`, `takeaway`, `sell_later`. Zapis jest jawny. Zatwierdzenie tworzy decyzję `approved` i utrwala bieżące pola. Cofnięcie oznacza poprzednią decyzję jako `superseded` i dodaje `changes_requested`. Edycja zatwierdzonego kierunku też oznacza decyzję jako `superseded`, bez nowej decyzji. „Zastosuj” przy koncepcji, jeśli zmienia odbiorców, zapisuje kierunek i cofa jego zatwierdzenie. Prowadzący i propozycja AI nadal nie są trwałe. Trwałość prowadzącego opisuje późniejszy DEC-023.

## DEC-023

Date: 2026-09-29<br>
Status: ACTIVE<br>
Decision: Prowadzący zapisuje się jako `growth_campaigns.host_name`. To wolny tekst z formularza, bez powiązania z `instructors` i bez `primary_instructor_id`.<br>
Rationale: Po zalogowaniu ma wracać imię wpisane przy projekcie. Istniejący instruktor zostaje osobnym, opcjonalnym wskazaniem eksperta.<br>
Consequences: Utworzenie projektu zapisuje `host_name`. Na ekranie projektu można je zmienić przyciskiem „Zapisz prowadzącego”. Puste pole nie czyści zapisanego imienia przy samym odtworzeniu. Propozycja AI nadal nie jest trwała.
