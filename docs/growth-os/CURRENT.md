# PNE Growth OS — Current State

Last updated: 2026-09-29 16:50 CEST<br>
Branch: main<br>
Commit: working tree / pending commit (base fcdf8e0)<br>
Stage: 0.3.2 OpenAI concept-revision pilot<br>
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

Etap 0.3.2 — wąski pilotaż prawdziwego OpenAI dla zmiany/rozbudowy koncepcji webinaru TIK. Stan projektu nadal istnieje wyłącznie w sesji HTTP.

## 3. Co już działa

- Feature flag `PNE_GROWTH_OS_ENABLED` i dostęp tymczasowo tylko dla `super_admin`.
- Menu **PNE Rozwój**: Dzisiaj, Projekty, Pomysły, Inbox.
- Formularz **Zaplanuj webinar TIK**.
- Sesyjny projekt webinaru (`DemoTikWebinarProject`).
- Workspace projektu z etapami, materiałami, checklistą czasową i jednym głównym CTA.
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

- Brak migracji DB.
- Brak zapisu wywołań AI do DB; propozycja pozostaje w sesji HTTP.
- Brak Anthropic, Gemini, OpenRouter, automatycznego routingu modeli i fallbacku między providerami.
- Brak YouTube API.
- Brak Sendy API.
- Brak Meta / Canva API.
- Brak publikacji, wysyłek, jobów i biznesowych side effectów.
- Stan projektu i propozycji działa tylko w sesji HTTP; poza nią powstaje wyłącznie techniczny log metadanych AI.
- Brak AI poza etapem Koncepcja.

## 7. Otwarte pytania

- Czy po walidacji pilotażu dodać drugiego providera, czy najpierw model danych v0.1?
- Czy historia wersji ma mieć „przywróć wersję”, czy tylko podgląd?

## 8. Następny krok

Smoke test 0.3.2 zaliczony (AJAX podgląd, dźwięk, Zastosuj/Odrzuć). Następna decyzja: drugi provider AI albo model danych v0.1.

## 9. Ostatnie zmiany

- Dodano opcjonalny OpenAI Responses API dla zadania `concept_revision`.
- Dodano abstrakcję providera i wersjonowany prompt/schema.
- Dodano structured output, walidację i bezpieczny zapis propozycji dopiero po pełnej walidacji.
- Dodano flagę, timeout, jeden retry, limity, circuit breaker i metadane kosztowe.
- Dodano AJAX: propozycja pojawia się bez przeładowania strony, spinner resetuje się, a dźwięk odtwarza się bezpośrednio po sukcesie.
- Zwiększono odporność parsera odpowiedzi OpenAI na różne formaty `output_text` i niepełne odpowiedzi.
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

## Question for consultant

Current question: none
