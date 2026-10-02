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

## DEC-024

Date: 2026-10-01<br>
Status: ACTIVE<br>
Decision: Pierwszym materiałem z AI jest `youtube-description`. Nowe zadanie `material_draft` (profil `youtube_description_v1`, prompt `material_youtube_description_v2`, schema `material_youtube_description_schema_v1`) przygotowuje szkic. Prompt v2 dodaje opcję emotikon (`style.emojis`, domyślnie włączoną) i opcjonalną instrukcję właściciela (`instruction`), której nadrzędne są zasady promptu. Wymaga zatwierdzonego kierunku i zatwierdzonej koncepcji. Propozycja żyje tylko w sesji HTTP. „Zastosuj” i „Odrzuć” są decyzjami człowieka.<br>
Rationale: Opis YouTube najprościej wynika z zatwierdzonej koncepcji i nie wymaga integracji. Ten sam wzorzec co przy koncepcji (propozycja → Zastosuj / Odrzuć) sprawdza AI na materiale bez ryzyka publikacji.<br>
Consequences: Do AI trafia tylko allowlista: temat roboczy, cel, data i godzina live w strefie aplikacji, pięć pól kierunku, pola koncepcji i bieżący szkic tego materiału. `host_name`, inne materiały i dane klientów, zamówień, płatności oraz kontaktowe nie są wysyłane. Odpowiedź ma tylko `draft` i `change_summary`. „Zastosuj” sprawdza odcisk kierunku, koncepcji i szkicu; nieaktualna propozycja jest odrzucana. Po „Zastosuj” zmienia się tylko `payload.draft`, status materiału to `DRAFT`, powstaje decyzja `material_ai_apply`. „Odrzuć” nie zmienia materiału i zapisuje `material_ai_reject`. Decyzje wskazują artifact materiału, jeśli istnieje; `meta` ma tylko klucz materiału, wersję promptu i źródło. Ręczny „Zapisz materiał” nadal nie tworzy decyzji. Flaga, limit dzienny i circuit breaker są wspólne z `concept_revision`. Brak publikacji, wysyłek, integracji wykonawczych i AI dla pozostałych materiałów. `schema_version` materiałów zostaje 1. Regułę o `host_name` zmienia DEC-025.

## DEC-025

Date: 2026-10-01<br>
Status: ACTIVE<br>
Decision: Szkic AI opisu YouTube dostaje imię i nazwisko prowadzącego (`growth_campaigns.host_name`) i może je wymienić. Zastępuje regułę „`host_name` poza AI” z DEC-024.<br>
Rationale: Waldemar: imię i nazwisko prowadzącego nie są tajne, a opis YouTube zwykle przedstawia prowadzącego.<br>
Consequences: `campaign.host_name` jest w allowliście `material_draft`; pusty lub „—” idzie jako pusty. Prompt każe przepisać imię i nazwisko dokładnie, bez tytułów, stanowisk i biografii, a przy pustym polu nie wymyślać prowadzącego. Prowadzący jest częścią odcisku propozycji, więc jego zmiana unieważnia starą propozycję. Pozostałe zakazy DEC-024 bez zmian (inne materiały, dane klientów, kontaktowe, zamówień i płatności).

## DEC-026

Date: 2026-10-01<br>
Status: ACTIVE<br>
Decision: Drugim materiałem z AI jest `facebook-post`. To samo zadanie `material_draft` dostaje profil `facebook_post_v1` (prompt `material_facebook_post_v1`, schema `material_facebook_post_schema_v1`). Post może korzystać z opisu YouTube, ale tylko zatwierdzonego (`APPROVED` lub `PUBLISHED`). AI nie podaje adresu URL, tylko wstawia znacznik `[LINK DO ZAPISU]`. Opcje: emotikony i hashtagi (3–5), obie domyślnie włączone. Długość: około 800 znaków w prompcie, twardy limit 1500. Zmienia regułę „inne materiały poza AI” z DEC-024 wyłącznie dla tego jednego źródła.<br>
Rationale: Waldemar wybrał Post Facebook jako drugi wycinek (najmniejsze ryzyko, sprawdza powtarzalność wzorca). Wybrał zatwierdzony opis YouTube jako źródło i znacznik linku do ręcznej podmiany. Hashtagi i długość przyjęto jako ustawienia polecane, do zmiany.<br>
Consequences: Allowlista posta: to samo co opis YouTube plus `source_materials.youtube_description` (pusty, gdy opis nie jest zatwierdzony) i `style.hashtags`. Żaden inny materiał nie trafia do AI. Odcisk propozycji posta ma dodatkowo `source_materials`, więc zmiana lub cofnięcie zatwierdzenia opisu YouTube unieważnia starą propozycję. Propozycje są w sesji osobno dla każdego materiału (`material_ai_proposals[klucz]`), więc propozycja posta nie kasuje propozycji opisu YouTube. Walidacja wyjścia bez zmian: tylko `draft` i `change_summary`, bez danych osobowych i bez linków spoza wejścia. Profil opisu YouTube, jego prompt i wersje są bez zmian. Decyzje `material_ai_apply` / `material_ai_reject` mają w `meta` klucz `facebook-post` i wersję promptu posta.

## DEC-027

Date: 2026-10-01<br>
Status: ACTIVE<br>
Decision: Każdy z dziesięciu materiałów ma historię wersji treści w nowej tabeli `growth_artifact_versions`. Wersja powstaje przy każdym zapisie, który zmienia szkic: „Zapisz materiał”, „Zastosuj” szkicu AI i przywrócenie. Sama zmiana statusu nie tworzy wersji. Trzymane jest ostatnie 20 wersji na materiał. Wersję można podejrzeć i przywrócić.<br>
Rationale: Waldemar: po pochopnym „Zastosuj” albo ręcznej zmianie dobry tekst był nie do odzyskania, bo artifact trzyma tylko aktualną treść. Wybrał podgląd z przywracaniem, wersję przy każdej zmianie treści, limit 20 i wszystkie materiały.<br>
Consequences: Wiersz wersji ma `growth_artifact_id`, `version` (ten sam numer co `growth_artifacts.version` po zapisie), `source` (`baseline`, `manual`, `ai_apply`, `restore`), `restored_from_version`, `payload` (`status`, `draft`), autora i `created_at`. Materiał zapisany przed wdrożeniem dostaje przy pierwszej zmianie treści wersję `baseline` z dotychczasowym tekstem. Przywrócenie wymaga potwierdzenia w modalu Bootstrap z podglądem. Zapisuje stary tekst jako nową wersję (`restore`) ze statusem `DRAFT` i nigdy nie przepisuje historii. Przywrócenie nie tworzy `growth_decisions`, tak jak ręczny zapis. Zmienia szkic, więc oczekująca propozycja AI dla tego materiału staje się nieaktualna. Starsze wersje ponad 20 są usuwane przy zapisie. Wersja z tekstem identycznym jak wcześniejsza ma znaczek „ten sam tekst co vN”, wskazujący najstarszą wersję z tym tekstem; powtórzenia nie są scalane. Koncepcja nadal ma 5 wersji w sesji; tej decyzji to nie zmienia.

## DEC-028

Date: 2026-10-01<br>
Status: ACTIVE<br>
Decision: Trzecim materiałem z AI jest `main-graphic`, jako tekstowy brief grafiki. To samo zadanie `material_draft` dostaje profil `graphic_brief_v1` (prompt `material_graphic_brief_v1`, schema `material_graphic_brief_schema_v1`). AI zwraca osobne pola, a aplikacja składa z nich szkic z etykietami. Termin i prowadzącego wstawia aplikacja, nie model. Brief obejmuje dwa formaty: 16:9 (1920×1080) i kwadrat (1080×1080). Obrazów nie generujemy i nie łączymy się z Canvą.<br>
Rationale: Waldemar wybrał grafikę jako pierwszy krok w kierunku obrazu z AI i szablonu Canvy (roadmapa). Osobne pola ułatwią później wypełnianie szablonu Canvy. Termin z aplikacji, bo modele mylą dzień tygodnia (startowy szkic miał błędną „Środę”). PNE nie ma jeszcze stałych kolorów i fontów marki, więc AI proponuje kierunek, a sugestie właściciela idą w dodatkowej instrukcji.<br>
Consequences: Zawsze w briefie: formaty, nagłówek, termin, kierunek wizualny. Opcjonalne, z checkboxami domyślnie włączonymi (`style.elements`): podtytuł, prowadzący, wezwanie do działania, opis obrazu dla AI (bez tekstu, logotypów i rozpoznawalnych osób), tekst alternatywny. Wyjście: `headline`, `subtitle`, `cta`, `visual_direction`, `image_prompt`, `alt_text`, `change_summary`; brak pola, pole dodatkowe albo pusty nagłówek, kierunek lub podsumowanie odrzucają odpowiedź. Wyłączone elementy są pomijane przy składaniu, nawet jeśli model je zwróci. `campaign.live_label` (np. „wtorek, 6 października 2026, godz. 20:00”) liczy aplikacja po polsku. Źródła: kierunek, koncepcja, prowadzący i termin, bez innych materiałów; Waldemar zostawia otwarte, czy później dodać zatwierdzony opis YouTube. Szkic przechodzi przez historię wersji (DEC-027) i Zastosuj / Odrzuć jak pozostałe materiały.

## DEC-029

Date: 2026-10-02<br>
Status: ACTIVE<br>
Decision: Dwie zmiany dla `main-graphic`. Po pierwsze, brief grafiki korzysta z zatwierdzonego opisu YouTube tak jak post Facebook (prompt `material_graphic_brief_v2`, schema bez zmian). Po drugie, na stronie grafiki działa generator obrazu przez OpenAI z podglądem i galerią: model `gpt-image-1`, jakość `medium`, jeden obraz w jednym formacie na kliknięcie (poziomy 16:9 albo kwadrat). Canva i inni dostawcy obrazów są poza zakresem.<br>
Rationale: Waldemar: „1. Tak 2. na razie tylko przez OpenAI 3. najpierw dodajmy generator grafiki z podglądem na stronie”, a potem odpowiedzi 1A, 2C, 3A, 4A, 5A, 6B. Opis YouTube jest opcjonalny (1A). Domyślnie obraz jest bez napisów, a napis jest checkboxem domyślnie wyłączonym (2C), bo modele mylą polskie znaki i cyfry. Jeden format na kliknięcie oznacza jeden koszt (3A). Wybrał `gpt-image-1` zamiast tańszego `gpt-image-1-mini` (6B).<br>
Consequences: Opis YouTube trafia jako `source_materials.youtube_description` (pusty, gdy nie jest zatwierdzony) i jest częścią odcisku propozycji briefu, więc jego zmiana unieważnia starą propozycję. Generator: opis obrazu jest wstępnie wypełniony sekcją „Opis obrazu dla AI” zapisanego briefu (albo „Kierunek wizualny”) i można go poprawić (4A). Aplikacja dokleja stałe zasady (kompozycja do formatu, bez tekstu, logotypów i rozpoznawalnych osób). Z checkboxem dokleja nagłówek z briefu i termin liczony przez aplikację. Opis przechodzi przez filtr danych osobowych i adresów URL. OpenAI robi 1536×1024 albo 1024×1024. Aplikacja przycina środek (GD) do 1920×1080 albo skaluje do 1080×1080 i zapisuje JPEG. Pliki są prywatne (`storage/app/private/growth-os/images/{artifact}/…`, dysk `local`) i dostępne tylko przez trasę za `growth_os.access` (5A). Tabela `growth_artifact_images` trzyma format, wymiary, ścieżkę, opis, źródło (`openai` / `simulation`), model, jakość, wersję promptu, `is_selected` i autora. Galeria: ostatnie 10 obrazów na materiał, a wybrana grafika główna nigdy nie jest usuwana automatycznie. Przyciski: Pobierz, Wybierz jako grafikę główną, Usuń (modal Bootstrap). Limit: 10 obrazów dziennie na użytkownika, osobny od limitu tekstu. Obwód awaryjny jest osobny od tekstu. Bez ponawiania przy błędzie, bo obraz jest płatny. Log `growth_ai`: `task_type=material_image`, rozmiar, jakość, czas, szacowany koszt z cennika w `.env`, bez opisu obrazu. Przy wyłączonym `GROWTH_AI_ENABLED` powstaje lokalny obraz zastępczy bez wywołania OpenAI. Wybór grafiki głównej nie zmienia statusu materiału i nie tworzy `growth_decisions`. Generowanie trwa do 1–2 minut w jednym żądaniu HTTP, więc na produkcji serwer nie może przerywać żądań przed upływem tego czasu.

## DEC-030

Date: 2026-10-02<br>
Status: ACTIVE<br>
Decision: Generator obrazu (DEC-029) domyślnie używa `gpt-image-2` zamiast `gpt-image-1`, jakość zostaje `medium`. Obok generowania od zera jest nowa akcja „Utwórz wersję kwadratową” przy obrazie poziomym: te same elementy rozmieszczone na nowo w kwadracie, a nie przycięcie.<br>
Rationale: Waldemar zapytał o najnowszy model obrazów, a na wariant „A” (przejście na `gpt-image-2`) odpowiedział: „A, oraz chciałbym żeby potem grafika w formacie kwadratowym miała te same elementy ale poprzesuwane tak żeby zmieściła się w takim formacie a nie żeby było to zwykłe skadrowanie (przycięcie)”. `gpt-image-2` robi natywnie 16:9, więc obraz poziomy nie traci brzegów.<br>
Consequences: Rozmiary zależą od modelu (`GraphicImageTask::providerSize`). Dla `gpt-image-2` poziomy to 2048×1152 skalowane do 1920×1080 bez przycinania, a kwadrat to 1024×1024 skalowany do 1080×1080. Dla innych modeli (np. `gpt-image-1` ustawiony w `GROWTH_AI_IMAGE_MODEL`) zostają rozmiary z DEC-029 z przycięciem i podpowiedzią w prompcie. Prompt obrazu ma wersję `material_graphic_image_v2`. Wersja kwadratowa idzie przez `POST /images/edits` (multipart, obraz poziomy jako `image[]`, rozmiar 1024×1024) z promptem `material_graphic_square_adapt_v1`: przekomponuj do 1:1, zachowaj te same elementy, styl i kolory, nie przycinaj ani nie zniekształcaj. Opis i napisy pochodzą z obrazu źródłowego, a nie z bieżącego briefu: tabela `growth_artifact_images` dostaje `source_image_id` oraz `overlay_headline` i `overlay_date`, zapisywane przy generowaniu. Akcja działa tylko dla obrazów poziomych, liczy się do limitu 10 obrazów dziennie, korzysta z tego samego obwodu awaryjnego i w symulacji tworzy obraz zastępczy. W galerii kwadrat z przeróbki ma znaczek „Kwadrat z poziomego #id”. Koszt szacunkowy (`medium`): poziomy około 0.042 USD, kwadrat od zera 0.053 USD, wersja kwadratowa z poziomego około 0.07 USD, bo płaci się też za obraz wejściowy (`GROWTH_AI_IMAGE_COST_LANDSCAPE`, `_SQUARE`, `_ADAPT`). Log ma pole `image_kind` (`landscape`, `square`, `square_from_landscape`). `gpt-image-2` może wymagać weryfikacji organizacji w OpenAI.

## DEC-031

Date: 2026-10-02<br>
Status: ACTIVE<br>
Decision: Materiał można wyłączyć w jednym projekcie webinaru nowym statusem „Nie dotyczy” (`SKIPPED`) na liście „Status materiału”. Nie ma osobnego przełącznika.<br>
Rationale: Waldemar chce wyłączyć rodzaj materiału, z którego w danym projekcie nie korzysta. Odpowiedzi: 1A (status, nie przełącznik), 2 — materiał zostaje w tym samym miejscu na liście, tylko wyszarzony, „bo nie zmienia kolejności”, 3A (blokada edycji i AI), 4A (materiały krytyczne też można wyłączyć), 5A (wyłączony opis YouTube nie jest źródłem AI). Wyłączenie dotyczy tylko tego projektu, a nie przyszłych.<br>
Consequences: Bez migracji: status `SKIPPED` trafia do payloadu artifactu, a `growth_artifacts.status` dostaje istniejące `archived`. Wyłączony materiał nie jest brany pod uwagę w „Następnym kroku” ani w liczniku elementów krytycznych (formularz zapisu, mailing przypominający, scenariusz prowadzącego). Na liście materiałów ma szary pasek, przygaszenie i znaczek „Nie dotyczy”, ale nadal da się go otworzyć, żeby zmienić status. Na stronie materiału szkic jest tylko do odczytu, a „Poproś AI o szkic”, „Zastosuj” propozycji AI, przywracanie wersji, „Generuj obraz” i „Utwórz wersję kwadratową” są zablokowane w widoku i w kontrolerze. „Odrzuć” propozycji, podgląd historii i galeria obrazów (pobieranie, wybór, usuwanie) działają. Zapis przy wyłączonym materiale zmienia tylko status i nie nadpisuje szkicu, więc ponowne włączenie przywraca szkic sprzed wyłączenia. Opis YouTube ze statusem „Nie dotyczy” nie jest zatwierdzony, więc post Facebook i brief grafiki powstają tylko z kierunku i koncepcji.

## DEC-032

Date: 2026-10-02<br>
Status: ACTIVE<br>
Decision: „Poproś AI o szkic” działa dla materiału „Mailing główny” (`main-mail`) jako czwarty profil zadania `material_draft` (`main_mail_v1`, prompt `material_main_mail_v1`, schema `material_main_mail_schema_v1`). Tylko tekst, bez wysyłki i bez Sendy.<br>
Rationale: Waldemar wybrał mailing główny jako kolejny krok, a potem odpowiedział: 1A, 2A, 3A, 4A, „5. Daj przełącznik radio do wyboru wersji”, 6A, 7A, 8A. Mailing jest pierwszym materiałem, który wprost prowadzi do zapisów.<br>
Consequences: Źródła jak przy poście i briefie grafiki: kierunek, koncepcja, prowadzący, termin z dniem tygodnia (`campaign.live_label`) i zatwierdzony opis YouTube (1A); jego zmiana unieważnia propozycję. AI zwraca osobne pola: dokładnie 3 tematy, preheader, treść i podsumowanie zmian (2A, 3A). Aplikacja składa szkic z etykietami „Temat:” (pierwsza propozycja jako główna), „Inne propozycje tematu:” i „Preheader:”, a pod nimi treść. Zamiast linku `[LINK DO ZAPISU]` (4A). Długość wybiera przełącznik radio „Długość maila”: krótki około 150–250 słów (domyślny) albo dłuższy około 300–450 słów z planem spotkania, akapitem o prowadzącym (bez wymyślonej biografii) i materiałem dodatkowym (5). Zwrot „Dzień dobry,” i forma „Państwo” (6A). Checkbox „Dodaj emotikony do treści maila”, domyślnie wyłączony, emotikony tylko w treści, oraz dodatkowa instrukcja (7A). Podpis „Z pozdrowieniami,”, prowadzący i „Zespół PNE”, bez stopki prawnej i linku do wypisania, bo doda je system mailingowy (8A). Odpowiedź z inną liczbą tematów, pustym polem, polem dodatkowym albo adresem URL spoza wejścia jest odrzucana. Bez migracji. Pozostałe 6 materiałów bez AI. Uzupełnienie tego samego dnia: na stronie mailingu szkic jest rozdzielony na pola „Temat”, „Preheader” i „Treść” (Waldemar: 1A, 2A). Zapis nadal trzyma jeden tekst z etykietami (`MaterialDraftTask::composeMainMail` / `parseMainMail`), więc historia wersji, AI i „Zastosuj” działają bez zmian. Tekst bez etykiet trafia w całości do „Treści”. Dwie pozostałe propozycje tematu od AI są podpowiedziami z przyciskiem „Użyj” i znikają po zapisie, który zachowuje tylko temat z pola. Sendy nie ma pola preheadera, więc przy polu jest przycisk „Kopiuj kod HTML preheadera” (ukryty `div` do wklejenia na początku treści w trybie HTML) i podgląd tego kodu. Pola tematu i preheadera mają liczniki znaków (zalecane do 60 i 40–100). Pod polem Temat lista „Propozycje tematu od AI” pokazuje wszystkie 3 tematy, także pierwotny, więc po wybraniu innego można do niego wrócić. Temat aktualnie w polu ma oznaczenie „w polu”. Pierwszą literę każdego tematu i preheadera z AI aplikacja zamienia na wielką, bo model powielał małe litery z poprzedniego szkicu. Temat nie ma kropki na końcu (zwyczaj w tematach maili), preheader jest zdaniem i może ją mieć.

## DEC-033

Date: 2026-10-02<br>
Status: ACTIVE<br>
Decision: „Poproś AI o szkic” działa dla materiału „Mailing przypominający” (`reminder-mail`) jako piąty profil zadania `material_draft` (`reminder_mail_v1`, prompt `material_reminder_mail_v1`, schema `material_reminder_mail_schema_v1`). Format i edycja jak w mailingu głównym (DEC-032). Tylko tekst, bez wysyłki i bez Sendy.<br>
Rationale: Waldemar wybrał mailing przypominający jako kolejny krok, „ale również z linkiem do zapisu”, a potem odpowiedział: 1A, 2A, 3A, 4B. To jeden z trzech elementów krytycznych projektu, a mechanizm mailingu głównego dało się wykorzystać prawie bez zmian.<br>
Consequences: Przypomnienie trafia do zapisanych i niezapisanych, więc treść ma dwa znaczniki w osobnych liniach: `[LINK DO POKOJU]` dla zapisanych i `[LINK DO ZAPISU]` dla pozostałych (1A). Przełącznik radio „Kiedy wysyłasz przypomnienie”: „Dzień przed webinarem („jutro”)” (domyślnie) albo „W dniu webinaru („dziś”)” (2A). Źródła: kierunek, koncepcja, prowadzący, termin z dniem tygodnia oraz zatwierdzony opis YouTube i zatwierdzony mailing główny (3A). Mailing główny trafia do AI bez dwóch alternatywnych tematów, a prompt każe trzymać się tych samych obietnic bez powtarzania tematu i zdań. Zmiana każdego ze źródeł unieważnia propozycję. Przełącznik długości jak w mailingu głównym, z krótszymi zakresami: krótki około 80–150 słów albo dłuższy około 180–280 słów (4B). Reszta jak w DEC-032: 3 tematy od wielkiej litery, preheader, „Dzień dobry,” i „Państwo”, podpis prowadzącego i „Zespół PNE”, emotikony domyślnie wyłączone, osobne pola Temat, Preheader i Treść, lista propozycji tematu i kopiowanie kodu HTML preheadera. Bez migracji. Wspólna lista źródeł jest w `MaterialDraftTask::sourceMaterialKeys`. Pozostałe 5 materiałów bez AI. Uzupełnienie tego samego dnia: na prośbę Waldemara domyślnie zaznaczona jest opcja „W dniu webinaru („dziś”)” (`REMINDER_DEFAULT_TIMING = same_day`).

## DEC-034

Date: 2026-10-02<br>
Status: ACTIVE<br>
Decision: „Poproś AI o szkic” działa dla materiału „Scenariusz prowadzącego” (`host-script`) jako szósty profil zadania `material_draft` (`host_script_v1`, prompt `material_host_script_v1`, schema `material_host_script_schema_v1`). Wynik to jeden tekst (`draft` i `change_summary`) w jednym polu szkicu, do 12 000 znaków.<br>
Rationale: Waldemar wybrał scenariusz jako kolejny krok, bo prowadzi live, a scenariusz jest elementem krytycznym projektu. Odpowiedzi: „1. A, ale daj też możliwość wpisania dowolnego czasu trwania oprócz tych domyślnych”, 2A, 3A, 4A, 5A.<br>
Consequences: Przełącznik radio „Czas trwania webinaru”: 45, 60 (domyślnie) albo 90 minut lub „Inny” z polem liczby minut od 15 do 240; poza zakresem albo bez liczby formularz pokazuje błąd i nie wywołuje AI (1). Aplikacja liczy godzinę końca (`style.end_time`), więc model nie dodaje czasu sam. Scenariusz to plan blokami z godzinami („20:00–20:05 Intro”), a w każdym bloku są „Cel:”, „Do powiedzenia:” z 2–4 kluczowymi myślami i „Przejście:” (2A). Interakcja to 3–4 linie „Pytanie na czat:” w różnych blokach i blok „Pytania i odpowiedzi” przed zakończeniem (3A). Zakończenie ma delikatne CTA z koncepcji, materiał dodatkowy i jedno spokojne zdanie o dalszej ofercie, gdy w kierunku jest wpisane „co sprzedać później” (4A). Na początku jest „Checklista przed startem” dla prowadzącego, a w Intro zdanie, że spotkanie jest nagrywane (5A). Źródłem jest też zatwierdzony opis YouTube; zmiana go unieważnia propozycję. Bez emotikon, bez Markdown i bez adresów URL (zamiast nich `[LINK DO MATERIAŁU]` albo `[LINK DO ZAPISU]`). Bez migracji. Pozostałe 4 materiały bez AI.
