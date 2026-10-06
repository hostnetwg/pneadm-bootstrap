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
Consequences: Klucz `direction`, `type=direction`, `schema_version=1`. `payload`: `why_now`, `audience`, `problem`, `takeaway`, `sell_later`. Zapis jest jawny. Zatwierdzenie tworzy decyzję `approved` i utrwala bieżące pola. Cofnięcie oznacza poprzednią decyzję jako `superseded` i dodaje `changes_requested`. Edycja zatwierdzonego kierunku też oznacza decyzję jako `superseded`, bez nowej decyzji. Od DEC-041 „Zastosuj” przy koncepcji nie zapisuje odbiorców z powrotem do kierunku i nie cofa jego zatwierdzenia. Prowadzący i propozycja AI nadal nie są trwałe. Trwałość prowadzącego opisuje późniejszy DEC-023.

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
Consequences: Przełącznik radio „Czas trwania webinaru”: 45, 60 (domyślnie) albo 90 minut lub „Inny” z polem liczby minut od 15 do 240; poza zakresem albo bez liczby formularz pokazuje błąd i nie wywołuje AI (1). Aplikacja liczy godzinę końca (`style.end_time`), więc model nie dodaje czasu sam. Scenariusz to plan blokami z godzinami („20:00–20:05 Intro”), a w każdym bloku są „Cel:”, „Do powiedzenia:” z 2–4 kluczowymi myślami i „Przejście:” (2A). Interakcja to 3–4 linie „Pytanie na czat:” w różnych blokach i blok „Pytania i odpowiedzi” przed zakończeniem (3A). Zakończenie ma delikatne CTA z koncepcji, materiał dodatkowy i jedno spokojne zdanie o dalszej ofercie, gdy w kierunku jest wpisane „co sprzedać później” (4A). Na początku jest „Checklista przed startem” dla prowadzącego, a w Intro zdanie, że spotkanie jest nagrywane (5A). Od 2026-10-06 scenariusz **nie** dostaje opisu YouTube (tylko kierunek, koncepcja i czas); prompt `material_host_script_v3`. Od tego samego dnia: tryby jak w DEC-036 — „Poproś AI o nowy szkic” (`generate`, bez obecnego scenariusza), „Popraw mój szkic” (`refine`, `author_draft`) i „Popraw ponownie” (`iterate`). Eksport PDF do ChatGPT.com: `bundle` (prompt + dane) albo `data` (same dane), bez limitu `max_input_chars`. Bez emotikon, bez Markdown i bez adresów URL (zamiast nich `[LINK DO MATERIAŁU]` albo `[LINK DO ZAPISU]`). Bez migracji. Pozostałe 4 materiały bez AI.

## DEC-035

Date: 2026-10-02<br>
Status: ACTIVE<br>
Decision: Growth OS rozdziela trzy role: Operator (zalogowany `User`), Prowadzący (Presenter) i Głos komunikacji (Communication Voice). Prowadzący to `growth_campaigns.primary_instructor_id` (instruktor z bazy) albo osoba spoza bazy (`primary_instructor_id = null`), a `host_name` jest zawsze kopią imienia i nazwiska z chwili zapisu. Głos komunikacji to nowa kolumna `growth_campaigns.communication_voice_instructor_id`; `null` oznacza „PNE — neutralnie”. Instruktor dostaje pole `instructors.ai_voice_profile` (TEXT), opis stylu pisania dla AI.<br>
Rationale: Brief konsultanta przy HEAD 41ffb55 i odpowiedzi Waldemara 1A (emotikony domyślnie wyłączone), 2A (profil edytuje tylko `super_admin`) i 3A (nowy webinar startuje z głosem PNE). Operator nie musi być prowadzącym ani autorem stylu. Webinar może prowadzić gość, a tekst może być pisany głosem innej osoby niż prowadzący.<br>
Consequences: `User` służy wyłącznie do logowania i audytu. Instruktor istnieje bez konta `User`. Formularz „Zaplanuj webinar TIK” i karta projektu mają wybór „Instruktor z bazy” albo „Inna osoba (spoza bazy)” oraz listę „Głos komunikacji” (aktywni instruktorzy plus aktualnie wybrany, oznaczony „(nieaktywny)”). Głos jest niezależny od prowadzącego i od Operatora. Odtworzenie projektu z bazy przywraca prowadzącego, `host_name` i głos. Do AI trafia tylko imię i nazwisko oraz `ai_voice_profile` instruktora będącego głosem, nigdy e-mail, telefon, bio, `bio_html`, notatki ani inne pola. Profil przechodzi ten sam filtr danych osobowych co reszta wejścia. Bazowe zasady PNE są w `PneVoice` (wersja `pne_voice_v1`); profil instruktora dokłada do nich tylko styl. Pusty profil albo nieaktywny lub usunięty instruktor nie blokuje AI: działa głos PNE, a ekran pokazuje komunikat („Ten instruktor nie ma jeszcze indywidualnego profilu komunikacji.” albo informację o nieaktywnym instruktorze). Profil Waldemara wpisuje administrator w formularzu instruktora; migracja niczego nie wypełnia. Zewnętrzny prowadzący bez rekordu instruktora nie buduje własnej historii stylu. Ograniczenie na przyszłość: głos jest zapisany przy kampanii, nie przy każdej wersji materiału (`growth_artifact_versions`), więc historia nie mówi, jakim głosem powstała starsza wersja. Na razie głosu używa tylko opis YouTube (DEC-036); pozostałe materiały AI działają jak wcześniej. Dwie migracje: `communication_voice_instructor_id` (klucz obcy, `nullOnDelete`) i `ai_voice_profile`.

## DEC-036

Date: 2026-10-02<br>
Status: ACTIVE<br>
Decision: Materiał „Opis YouTube” ma trzy tryby AI: „Poproś AI o nowy szkic” (generate), „Popraw mój szkic” (refine) i „Popraw ponownie” (iterate). Prompt `material_youtube_description_v3`, schema bez zmian (`material_youtube_description_schema_v1`).<br>
Rationale: Waldemar zauważył, że AI zastępowało jego tekst własnym stylem. Brief konsultanta przy HEAD 41ffb55 wprowadza redakcję tekstu autora i poprawki krok po kroku, zaczynając od jednego materiału.<br>
Consequences: Generate pisze od zera i nie dostaje obecnego szkicu. Refine wysyła tekst z pola szkicu, także niezapisany (`author_draft`), z zasadą „Redaguj tekst autora. Nie zastępuj jego głosu swoim.”; puste pole daje komunikat „Najpierw wpisz własny szkic.” bez wywołania AI. Iterate działa na karcie propozycji: „Co jeszcze poprawić?” i „Popraw ponownie” wysyłają poprzednią propozycję (`previous_proposal`) z nową uwagą i tworzą nową propozycję w sesji, bez zapisu materiału, decyzji i wersji. Pusta uwaga daje „Napisz, co jeszcze poprawić.”. Prompt traktuje lokalne polecenia dosłownie (tylko CTA, pierwszy akapit bez zmian, tylko literówki). Wejście oddziela fakty o prowadzącym (`presenter.name`, nadal `host_name` jak w DEC-025) od głosu (`voice.pne_rules`, `voice.personal`); prompt zabrania pierwszej osoby sugerującej, że autor prowadzi webinar, i wymyślonych relacji („zaprosiłem”). Emotikony domyślnie wyłączone (1A). Odcisk propozycji dostaje część `voice`: id instruktora, status, imię i nazwisko, skrót profilu i wersję `PneVoice`. Zmiana głosu, profilu, kierunku, koncepcji, zapisanego szkicu lub prowadzącego unieważnia propozycję. Tryb, numer poprawki i skrót wysłanego tekstu są zapisane przy propozycji, nie w odcisku. Iterate na nieaktualnej propozycji usuwa ją i pokazuje komunikat. Nowy komunikat dla wszystkich materiałów: „Kierunek, koncepcja lub szkic zmieniły się od czasu przygotowania propozycji. Wygeneruj ją ponownie.” „Zastosuj” i „Odrzuć” działają jak wcześniej; decyzja dostaje w meta `ai_mode`, `iteration_count` i `communication_voice_instructor_id`, bez treści. Po „Odrzuć” niezapisany tekst wysłany w refine wraca do pola szkicu. Symulacja lokalna obsługuje wszystkie trzy tryby. Pozostałe materiały AI bez zmian.

## DEC-037

Date: 2026-10-02<br>
Status: ACTIVE<br>
Decision: Formularz **Zaplanuj webinar** (`/growth/projects/create`) ma **Asystenta planowania**. Nowe zadanie `direction_planning` (prompt `direction_planning_v1`, schema `direction_planning_schema_v1`) pomaga zaplanować webinar: aktualność, odbiorców, problem, rezultat i ostrożną decyzję `sell_later` (`nie` / `być może` / `tak`). Pierwsza analiza i odświeżenie wymagają hosted `web_search` w OpenAI Responses API; brak `web_search_call` zamyka wywołanie bez propozycji. Propozycja żyje tylko w sesji. „Użyj tego kierunku” oznacza szkic, ale projekt powstaje dopiero po „Utwórz projekt webinaru”. Zastosowany kierunek jest `DRAFT`, bez automatycznego zatwierdzenia.<br>
Rationale: Brief konsultanta przy HEAD 54c29b9 i odpowiedzi Waldemara: 1A (statyczne pomysły znikają z create, zostają na Pomysłach bez etykiety AI), 2A (puste szkice materiałów i koncepcji), 3B (mocniejszy model researchu: domyślnie `gpt-5.5`, bo to oficjalna ścieżka `web_search`; `GROWTH_AI_RESEARCH_MODEL` pozwala później przełączyć na `gpt-6-astra`), 4A („Użyj tego kierunku” nie tworzy projektu samo), 5B (CTA „Zaplanuj webinar”). Przypadek testowy: „NotebookLM w pracy nauczyciela — od przygotowania lekcji do pracy z dokumentami”.<br>
Consequences: `DirectionPlanningTask` implementuje `GrowthAiResearchTask`. Generate i refresh ustawiają `tools: [{type: web_search}]`, `tool_choice: {type: web_search}` i `include: [web_search_call.action.sources]` w jednym żądaniu ze `json_schema` i `store: false`. Iterate nie wyszukuje; dostaje `previous_proposal` i wymaganą instrukcję. Źródła bierze API (`action.sources` i `url_citation`), nigdy pola URL wymyślone przez model. Symulacja (`GROWTH_AI_ENABLED=false`) nie udaje researchu ani źródeł. Allowlista wejścia: typ, temat, cel, data, instrukcja, poprzednia propozycja; bez głosu komunikacji, prowadzącego i innych materiałów. Prompt każe ignorować instrukcje ze stron. Odcisk to skróty typu, celu i tematu; zmiana unieważnia iterate/refresh i „Użyj tego kierunku” (projekt i tak powstaje, szkic AI nie wchodzi). Nowy projekt nie dostaje już przykładowych treści Canva: puste pola kierunku, koncepcja tylko z tytułem = temat, puste szkice materiałów. Lista Pomysłów zostaje jako „Przykład tematu”. Wspólny limit dzienny i circuit breaker z innymi zadaniami tekstowymi. Log metadanych ma `task_type=direction_planning`, `web_search_used` i `source_count`, bez promptu, odpowiedzi, treści, PII i URL-i. Bez czatu, bez odkrywania tematu, bez migracji. Istniejące AI materiałów i koncepcji bez zmian (nadal `gpt-5-mini`, bez `web_search`).

## DEC-038

Date: 2026-10-03<br>
Status: ACTIVE<br>
Decision: `/growth/projects` pokazuje **wszystkie** kampanie właściciela. Przy każdej jest „Otwórz projekt webinaru” i „Usuń”. Usunięcie jest trwałe: kampania, kierunek, koncepcja, materiały, historia wersji, decyzje, zadania i pliki obrazów. Potwierdzenie tylko w modalu Bootstrap 5.<br>
Rationale: Waldemar potrzebuje usuwać projekty testowe. Lista pokazywała wyłącznie ostatnią kampanię, więc starsze zostawały w bazie. Wybrany wariant A.<br>
Consequences: Workspace nadal używa jednego projektu w sesji (`tik-webinar-session`). „Otwórz” wczytuje wybraną kampanię do sesji. „Usuń” kasuje tylko kampanię zalogowanego właściciela (404 dla cudzej). Jeśli usuwany projekt był otwarty, sesja jest czyszczona. Brak kosza i `SoftDeletes`. Nie rusza zamówień, kursów ani instruktorów. Bez migracji.

## DEC-039

Date: 2026-10-03<br>
Status: ACTIVE<br>
Decision: Po utworzeniu projektu asystent kierunku zostaje na karcie **Pomysł i kierunek**. „Przygotuj od nowa” (`generate` + `web_search`) układa kierunek z tematu projektu i opcjonalnych sugestii, **bez** opierania się na wypełnionych polach. „Popraw propozycję” (bez wyszukiwania) i „Popraw propozycję — szukaj w Internecie” (z `web_search`) biorą bieżące pola, także niezapisane. Nowa propozycja stoi obok. „Zmień na” wstawia jeden fragment do pola, bez zapisu. „Zastosuj” zapisuje pięć pól jako `DRAFT`. „Odrzuć” nic nie zapisuje. Przy statusie Gotowe asystent jest zablokowany do „Cofnij zatwierdzenie”.<br>
Rationale: Po „Użyj tego kierunku” i „Utwórz projekt webinaru” propozycja znika z formularza tworzenia. Waldemar chce dalej poprawiać kierunek z AI albo ręcznie w otwartym projekcie — oraz móc zacząć od zera z samego tematu i sugestii, gdy obecne pola przeszkadzają.<br>
Consequences: To samo zadanie `direction_planning` (prompt od 2026-10-06: `direction_planning_v2`). Propozycja workspace jest w sesji projektu (`direction_ai_proposal`), osobno od propozycji sprzed utworzenia projektu. Odcisk dotyczy zapisanego kierunku z chwili prośby: niezapisane pola nie blokują „Zastosuj”, a „Zapisz kierunek” albo zatwierdzenie kasuje propozycję. „Zastosuj” nie zmienia tematu projektu i nie zatwierdza kierunku. Tytuły z AI są tylko podpowiedzią. Generate w workspace nie dziedziczy lokalnego modelu z poprzedniej propozycji. Bez migracji i bez czatu.

## DEC-040

Date: 2026-10-03<br>
Status: ACTIVE<br>
Decision: Pusta **Koncepcja webinaru** dostaje opcję „Wygeneruj na podstawie pomysłu i kierunku” na początku listy „Wygeneruj lub zmień”. AI układa pola koncepcji z tematu i pięciu pól kierunku. Tytuł koncepcji nie zmienia tematu w „Pomysł i kierunek”. „Zastosuj” tej opcji nie nadpisuje kierunku.<br>
Rationale: Dotychczasowe opcje tylko poprawiają istniejącą koncepcję. Przy pustych polach nie mają z czego wyjść. Waldemar chce iść dalej tą samą ścieżką AI, bez rozjechania się tytułu z już ustalonym kierunkiem.<br>
Consequences: To nadal `concept_revision`, model `gpt-5-mini`, bez `web_search`. Wejście dostaje blok `direction` przy tej opcji. Symulacja nie wstawia przykładowych treści Canva. Od DEC-041 ten sam blok kierunku dostają też pozostałe opcje listy. Bez migracji.

## DEC-041

Date: 2026-10-03<br>
Status: ACTIVE<br>
Decision: Każda opcja listy „Wygeneruj lub zmień” dostaje zapisany temat i pięć pól kierunku. Przy poprawce istniejącej koncepcji kierunek jest granicą sensu: AI zmienia to, o co prosi opcja, i koryguje zdanie, które kierunkowi zaprzecza. Nie układa koncepcji od nowa. „Zastosuj” nie zmienia pól kierunku i nie cofa jego zatwierdzenia.<br>
Rationale: Kolejne poprawki koncepcji widziały tylko poprzednią koncepcję i mogły odjechać od tematu, problemu i efektu. Kopiowanie odbiorców z propozycji z powrotem do „Dla kogo” rozjeżdżało kierunek w drugą stronę.<br>
Consequences: To nadal `concept_revision`, `gpt-5-mini`, bez `web_search`. Blok `direction` jest przy każdej opcji. Tryb `from_direction` nadal wypełnia pustą koncepcję. Pozostałe opcje redagują zapisaną koncepcję. Puste pola kierunku nie są powodem, żeby coś dopisywać. „Zastosuj” zapisuje tylko koncepcję. Bez migracji.

## DEC-042

Date: 2026-10-03<br>
Status: ACTIVE<br>
Decision: Na grafice głównej logo Platformy i opcjonalne logo sponsora są dokładane jako pliki na gotowy obraz. Model ich nie rysuje. Wersja kwadratowa dostaje te same pliki dopiero po przekomponowaniu.<br>
Rationale: Prośba do modelu o narysowanie logo psuje litery i proporcje. Przekazanie logo w pikselach obrazu poziomego zniekształciłoby je przy wersji kwadratowej.<br>
Consequences: Logo Platformy to plik `public/images/Logo nazwa PNE - white.png`. Logo sponsora jest jedno na kampanię, PNG na dysku prywatnym. Obraz z logo ma czysty plik w `base_path`; do edycji kwadratu idzie ten plik. Migracja `2026_10_03_120000_add_logo_overlay_to_growth_artifact_images`.

## DEC-043

Date: 2026-10-03<br>
Status: ACTIVE<br>
Decision: Brief grafiki ma te same tryby co opis YouTube: nowy brief, poprawa szkicu i kolejna poprawka propozycji. Nagłówek bierze się z ustalonego tematu webinaru. Opis obrazu poprawia się osobno i „Zastosuj” zmienia tylko tę sekcję briefu. Przy gotowym obrazie „Popraw ten obraz” wysyła ten obraz i jedną uwagę do edycji; logo dokłada się potem, a poprzedni obraz zostaje.<br>
Rationale: Szkice materiałów już dostają temat, kierunek i koncepcję, ale nagłówek grafiki był skrótem obok tematu. Generator zdjęcia nie widział kierunku. Poprawka całego briefu mieszałaby opis obrazu z nagłówkiem, a generowanie od zera gubiłoby zdjęcie, które już pasuje.<br>
Consequences: Prompt briefu `material_graphic_brief_v3`. Opis obrazu to zadanie `graphic_image_description`, model `gpt-5-mini`, bez `web_search`. Edycja obrazu używa `POST /images/edits` i czystego pliku z `base_path`. Bez migracji.

## DEC-044

Date: 2026-10-03<br>
Status: ACTIVE<br>
Decision: Post Facebook ma te same trzy kroki co opis YouTube: nowy szkic, poprawa tekstu z pola (także niezapisana) i kolejna poprawka propozycji. Głos komunikacji zostaje tylko przy opisie YouTube.<br>
Rationale: Przy poście był jeden przycisk, który nie brał niezapisanej poprawki i nie pozwalał poprawiać samej propozycji.<br>
Consequences: Prompt `material_facebook_post_v2`. Te same dane co dotychczas: temat, kierunek, koncepcja i zatwierdzony opis YouTube. Bez migracji.

## DEC-045

Date: 2026-10-03<br>
Status: ACTIVE<br>
Decision: Mailing główny ma te same trzy kroki co opis YouTube: nowy szkic, poprawa tematu, preheadera i treści z pól (także niezapisanych) oraz kolejna poprawka propozycji.<br>
Rationale: Przy mailu był jeden przycisk. Poprawka nie brała tekstu, którego właściciel jeszcze nie zapisał, i nie dało się poprawiać samej propozycji.<br>
Consequences: Prompt `material_main_mail_v2`. Mailing przypominający zostaje przy jednym przycisku. Bez migracji.

## DEC-046

Date: 2026-10-03<br>
Status: ACTIVE<br>
Decision: Gdy pojawi się komunikat o wykorzystanym dziennym limicie AI, obok jest „Zresetuj limit”. To samo na Zaplanuj webinar, w projekcie i na materiałach oraz przy błędzie koncepcji bez przeładowania strony.<br>
Rationale: Po wyczerpaniu limitu praca z AI stawała do końca doby. Właściciel ma móc wyzerować licznik świadomie, tak jak przy obrazach.<br>
Consequences: Reset czyści licznik tekstowego AI (`growth-ai:daily`). Limit obrazów zostaje osobny. Potwierdzenie jest w oknie Bootstrap. W logu jest tylko liczba zużytych wywołań, bez treści. Bez migracji.

## DEC-047

Date: 2026-10-04<br>
Status: ACTIVE<br>
Decision: Na mailingu głównym checkbox „Profesjonalny HTML maila” jest domyślnie włączony. AI pisze zwykły tekst, a aplikacja składa z niego mail HTML do wklejenia w Sendy.<br>
Rationale: Właściciel wkleja treść w Sendy w trybie HTML. Swobodny HTML z modelu jest niestabilny, a stały układ daje ten sam wygląd przy każdym webinarze.<br>
Consequences: Prompt `material_main_mail_v3`. Mailing przypominający zostaje zwykłym tekstem. W szkicu jest podgląd i „Kopiuj HTML maila” (preheader na początku). Bez migracji.

## DEC-048

Date: 2026-10-04<br>
Status: ACTIVE<br>
Decision: Treść maila jest w oknie edycji z przełącznikiem Edycja / Kod HTML i podstawowym formatowaniem. To własny edytor, bez zewnętrznej biblioteki.<br>
Rationale: Gotowe edytory (także TinyMCE używany przy lekcjach) przebudowują HTML i psują układ maila do Sendy. Własne okno zostawia tabelę, style w linii i znacznik linku.<br>
Consequences: Przyciski: pogrubienie, kursywa, podkreślenie, listy, link i czyszczenie formatu. Link wstawia się w oknie Bootstrap. Zapis bez zmian nie przepisuje treści. Bez migracji. Od DEC-049 mailing główny używa Tiptap; to okno zostaje przy mailingu przypominającym.

## DEC-049

Date: 2026-10-04<br>
Status: SUPERSEDED by DEC-050<br>
Decision: Mailing główny edytuje treść w Tiptap (pakiety MIT) i wybiera jeden z szablonów układu. AI nadal zwraca zwykły tekst w trybach generate, refine i iterate. Aplikacja składa końcowy HTML.<br>
Rationale: Własne okno z DEC-048 nie dawało cofania ani pewnego formatowania. Pełny HTML maila w edytorze gubi tabelę Sendy, przycisk i znacznik zapisu. Szablon ma być wyborem wyglądu, nie odpowiedzią modelu.<br>
Consequences: Tiptap dostaje tylko akapit, pogrubienie, kursywę, podkreślenie, listy, link, złamanie linii oraz cofnij i ponów. Nie edytuje tabeli, karty 600 px, przycisku „Zapisz się na webinar”, `[LINK DO ZAPISU]`, preheadera ani stopki. Szablony `classic`, `personal` i `minimal` (Klasyczny PNE, Osobisty, Minimalny) mają ten sam układ: nagłówek, treść, przycisk, stopka. Ich HTML jest w `resources/growth-os/mail-templates/`. Pole „Kod HTML” pokazuje gotowy mail i nie służy do zmiany układu. Klucz `template_key` jest w JSON materiału i w historii wersji. Brak klucza oznacza szablon klasyczny. Zmiana szablonu nie zmienia tematu, preheadera ani treści. Apply i odrzucenie propozycji nie zmieniają szablonu. Do AI nie idzie otoczka HTML. Prompt `material_main_mail_v4`. Mailing przypominający bez tej zmiany. Bez migracji.

## DEC-050

Date: 2026-10-05<br>
Status: ACTIVE<br>
Decision: Jeden kanoniczny layout **Sendy PNE** zastępuje classic/personal/minimal. AI/Tiptap pisze tylko treść redakcyjną (wstęp, wartość, punkty). Aplikacja dodaje greeting z `[Name,fallback=]`, kartę webinaru, CTA zapisu i YouTube z pól kampanii, opcjonalne zaświadczenie, opcjonalną ofertę płatnych szkoleń (snapshot) oraz stopkę z `[unsubscribe]`. Ten sam layout stosuje mailing przypominający; jego AI flow bez zmian.<br>
Rationale: Autentyczny mailing Sendy właściciela wymaga kontrolowanych regionów technicznych i sprzedażowych. Wolny CONTENT + prosty shell nie wystarczał. Ceny i Omnibus muszą pochodzić z ADM, nie z AI.<br>
Consequences: Migracja `growth_campaigns.registration_url` i `youtube_live_url`. UI linków na karcie projektu. Checkboxy main-mail: zaświadczenie i oferta płatna (`include_paid_offer`, `show_certificate`, `paid_offer_snapshot` w JSON materiału i historii wersji). Oferta: max 5 kursów `is_paid` + `show_on_pnedu` + `is_active` + `end_date >= now`, ceny z `CoursePriceVariant`, Omnibus z `PriceOmnibusService`. Brak `registration_url` blokuje „Kopiuj HTML”. Brak YouTube tylko ostrzega. Legacy `template_key` mapuje się do `sendy-pne`. Prompt `material_main_mail_v5`. Bez biblioteki szablonów DB, bez Sendy API, bez nowych trybów AI.

## DEC-051

Date: 2026-10-05<br>
Status: ACTIVE<br>
Decision: Każda GrowthCampaign ma `address_form`: `ty` | `panstwo` (domyślnie `ty`). Obowiązuje wszystkie szkice AI kampanii (materiały, kierunek, koncepcja). Głos komunikacji nie nadpisuje formy. Jawna „Dodatkowa instrukcja dla AI” może nadpisać formę tylko dla bieżącej operacji/proposal, bez zmiany ustawienia projektu. Refine ujednolica tekst do aktualnego `address_form` (lub lokalnego override). Iterate kontynuuje świadomy override, dopóki instrukcja go nie odwołuje. Host-script przy `ty` używa naturalnego Wy/Wam wobec grupy; działania jednego prowadzącego: „pokażę”, nie „pokażemy”. „My” tylko dla działań PNE/zespołu. Lokalna symulacja respektuje tę samą politykę. Zmiana settingu nie przepisuje zatwierdzonych materiałów — pokazuje ostrzeżenie.<br>
Rationale: Właściciel pisze mailingi bezpośrednio na Ty; formalne Państwo ma zostać wyborem kampanii, nie hardcodem w promptach. Forma zwrotu to polityka kanału/kampanii, nie styl głosu instruktora.<br>
Consequences: Migracja `growth_campaigns.address_form`. UX w formularzu prowadzącego/głosu (create + karta projektu). Centralna `AddressFormPolicy` wstrzykiwana do promptów. Fingerprint materiałów zawiera `address_form` (stale proposal po zmianie). Greeting Sendy bez zmian.

## DEC-053

Date: 2026-10-06<br>
Status: ACTIVE<br>
Decision: **Wyszukiwanie w sieci** jest lokalnym checkboxem w panelu modelu/wysiłku (wszędzie: create kierunku, karta kierunku, koncepcja, materiały, opis obrazu). Domyślnie **włączone**. Osobny przycisk „Popraw — szukaj w Internecie” znika. Gdy search włączony, a model nie wykona `web_search`, propozycja i tak wraca z informacją (soft-fail), bez blokady. Źródła pokazujemy, gdy search realnie zadziałał.<br>
Rationale: Waldemar chce jeden wzorzec włączania Internetu dla wszystkich elementów Growth OS, zamiast osobnych przycisków tylko przy kierunku.<br>
Consequences: `ai_web_search` w `GrowthAiRequestOptions` + checkbox w `ai-execution-controls`. `GrowthAiService` / OpenAI provider: opcjonalny `web_search` także dla koncepcji i materiałów; `require_web_search` hard-fail usunięty. Prompt kierunku `direction_planning_v3`. Legacy `planning_mode=refresh` mapuje się na iterate + search. Bez migracji.

## DEC-052

Date: 2026-10-06<br>
Status: ACTIVE<br>
Decision: Growth OS ma trwałe **domyślne ustawienia wykonania AI** (model + reasoning effort) oraz **lokalny override** przy każdym requestcie tekstowym. Katalog modeli jest kontrolowaną allowlistą (Luna / Sol / Astra / gpt-5-mini / gpt-5.5). Domyślnie: general = `gpt-6.1-sol` + `medium`, research = `gpt-6.1-sol` + `high`. Lokalny wybór dotyczy tylko bieżącego workflow/proposal i nie zmienia globalnych ustawień. Iterate dziedziczy model/effort z poprzedniej propozycji, chyba że użytkownik zmieni. Model/effort **nie** wchodzą do fingerprintu treści. Image API pozostaje osobne (bez reasoning effort w tym etapie).<br>
Rationale: Właściciel chce jednym kliknięciem sensowne defaulty, a przy słabym wyniku — świadomie mocniejszy model i większy wysiłek, bez przebudowy całego Growth OS.<br>
Consequences: Tabela `growth_ai_settings` (singleton). Ekran `/growth/ai-settings`. Kontrolka Model/Wysiłek przy requestach AI (kierunek, koncepcja, materiały, opis obrazu). `GrowthAiExecutionOptions` + `GrowthAiModelCatalog`. Provider Responses API dostaje `model`, `reasoning.effort` i effort-aware `max_output_tokens`. Log: `reasoning_effort`, `selection_source`; koszt ze stawek katalogu wybranego modelu. Bez image model picker w tym etapie.
