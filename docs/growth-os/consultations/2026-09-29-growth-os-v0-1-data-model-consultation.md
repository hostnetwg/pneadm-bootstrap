# Consultation Package — PNE Growth OS v0.1 Data Model

Date: 2026-09-29  
Repository: `hostnetwg/pneadm-bootstrap`  
Area: PNE Growth OS / PNE Rozwój  
Purpose: external ChatGPT consultation before creating database migrations

## Prompt For External ChatGPT

Pracujemy nad projektem **PNE Growth OS / PNE Rozwój** w repozytorium:

```text
hostnetwg/pneadm-bootstrap
```

Twoja rola: zewnętrzny konsultant produktu i architektury. Nie koduj, nie proponuj gotowych migracji jako finalnego rozwiązania do wklejenia. Oceń koncepcję, ryzyka, braki i kolejność decyzji.

Przed odpowiedzią przeczytaj w tej kolejności:

1. `docs/growth-os/CURRENT.md`
2. `docs/growth-os/architecture.md`
3. `docs/growth-os/decisions.md`
4. `docs/growth-os/roadmap.md`
5. `docs/growth-os/CONSULTING.md`
6. `docs/pne-growth-os/06-AI.md`

Kontekst:

- Etap 0.3.2 jest wdrożony i zweryfikowany: wąski pilotaż OpenAI dla etapu **Koncepcja webinaru TIK**.
- AI tworzy tylko propozycję. Aktualna koncepcja zmienia się dopiero po kliknięciu **Zastosuj**.
- Stan prototypu nadal działa w sesji HTTP, bez DB Growth OS.
- Dokumentacja została zaktualizowana pod minimalny model danych v0.1.
- Przed migracjami chcemy skonsultować model i uniknąć zbyt szerokiego schematu.

Aktualnie rozważany minimalny model danych v0.1:

- `growth_campaigns`
- `growth_topics`
- `growth_experts`
- `growth_artifacts`
- `growth_tasks`
- `growth_decisions`
- możliwe pivoty:
  - `growth_campaign_topic`
  - `growth_campaign_expert`

Najważniejsze zasady:

- `Growth Campaign` nie jest tym samym co istniejący `MarketingCampaign`.
- `Course` nie jest tym samym co `Growth Campaign`.
- `Course` = konkretne szkolenie / wydarzenie z terminem.
- `Growth Campaign` = strategiczny projekt wokół tematu, eksperta, treści i celu.
- Nie budujemy teraz Customer Graph, Metrics, Product Graph, publikacji, wysyłek, RAG ani integracji wykonawczych.
- Migracje, jeśli powstaną, należą do projektu `pneadm`, bo tabele będą w bazie `pneadm`.
- Nie chcemy zapisywać pełnych promptów, odpowiedzi AI, danych klientów ani PII.

## Pytania Konsultacyjne

1. Czy minimalny zestaw tabel v0.1 jest właściwy przed migracjami?
2. Czy `growth_artifacts` jako elastyczna tabela z `payload` JSON to dobry kompromis na tym etapie, czy grozi chaosem?
3. Czy `growth_decisions` powinno być osobną tabelą już w v0.1, czy decyzje powinny być na razie stanem artifact/task?
4. Czy `growth_experts` powinno od razu mieć polymorphic `source_type/source_id`, czy lepiej twardo powiązać z istniejącym prowadzącym/instruktorem dopiero później?
5. Jakie pola są konieczne w pierwszych migracjach, a jakie lepiej zostawić na później?
6. Jakie indeksy/unikalności warto zaplanować od razu?
7. Jak uniknąć zbudowania zbyt dużego dashboardu i jednocześnie mieć trwały stan procesu?
8. Jakie ryzyka prywatności lub utrzymania widzisz w obecnym projekcie v0.1?
9. Czy przenoszenie prototypu TIK z sesji do DB powinno nastąpić od razu po migracjach, czy dopiero po stabilizacji schematu?
10. Co powinno być jednoznacznym kryterium sukcesu v0.1?

## Oczekiwany Output

Odpowiedz po polsku, krótko i konkretnie:

1. Ocena: czy model v0.1 jest właściwy / za szeroki / za wąski.
2. Najważniejsze ryzyka.
3. Proponowane poprawki przed migracjami.
4. Minimalny zestaw tabel i relacji, który rekomendujesz.
5. Lista decyzji, które Waldemar powinien podjąć przed kodowaniem.
6. Sugestia kolejności prac dla Cursor AI.

Nie zakładaj, że funkcje z roadmapy już istnieją. Źródłem aktualnego stanu jest `CURRENT.md`, a dla implementacji aktualny kod.

## Relevant Files

- `docs/growth-os/CURRENT.md`
- `docs/growth-os/architecture.md`
- `docs/growth-os/decisions.md`
- `docs/growth-os/roadmap.md`
- `docs/growth-os/CONSULTING.md`
- `docs/pne-growth-os/06-AI.md`
- `app/Support/GrowthOS/DemoTikWebinarProject.php`
- `app/Http/Controllers/GrowthOS/ProjectController.php`
- `resources/views/growth-os/projects/show.blade.php`

## Notes For Cursor AI After Consultation

After receiving feedback, compare it against:

- `docs/growth-os/CURRENT.md`
- `docs/growth-os/decisions.md`
- current Laravel conventions in `pneadm`
- migration location rule: tables in database `pneadm` → migrations in `pneadm/database/migrations/`

Do not create migrations until Waldemar accepts the final table scope.
