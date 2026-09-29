# PNE Growth OS — Architecture

Status: architektura koncepcyjna, bez migracji.

## Zasada Główna

`adm.pnedu.pl` pozostaje głównym źródłem prawdy dla PNE Growth OS. Systemy zewnętrzne, takie jak OpenAI, YouTube, Sendy, Meta, Canva lub Google Drive, są wykonawcami konkretnych zadań, ale nie przejmują kanonicznego stanu procesu.

## Przyszłe Obiekty Domenowe

- **Growth Campaign** — strategiczny projekt wokół tematu, eksperta, treści i celu.
- **Topic** — potrzeba, temat lub obszar zainteresowania rynku.
- **Expert** — profil ekspercki oparty o istniejącego prowadzącego / instruktora.
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

## Model Danych v0.1 — Projekt Przed Migracjami

Status: zaakceptowany kierunek minimalny, migracje jeszcze nieutworzone.

Pierwszy trwały model danych ma przechować tylko fundament pracy Growth OS. Nie obejmuje jeszcze Customer Graph, Product Graph, metryk, YouTube, Sendy, publikacji ani zewnętrznych side effectów.

### Tabele Kierunkowe

`growth_campaigns`

- główny workspace strategiczny,
- pola kierunkowe: `name`, `slug`, `type`, `status`, `goal`, `owner_user_id`, `starts_at`, `ends_at`, `summary`,
- przykładowy typ: `webinar`, później także `content_series`, `expert_campaign`, `product_launch`,
- statusy kierunkowe: `draft`, `planning`, `in_progress`, `paused`, `completed`, `archived`.

`growth_topics`

- temat, potrzeba rynku albo hipoteza,
- pola kierunkowe: `title`, `slug`, `summary`, `audience`, `problem`, `promise`, `source`, `status`,
- może istnieć niezależnie od kampanii i być później użyty w wielu kampaniach.

`growth_experts`

- profil ekspercki używany przez Growth OS,
- pola kierunkowe: `display_name`, `role`, `bio`, `expertise`, `source_type`, `source_id`, `status`,
- `source_type/source_id` pozwalają później powiązać eksperta z istniejącym instruktorem/prowadzącym bez twardej zależności w pierwszej migracji.

`growth_artifacts`

- roboczy lub zatwierdzony rezultat pracy,
- pola kierunkowe: `growth_campaign_id`, `type`, `status`, `title`, `summary`, `payload`, `version`, `created_by_user_id`, `approved_at`,
- przykłady typów: `concept`, `agenda`, `email_draft`, `youtube_description`, `lead_magnet_outline`, `ai_proposal`.

`growth_tasks`

- zadania operacyjne wewnątrz kampanii,
- pola kierunkowe: `growth_campaign_id`, `growth_artifact_id`, `title`, `description`, `status`, `assignee_user_id`, `due_at`, `completed_at`,
- nie zastępuje systemu ticketowego; ma prowadzić proces Growth OS krok po kroku.

`growth_decisions`

- decyzje i approvale człowieka,
- pola kierunkowe: `growth_campaign_id`, `growth_artifact_id`, `growth_task_id`, `type`, `status`, `question`, `decision`, `decided_by_user_id`, `decided_at`, `meta`,
- statusy kierunkowe: `pending`, `approved`, `rejected`, `changes_requested`, `superseded`.

### Relacje Minimalne

- `growth_campaigns` może mieć wiele `growth_artifacts`, `growth_tasks` i `growth_decisions`.
- `growth_artifacts` należą do kampanii; mogą opcjonalnie wskazywać temat lub eksperta po dodaniu pivotów.
- `growth_tasks` mogą wskazywać artifact, którego dotyczą.
- `growth_decisions` mogą dotyczyć kampanii, artifactu albo taska.
- Powiązania wiele-do-wielu, które warto rozważyć w migracjach v0.1:
  - `growth_campaign_topic`,
  - `growth_campaign_expert`.

### Zasady v0.1

- Migracje należą do `pneadm`, bo model danych dotyczy bazy `pneadm`.
- Nie zapisujemy jeszcze pełnych promptów, odpowiedzi AI ani danych klientów.
- Pole `payload` może przechowywać strukturalny draft/artifact jako JSON, ale tylko dla treści Growth OS, nie dla PII.
- Każda tabela powinna mieć `timestamps`; soft delete do decyzji przy migracjach, domyślnie tylko tam, gdzie ma sens operacyjny.
- Model ma umożliwić przeniesienie prototypu TIK z sesji do DB bez budowania dużego dashboardu.

### Poza Zakresem v0.1

- Customer / Organization,
- Content / Product Graph,
- Event / Activity / Metrics,
- publikacje i wysyłki,
- provider fallback,
- RAG, embeddings i vector DB,
- automatyczne działania zewnętrzne.

## Obecny Etap 0.3.2

Obecnie nie ma jeszcze tabel domenowych Growth OS. Prototyp działa tylko w sesji HTTP i nie zapisuje stanu do bazy.

Nie tworzymy jeszcze migracji. Następny krok po akceptacji modelu v0.1 to przygotowanie migracji w `pneadm/database/migrations/`.

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
