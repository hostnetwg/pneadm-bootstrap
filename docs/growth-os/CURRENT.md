# PNE Growth OS — Current State

Last updated: 2026-09-29 21:15 CEST<br>
Branch: main<br>
Commit: working tree / pending commit (base 4b79387)<br>
Stage: v0.1 material artifacts<br>
Current blocker: none

## 1. Cel projektu

PNE Growth OS / PNE Rozwój to moduł w `adm.pnedu.pl`, który ma prowadzić właściciela krok po kroku przez planowanie i przygotowanie działań rozwojowych PNE: webinarów, treści, kampanii eksperckich, relacji i przyszłej analityki.

## Current UX snapshot

- Entry point: **PNE Rozwój → Zaplanuj webinar TIK**.
- Main workspace: **Projekt webinaru**.
- Primary UX principle: zawsze widoczny **Następny krok**.
- Supporting views: **Dzisiaj / Projekty / Pomysły / Inbox**.
- Concept stage: edycja ręczna, cofnięcie zatwierdzenia, opcjonalna propozycja OpenAI jako wariant do przyjęcia/odrzucenia.

## 2. Aktualny etap

Etap v0.1 — 10 materiałów roboczych zapisuje status i szkic przy kampanii i wraca po zalogowaniu. Kierunek, propozycja AI i prowadzący zostają w przeglądarce, w której powstały.

## 3. Co już działa

- Feature flag `PNE_GROWTH_OS_ENABLED` i dostęp tymczasowo tylko dla `super_admin`.
- Menu **PNE Rozwój**: Dzisiaj, Projekty, Pomysły, Inbox.
- Formularz **Zaplanuj webinar TIK**.
- Sesyjny projekt webinaru (`DemoTikWebinarProject`).
- Modele Eloquent v0.1 i relacje: właściciel, instruktor, materiał, zadanie, decyzja. Workspace używa kampanii, koncepcji, decyzji przy koncepcji, 9 zadań operacyjnych i 10 materiałów roboczych.
- Testy integralności: unikalny klucz materiału w kampanii, puste relacje, usuwanie kampanii razem z dziećmi, czyszczenie opcjonalnych powiązań.
- Utworzenie projektu zapisuje `growth_campaigns`. Ręczny zapis koncepcji i „Zastosuj” zapisują jeden artifact `concept`. „Odrzuć” i sama propozycja AI nie zapisują koncepcji.
- Workspace projektu z etapami, materiałami, checklistą czasową i jednym głównym CTA.
- Checklista pokazuje 9 zadań operacyjnych z checkboxem. Pozostałe punkty osi czasu są informacją i nie mają checkboxa.
- Dziesięć materiałów ma edytowalny szkic i status. „Zapisz materiał” zapisuje artifact `material`. Samo otwarcie ekranu nie tworzy wiersza.
- Etap **Koncepcja**: edycja ręczna, status Do dopracowania / Gotowe, cofnięcie zatwierdzenia.
- **Poproś AI o zmianę**: przy wyłączonej fladze działa symulacja lokalna, a przy włączonej — ustrukturyzowana propozycja OpenAI.
- Propozycja AI nigdy nie nadpisuje bieżącej koncepcji aż do jawnego „Zastosuj”; można ją odrzucić.
- Osobna, domyślnie wyłączona flaga `GROWTH_AI_ENABLED`; prawdziwe AI jest dostępne tylko dla `super_admin`.
- Provider i model są konfigurowane centralnie i widoczne w UI; logika Growth OS korzysta z abstrakcji providera.
- Walidacja structured output, allowlista danych koncepcji, blokada e-maili/telefonów/sekretów, timeout, jeden retry, limity wywołań i prosty circuit breaker.
- Osobny log techniczny zawiera tylko metadane wywołania — bez promptu, odpowiedzi, koncepcji, PII i sekretów.
- Prosta historia wersji koncepcji w sesji (do 5 pozycji).
- Pozostałe pomysły i sugestie AI są nadal symulowane.
- Inbox prowadzi do miejsca w projekcie, nie jest głównym flow.

Do tej sekcji wpisujemy wyłącznie rzeczy faktycznie istniejące w aktualnym kodzie/prototypie. Wizja, planowane API, przyszłe modele i pomysły konsultacyjne należą do `vision.md` albo `roadmap.md`.

## 4. Aktualny flow UX

```text
Zaplanuj TIK
→ Pomysł i kierunek
→ Koncepcja (edycja / AI / zatwierdź / cofnij)
→ Materiały
→ Przygotowanie
→ LIVE
→ Follow-up
```

## 5. Najważniejsze decyzje

- Start UX to **Zaplanuj webinar TIK**, nie abstrakcyjny Topic.
- Projekt webinaru jest głównym workspace.
- Inbox jest pomocniczy.
- AI draftuje i sugeruje; człowiek zatwierdza.
- Propozycja AI dla koncepcji tworzy **wariant**, a nie nadpisuje od razu.
- OpenAI jest pierwszym providerem pilotażu, ale kod domenowy nie zależy bezpośrednio od jego API.
- Do AI trafia wyłącznie allowlista pól koncepcji; PII, dane klientów, zamówień i płatności są zabronione.
- Na górze projektu zawsze ma być widoczny najważniejszy następny krok.
- Nie budujemy dużego dashboardu ani pełnej platformy na zapas.

## 6. Czego świadomie jeszcze NIE robimy

- Prototyp nie zapisuje jeszcze kierunku do bazy.
- Brak zapisu wywołań AI do DB; propozycja pozostaje w sesji HTTP.
- Brak Anthropic, Gemini, OpenRouter, automatycznego routingu modeli i fallbacku między providerami.
- Brak YouTube API.
- Brak Sendy API.
- Brak Meta / Canva API.
- Brak publikacji, wysyłek, jobów i biznesowych side effectów.
- Kierunek, propozycja AI i prowadzący działają w sesji HTTP. Poza sesją zostaje kampania, zapisana koncepcja, decyzje przy koncepcji, 9 zadań operacyjnych, 10 materiałów (po jawnym zapisie) i techniczny log metadanych AI.
- Brak AI poza etapem Koncepcja.
- Prowadzący nie jest jeszcze zapisywany. Po odtworzeniu projektu pole prowadzącego jest puste.
- `Topic` i `Expert` nie należą do v0.1. Ekspert wskazuje opcjonalnie istniejący `Instructor` przez `primary_instructor_id`.

## 7. Otwarte pytania

- Czy historia wersji ma mieć „przywróć wersję”, czy tylko podgląd?

## 8. Następny krok

Trwały kierunek. Nie przenosić propozycji AI ani prowadzącego. Zadania operacyjne nie sterują głównym CTA.

## 9. Ostatnie zmiany

- Dodano opcjonalny OpenAI Responses API dla zadania `concept_revision`.
- Dodano abstrakcję providera i wersjonowany prompt/schema.
- Dodano structured output, walidację i bezpieczny zapis propozycji dopiero po pełnej walidacji.
- Dodano flagę, timeout, jeden retry, limity, circuit breaker i metadane kosztowe.
- Dodano AJAX: propozycja pojawia się bez przeładowania strony, spinner resetuje się, a dźwięk odtwarza się bezpośrednio po sukcesie.
- Zwiększono odporność parsera odpowiedzi OpenAI na różne formaty `output_text` i niepełne odpowiedzi.
- „Zapisz materiał” utrwala status i szkic dziesięciu materiałów jako artifact `material`. Etykieta publikacji zostaje w payloadzie.
- Nowa kampania dostaje 9 zadań operacyjnych ze stabilnym `key`. Checkbox przełącza `todo` i `done`. Istniejące kampanie uzupełnia komenda `growth:seed-operational-tasks`.
- Zastosuj, Odrzuć, „Koncepcja gotowa” i cofnięcie zatwierdzenia zapisują decyzję człowieka.
- Zalogowanie bez sesji odtwarza ostatnią kampanię właściciela i artifact `concept`.
- Utworzenie projektu zapisuje kampanię, a zapis koncepcji i „Zastosuj” zapisują artifact `concept`.
- Dodano testy integralności v0.1: unikalność klucza, puste relacje, cascade kampanii, `nullOnDelete` i blokada usunięcia właściciela.
- Dodano modele `App\Models\GrowthOS` i relacje dla 4 tabel v0.1.
- Dodano migrację `2026_09_29_191500_create_growth_os_v0_1_tables` dla 4 tabel v0.1.
- Zmieniono zaakceptowany zakres modelu danych v0.1 na 4 tabele: GrowthCampaign, Artifact, Task i Decision.
- Topic i Expert przeniesiono poza pierwszą migrację; ekspert v0.1 opiera się o istniejący `Instructor`.
- Doprecyzowano kontrakt artifactu: `key`, `type`, `schema_version`, `payload`, bez osobnej tabeli wersji w v0.1.
- Zachowano symulację lokalną przy wyłączonym prawdziwym AI.
- Dodano automatyczne testy bez prawdziwych i płatnych requestów.
- Wcześniej: edycja, cofnięcie zatwierdzenia i historia koncepcji w sesji.
- Uporządkowano dokumentację `docs/growth-os/`.
- Wcześniej: sesyjny flow webinaru TIK od zera, menu Dzisiaj/Projekty/Pomysły/Inbox.

## 10. Pliki referencyjne

- [vision.md](./vision.md)
- [architecture.md](./architecture.md)
- [decisions.md](./decisions.md)
- [roadmap.md](./roadmap.md)
- [CONSULTING.md](./CONSULTING.md)
- [consultations/2026-09-growth-os-0-3-chatgpt-package.md](./consultations/2026-09-growth-os-0-3-chatgpt-package.md)
- [consultations/2026-09-29-growth-os-operational-tasks-decision.md](./consultations/2026-09-29-growth-os-operational-tasks-decision.md)
- [consultations/2026-09-29-growth-os-material-artifacts-decision.md](./consultations/2026-09-29-growth-os-material-artifacts-decision.md)

## Question for consultant

Current question: none
