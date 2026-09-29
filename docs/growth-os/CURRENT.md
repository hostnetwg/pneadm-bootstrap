# PNE Growth OS — Current State

Last updated: 2026-09-29 14:12  
Branch: main  
Commit: implementation baseline 5d894ac (CURRENT metadata may be updated by later docs-only commits)  
Stage: 0.3.1 UX prototype  
Current blocker: none

## 1. Cel projektu

PNE Growth OS / PNE Rozwój to moduł w `adm.pnedu.pl`, który ma prowadzić właściciela krok po kroku przez planowanie i przygotowanie działań rozwojowych PNE: webinarów, treści, kampanii eksperckich, relacji i przyszłej analityki.

## Current UX snapshot

- Entry point: **PNE Rozwój → Zaplanuj webinar TIK**.
- Main workspace: **Projekt webinaru**.
- Primary UX principle: zawsze widoczny **Następny krok**.
- Supporting views: **Dzisiaj / Projekty / Pomysły / Inbox**.
- Concept stage: edycja ręczna, cofnięcie zatwierdzenia, propozycja AI jako wariant do przyjęcia/odrzucenia.

## 2. Aktualny etap

Etap 0.3.1 — praca nad koncepcją webinaru TIK w sesji HTTP.

## 3. Co już działa

- Feature flag `PNE_GROWTH_OS_ENABLED` i dostęp tymczasowo tylko dla `super_admin`.
- Menu **PNE Rozwój**: Dzisiaj, Projekty, Pomysły, Inbox.
- Formularz **Zaplanuj webinar TIK**.
- Sesyjny projekt webinaru (`DemoTikWebinarProject`).
- Workspace projektu z etapami, materiałami, checklistą czasową i jednym głównym CTA.
- Etap **Koncepcja**: edycja ręczna, status Do dopracowania / Gotowe, cofnięcie zatwierdzenia.
- Symulowane **Poproś AI o zmianę**: wariant nie nadpisuje bieżącej koncepcji aż do „Zastosuj”.
- Prosta historia wersji koncepcji w sesji (do 5 pozycji).
- Pomysły i sugestie AI są symulowane.
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
- Na górze projektu zawsze ma być widoczny najważniejszy następny krok.
- Nie budujemy dużego dashboardu ani pełnej platformy na zapas.

## 6. Czego świadomie jeszcze NIE robimy

- Brak migracji DB.
- Brak prawdziwego OpenAI API.
- Brak YouTube API.
- Brak Sendy API.
- Brak Meta / Canva API.
- Brak publikacji, wysyłek, jobów i side effectów.
- Wszystko działa tylko w sesji HTTP.
- Brak pełnej edycji AI dla wszystkich materiałów (tylko Koncepcja w 0.3.1).

## 7. Otwarte pytania

- Czy analogiczny flow edycji/AI przenieść od razu na „Pomysł i kierunek”?
- Czy historia wersji ma mieć „przywróć wersję”, czy tylko podgląd?
- Który materiał jest kolejnym kandydatem do takiego samego UX: mailing, opis YouTube, czy scenariusz?

## 8. Następny krok

Walidacja 0.3.1 z właścicielem na ekranie Koncepcji, potem decyzja: rozszerzyć ten sam wzorzec na kierunek/materiały albo przejść do modelu danych v0.1.

## 9. Ostatnie zmiany

- Dodano edycję koncepcji w sesji.
- Dodano cofnięcie zatwierdzenia koncepcji.
- Dodano symulowane „Poproś AI o zmianę” z Zastosuj / Odrzuć.
- Dodano krótką historię wersji koncepcji.
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
