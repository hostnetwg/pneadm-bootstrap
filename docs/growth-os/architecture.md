# PNE Growth OS — Architecture

Status: kampania i koncepcja zapisują się z prototypu; reszta procesu zostaje w sesji.

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
- pola: `id`, `name`, `type`, `status`, `goal`, `owner_user_id`, `primary_instructor_id`, `working_topic`, `summary`, `live_at`, `starts_at`, `ends_at`, `timestamps`,
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
└── hasMany GrowthDecision

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

## Obecny Etap 0.3.2

Tabele domenowe v0.1 są w migracji `database/migrations/2026_09_29_191500_create_growth_os_v0_1_tables.php`. Modele są w `app/Models/GrowthOS/`. Istniejący ekran projektu zapisuje kampanię przy utworzeniu, artifact `direction` przy „Zapisz kierunek” i przy zatwierdzeniu kierunku, artifact `concept` przy ręcznym zapisie i przy „Zastosuj” oraz dziesięć materiałów typu `material` przy „Zapisz materiał”. Odświeżenie w tej samej sesji czyta te dane z bazy. Propozycja AI i prowadzący zostają w sesji.

Po zalogowaniu bez sesji wraca ostatnia kampania właściciela, kierunek, artifact `concept`, decyzje przy kierunku i koncepcji, 9 zadań operacyjnych oraz zapisane materiały (status i szkic). Zadania mają stabilny `key`, termin liczony od `live_at` oraz w UX tylko `todo` i `done`. Nie tworzą decyzji i nie zmieniają następnego kroku. Zapis materiału też nie tworzy decyzji. Etykieta „Opublikowane / zaplanowane” jest tylko w `payload.status`; kolumna artifactu dostaje `approved`. Następny krok to trwały prowadzący.

Jedyną rzeczywistą integracją zewnętrzną pilotażu jest opcjonalna rewizja koncepcji przez OpenAI:

```text
Etap Koncepcja
→ GrowthAiService
→ ConceptRevisionTask (allowlista danych, prompt i schema)
→ GrowthAiProvider
→ OpenAiProvider
→ OpenAI Responses API
→ parsowanie i walidacja
→ propozycja w sesji
→ jawne Zastosuj / Odrzuć
```

Logika etapu Koncepcja nie zależy bezpośrednio od endpointu ani SDK OpenAI. Provider i model są konfiguracją centralną. W pilotażu istnieje tylko implementacja OpenAI; nie ma automatycznego routingu ani fallbacku do innego dostawcy.

Stan projektu i pełna propozycja pozostają w sesji HTTP. Log plikowy przechowuje wyłącznie minimalne metadane techniczne wywołania, bez promptu, odpowiedzi i treści koncepcji.

## Granice

Growth OS nie może wprowadzać synchronicznych zależności do:

- zamówień,
- płatności,
- faktur,
- certyfikatów,
- provisioningu pnedu.pl.

Moduł pozostaje addytywny i odwracalny przez feature flag.
