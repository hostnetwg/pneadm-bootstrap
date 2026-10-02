# PNE Growth OS — Architecture

Status: kampania, prowadzący, kierunek, koncepcja, decyzje, 9 zadań operacyjnych i 10 materiałów zapisują się z prototypu. Propozycje AI (koncepcja oraz szkice opisu YouTube i posta Facebook) zostają w sesji HTTP.

## Zasada Główna

`adm.pnedu.pl` pozostaje głównym źródłem prawdy dla PNE Growth OS. Systemy zewnętrzne, takie jak OpenAI, YouTube, Sendy, Meta, Canva lub Google Drive, są wykonawcami konkretnych zadań, ale nie przejmują kanonicznego stanu procesu.

## Przyszłe Obiekty Domenowe

- **Growth Campaign** — strategiczny projekt wokół tematu, eksperta, treści i celu.
- **Topic** — potrzeba, temat lub obszar zainteresowania rynku; future, nie należy do pierwszego schematu v0.1.
- **Expert** — profil ekspercki oparty o istniejącego prowadzącego / instruktora; future, w v0.1 używamy opcjonalnego powiązania z istniejącym `Instructor`.
- **Artifact** — roboczy rezultat pracy: draft, brief, grafika, konspekt, prompt, materiał.
- **Task** — zadanie przygotowawcze lub operacyjne.
- **Decision / Approval** — decyzja właściciela lub osoby odpowiedzialnej.
- **Content** — opublikowana lub planowana treść.
- **Product** — powiązany produkt, szkolenie, e-book lub oferta.
- **Customer / Organization** — osoba, szkoła, JST, organizacja lub segment relacji.
- **Event / Activity** — zdarzenie, interakcja, publikacja, wysyłka lub aktywność.
- **Metrics** — metryki skuteczności dopasowane do celu.

## Ważne Rozróżnienia

### Growth Campaign ≠ MarketingCampaign

`MarketingCampaign` w istniejącym systemie dotyczy kampanii atrybucyjnej, linków, UTM i analityki marketingowej.

`Growth Campaign` to strategiczny projekt pracy: temat, ekspert, cel, treści, decyzje, zadania i wyniki.

### Course ≠ Growth Campaign

`Course` to konkretne szkolenie lub wydarzenie z terminem.

`Growth Campaign` może prowadzić do `Course`, może korzystać z istniejącego `Course`, albo w ogóle nie mieć produktu sprzedażowego.

## Model Danych v0.1

Status: zaakceptowany zakres domenowy; migracja i modele `App\Models\GrowthOS` istnieją. Zapis prototypu jeszcze nie.

Pierwszy trwały model danych ma utrwalić już zweryfikowany proces Growth OS, a nie budować pełny Topic Graph, Expert Graph, Customer Graph ani system publikacji. v0.1 obejmuje tylko cztery główne tabele:

- `growth_campaigns`,
- `growth_artifacts`,
- `growth_tasks`,
- `growth_decisions`.

Nie tworzymy w v0.1 tabel `growth_topics`, `growth_experts`, `growth_campaign_topic` ani `growth_campaign_expert`. Temat pozostaje częścią kampanii/koncepcji, a ekspert opiera się na istniejącym `App\Models\Instructor`.

### Tabele v0.1

`growth_campaigns`

- główny workspace strategiczny,
- pola: `id`, `name`, `type`, `status`, `goal`, `host_name`, `owner_user_id`, `primary_instructor_id`, `working_topic`, `summary`, `live_at`, `starts_at`, `ends_at`, `timestamps`,
- `host_name` to imię prowadzącego wpisane w projekcie, bez powiązania z `instructors`,
- `primary_instructor_id` jest opcjonalnym powiązaniem z istniejącym `instructors.id`,
- `slug` nie jest obowiązkowy w v0.1.

`growth_artifacts`

- roboczy lub zatwierdzony rezultat pracy,
- pola kierunkowe: `id`, `growth_campaign_id`, `key`, `type`, `status`, `title`, `summary`, `schema_version`, `version`, `payload`, `created_by_user_id`, `timestamps`,
- `key` jednoznacznie identyfikuje artifact w kampanii, np. `concept`, `main-mail`, `reminder-mail`, `follow-up`, `youtube-description`, `host-script`,
- `type` określa kategorię artifactu, np. `concept`, `email`, `youtube_description`, `script`, `graphic_brief`,
- `type + schema_version` definiuje kontrakt danych `payload`,
- `payload` nie jest dowolnym workiem JSON bez walidowanego kontraktu,
- `version` jest licznikiem bieżącej wersji, nie pełną historią wersji.

`growth_tasks`

- zadania operacyjne wewnątrz kampanii,
- pola: `id`, `growth_campaign_id`, `key`, `growth_artifact_id`, `title`, `description`, `status`, `assignee_user_id`, `due_at`, `completed_at`, `timestamps`,
- `key` jest stabilnym identyfikatorem zadania w kampanii, niezależnym od tytułu,
- `growth_artifact_id` i `assignee_user_id` są opcjonalne,
- nie zastępuje systemu ticketowego; ma prowadzić proces Growth OS krok po kroku.

`growth_decisions`

- kanoniczny zapis decyzji człowieka,
- pola kierunkowe: `id`, `growth_campaign_id`, `growth_artifact_id`, `growth_task_id`, `type`, `status`, `question`, `decision`, `decided_by_user_id`, `decided_at`, `meta`, `timestamps`,
- `growth_artifact_id`, `growth_task_id`, `question`, `decision`, `decided_by_user_id`, `decided_at` i `meta` są opcjonalne,
- `Artifact.status` odpowiada na pytanie: „w jakim stanie jest materiał?”,
- `Decision` odpowiada na pytanie: „kto, kiedy i jaką decyzję podjął?”.

`growth_artifact_versions` (DEC-027, poza zakresem v0.1)

- historia treści materiału: `growth_artifact_id`, `version`, `source` (`baseline`, `manual`, `ai_apply`, `restore`), `restored_from_version`, `payload` (`status`, `draft`), `created_by_user_id`, `created_at`,
- `unique(growth_artifact_id, version)`, kasowanie razem z artifactem,
- wiersz powstaje tylko przy zmianie szkicu; zostaje ostatnie 20 na artifact,
- przywrócenie dopisuje nowy wiersz, nigdy nie zmienia starych.

### Statusy Kanoniczne

`growth_campaigns.status`

- `draft` — Szkic,
- `planning` — Planowanie,
- `preparing` — Przygotowanie,
- `ready` — Gotowy,
- `live` — LIVE,
- `follow_up` — Follow-up,
- `completed` — Zakończony,
- `paused` — Wstrzymany,
- `cancelled` — Anulowany,
- `archived` — Zarchiwizowany.

Nie używamy osobnego statusu `scheduled`; termin wydarzenia wynika z pól daty/czasu, a nie ze statusu procesu.

`growth_artifacts.status`

- `not_started` — Nie rozpoczęto,
- `draft` — Wersja robocza,
- `review` — Do sprawdzenia,
- `approved` — Zatwierdzone,
- `archived` — Zarchiwizowane.

Nie dodajemy statusu `published`; publikacja będzie później osobną domeną Content / Distribution / Integration.

`growth_tasks.status`

- `todo` — Do zrobienia,
- `in_progress` — W trakcie,
- `blocked` — Zablokowane,
- `done` — Gotowe,
- `cancelled` — Anulowane.

`growth_decisions.status`

- `pending` — Do decyzji,
- `approved` — Zaakceptowano,
- `rejected` — Odrzucono,
- `changes_requested` — Do poprawy,
- `superseded` — Zastąpiona nowszą decyzją.

### Relacje v0.1

```text
GrowthCampaign
├── belongsTo User jako owner
├── belongsTo Instructor jako primaryInstructor (nullable)
├── hasMany GrowthArtifact
├── hasMany GrowthTask
└── hasMany GrowthDecision

GrowthArtifact
├── belongsTo GrowthCampaign
├── belongsTo User jako createdBy (nullable)
├── hasMany GrowthTask
├── hasMany GrowthDecision
└── hasMany GrowthArtifactVersion (DEC-027)

GrowthTask
├── belongsTo GrowthCampaign
├── belongsTo GrowthArtifact (nullable)
├── belongsTo User jako assignee (nullable)
└── hasMany GrowthDecision

GrowthDecision
├── belongsTo GrowthCampaign
├── belongsTo GrowthArtifact (nullable)
├── belongsTo GrowthTask (nullable)
└── belongsTo User jako decidedBy (nullable)

User
└── hasMany GrowthCampaign jako ownedGrowthCampaigns

Instructor
└── hasMany GrowthCampaign jako primaryGrowthCampaigns
```

Nie tworzymy w v0.1 innych grafów ani pivotów.

### Unikalności I Indeksy Kierunkowe

- wszystkie FK indeksowane,
- `growth_campaigns.status`,
- `growth_campaigns.type`,
- `growth_campaigns.owner_user_id`,
- `growth_artifacts`: `unique(growth_campaign_id, key)`,
- `growth_artifacts`: `index(growth_campaign_id, status)`,
- `growth_tasks`: `unique(growth_campaign_id, key)`,
- `growth_tasks`: `index(growth_campaign_id, status)`,
- `growth_tasks.due_at`,
- `growth_decisions`: `index(growth_campaign_id, status)`.

Nie dodajemy nadmiarowych indeksów „na przyszłość”.

### Soft Delete

Na v0.1 nie zakładamy `SoftDeletes` jako domyślnej zasady dla wszystkich tabel. Preferujemy jawne statusy, takie jak `archived` i `cancelled`. Soft delete może zostać dodany później tam, gdzie pojawi się konkretna potrzeba operacyjna.

### Sukces v0.1

Sukces v0.1 nie oznacza wyłącznie „mamy modele i tabele”. Sukces oznacza, że Waldemar może:

- rozpocząć projekt webinaru TIK,
- zapisać koncepcję,
- zamknąć przeglądarkę,
- wrócić później,
- kontynuować ten sam projekt,
- zachować artifacty,
- zachować zadania,
- zachować decyzje człowieka,
- nadal korzystać z prostego flow UX,
- nadal mieć obowiązkowe jawne **Zastosuj / Odrzuć** dla AI.

### Poza Zakresem v0.1

- `growth_topics`,
- `growth_experts`,
- pivoty tematów i ekspertów,
- osobna tabela `growth_artifact_versions`,
- Customer Graph,
- Topic Graph,
- Expert Graph,
- Content / Product Graph,
- Event / Activity / Metrics,
- publikacje i wysyłki,
- provider fallback,
- RAG, embeddings i vector DB,
- automatyczne działania zewnętrzne.

## Obecny Etap: v0.1 + v0.2 (szkice AI opisu YouTube, posta Facebook i briefu grafiki)

Tabele domenowe v0.1 są w migracji `database/migrations/2026_09_29_191500_create_growth_os_v0_1_tables.php`. Modele są w `app/Models/GrowthOS/`. Istniejący ekran projektu zapisuje kampanię przy utworzeniu, artifact `direction` przy „Zapisz kierunek” i przy zatwierdzeniu kierunku, artifact `concept` przy ręcznym zapisie i przy „Zastosuj” oraz dziesięć materiałów typu `material` przy „Zapisz materiał”. Odświeżenie w tej samej sesji czyta te dane z bazy. Utworzenie projektu zapisuje też `host_name`. Propozycja AI zostaje w sesji.

Po zalogowaniu bez sesji wraca ostatnia kampania właściciela, prowadzący (`host_name`), kierunek, artifact `concept`, decyzje przy kierunku i koncepcji, 9 zadań operacyjnych oraz zapisane materiały (status i szkic). Zadania mają stabilny `key`, termin liczony od `live_at` oraz w UX tylko `todo` i `done`. Nie tworzą decyzji i nie zmieniają następnego kroku. Ręczny zapis materiału też nie tworzy decyzji. Etykieta „Opublikowane / zaplanowane” jest tylko w `payload.status`; kolumna artifactu dostaje `approved`. Propozycje AI zostają w sesji i nie wracają po nowym zalogowaniu.

Jedyną rzeczywistą integracją zewnętrzną jest opcjonalne OpenAI w dwóch zadaniach: rewizja koncepcji (`concept_revision`) i szkic materiału (`material_draft`, tylko materiały `youtube-description`, `facebook-post` i `main-graphic`):

```text
Etap Koncepcja / ekran materiału „Opis YouTube”, „Post Facebook” albo „Grafika główna”
→ GrowthAiService (flaga, super_admin, circuit breaker, wspólny limit dzienny, log)
→ ConceptRevisionTask albo MaterialDraftTask (allowlista danych, prompt, schema, walidacja)
→ GrowthAiProvider
→ OpenAiProvider
→ OpenAI Responses API
→ parsowanie i walidacja
→ propozycja w sesji
→ jawne Zastosuj / Odrzuć
```

Oba zadania implementują mały kontrakt `GrowthAiTask` (`type`, `promptVersion`, `schemaVersion`, `instructions`, `schema`). Serwis loguje typ i wersje z zadania, więc nie ma w nim stałych pod koncepcję. Nie ma rejestru zadań ani routera modeli.

`MaterialDraftTask` (DEC-024): prompt `material_youtube_description_v2`, schema `material_youtube_description_schema_v1`, profil `youtube_description_v1`. Wejście to allowlista: `material` (klucz, nazwa, typ), `campaign` (`working_topic`, etykieta celu, `live_date`, `live_time`, strefa aplikacji, `host_name`), pięć pól kierunku, pola koncepcji (`title`, `subtitle`, `promise`, `points`, `plan`, `cta`, `additional_material`), bieżący szkic tego materiału, `style.emojis` i opcjonalna `instruction` właściciela. Bez innych materiałów. `host_name` trafia do AI od DEC-025; pusty lub „—” jest wysyłany jako pusty, a prompt każe przepisać imię i nazwisko bez dopisywania biografii. Wyjście: `draft` i `change_summary`; dodatkowe pola, dane osobowe i linki spoza wejścia odrzucają odpowiedź. Data i godzina idą osobno, a przy kontroli telefonów wzorce dat są pomijane, żeby termin nie wyglądał jak numer telefonu.

Profil `facebook-post` (DEC-026): prompt `material_facebook_post_v1`, schema `material_facebook_post_schema_v1`, profil `facebook_post_v1`. Wejście to ta sama allowlista plus `source_materials.youtube_description` (opis YouTube tylko ze statusem Zatwierdzone lub Opublikowane, w innym wypadku pusty) i `style.hashtags`. Żadne inne materiały. AI wstawia `[LINK DO ZAPISU]` zamiast adresu. Twardy limit odpowiedzi to 1500 znaków. Profil wybiera `MaterialDraftTask::forMaterial($klucz)`; nieobsługiwany klucz daje 404 w kontrolerze.

Profil `main-graphic` (DEC-028): prompt `material_graphic_brief_v1`, schema `material_graphic_brief_schema_v1`, profil `graphic_brief_v1`. Wyjście to osobne pola briefu, a nie `draft`; `MaterialDraftTask::composeGraphicBrief` składa z nich szkic z etykietami. Termin (`campaign.live_label`) liczy `DemoTikWebinarProject::liveLabel` po polsku z dniem tygodnia, a prowadzący pochodzi z kampanii. Model nie wpisuje żadnego z nich. Opcjonalne elementy wybiera `style.elements`. Od DEC-029 prompt `material_graphic_brief_v2` dostaje też `source_materials.youtube_description` (jak post), a odcisk propozycji briefu obejmuje `source_materials`. Listę materiałów z tym źródłem zwraca `MaterialDraftTask::usesYoutubeSource`.

Generator obrazu (DEC-029) jest osobną ścieżką, bo zwraca plik, a nie JSON: `GrowthImageService` → `GrowthAiImageProvider` (`OpenAiImageProvider`, `POST /images/generations`) → `GraphicImageProcessor` (GD: przycięcie środka i skalowanie do 1920×1080 albo 1080×1080, JPEG) → plik prywatny na dysku `local` i wiersz `growth_artifact_images` przypięty do artifactu `main-graphic`. Prompt składa `GraphicImageTask` z opisu właściciela i stałych zasad aplikacji. Serwis ma własny limit dzienny, własny obwód awaryjny i log `task_type=material_image` bez opisu obrazu. Podgląd i pobieranie idą przez trasę kontrolera, która sprawdza, że obraz należy do artifactu tej kampanii i tego materiału. Galeria przycina się do 10 obrazów przy każdym nowym, ale nigdy nie usuwa wybranego (`is_selected`). Od DEC-030 rozmiar u dostawcy zależy od modelu (`GraphicImageTask::providerSize`: `gpt-image-2` robi 2048×1152, więc GD tylko skaluje), a `GrowthImageService::adaptToSquare` wysyła zapisany obraz poziomy do `POST /images/edits` z promptem przekomponowania. Obie ścieżki przechodzą przez wspólne `run()` (symulacja, uprawnienia, obwód, limit, zapis, log z `image_kind`). Kwadrat z przeróbki wskazuje źródło przez `source_image_id`, a napisy bierze z `overlay_headline` / `overlay_date` obrazu źródłowego.

Propozycje są w sesji osobno dla każdego materiału (`material_ai_proposals[klucz]`). Propozycja szkicu ma odcisk sha256 czterech źródeł: pól kierunku, pól koncepcji, bieżącego szkicu materiału i prowadzącego. Przy poście, briefie grafiki i obu mailingach dochodzi piąte źródło, `source_materials`: zatwierdzony opis YouTube, a przy mailingu przypominającym także zatwierdzony mailing główny (`MaterialDraftTask::sourceMaterialKeys`). „Zastosuj” liczy odcisk ponownie i sprawdza oba zatwierdzenia. Różnica czyści propozycję i niczego nie zapisuje. Zgodność zapisuje szkic, ustawia status `DRAFT`, zapisuje artifact `material` i decyzję `material_ai_apply`. „Odrzuć” zapisuje tylko decyzję `material_ai_reject`.

Logika Growth OS nie zależy bezpośrednio od endpointu ani SDK OpenAI. Provider i model są konfiguracją centralną. Istnieje tylko implementacja OpenAI; nie ma automatycznego routingu ani fallbacku do innego dostawcy.

Pełna propozycja AI pozostaje w sesji HTTP. Log plikowy przechowuje wyłącznie minimalne metadane techniczne wywołania (`task_type`, wersje, tokeny, koszt, status), bez promptu, odpowiedzi i treści.

## Granice

Growth OS nie może wprowadzać synchronicznych zależności do:

- zamówień,
- płatności,
- faktur,
- certyfikatów,
- provisioningu pnedu.pl.

Moduł pozostaje addytywny i odwracalny przez feature flag.
