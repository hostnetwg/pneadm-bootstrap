# PNE Growth OS — Current State

Last updated: 2026-09-29 12:59  
Branch: main  
Commit: working tree / pending commit (base 04bc85e)  
Stage: 0.3 UX prototype  
Current blocker: flow edycji i cofania zatwierdzenia koncepcji

## 1. Cel projektu

PNE Growth OS / PNE Rozwój to moduł w `adm.pnedu.pl`, który ma prowadzić właściciela krok po kroku przez planowanie i przygotowanie działań rozwojowych PNE: webinarów, treści, kampanii eksperckich, relacji i przyszłej analityki.

## Current UX snapshot

- Entry point: **PNE Rozwój → Zaplanuj webinar TIK**.
- Main workspace: **Projekt webinaru**.
- Primary UX principle: zawsze widoczny **Następny krok**.
- Supporting views: **Dzisiaj / Projekty / Pomysły / Inbox**.

## 2. Aktualny etap

Etap 0.3 — prototyp UX przygotowania webinaru TIK od zera.

## 3. Co już działa

- Feature flag `PNE_GROWTH_OS_ENABLED` i dostęp tymczasowo tylko dla `super_admin`.
- Menu **PNE Rozwój**: Dzisiaj, Projekty, Pomysły, Inbox.
- Formularz **Zaplanuj webinar TIK**.
- Sesyjny projekt webinaru (`DemoTikWebinarProject`).
- Workspace projektu z etapami, materiałami, checklistą czasową i jednym głównym CTA.
- Pomysły i sugestie AI są symulowane.
- Inbox prowadzi do miejsca w projekcie, nie jest głównym flow.

Do tej sekcji wpisujemy wyłącznie rzeczy faktycznie istniejące w aktualnym kodzie/prototypie. Wizja, planowane API, przyszłe modele i pomysły konsultacyjne należą do `vision.md` albo `roadmap.md`.

## 4. Aktualny flow UX

```text
Zaplanuj TIK
→ Pomysł i kierunek
→ Koncepcja
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

## 7. Otwarte pytania

- Jak edytować i cofać zatwierdzenie etapów?
- Jak pokazać „Poproś AI o zmianę” bez oddawania kontroli AI?
- Czy etap ma mieć statusy Draft / Do dopracowania / Gotowe / Zatwierdzone?
- Czy propozycja AI tworzy wariant, wersję roboczą czy nadpisuje treść?
- Jak prosto pokazać historię wersji w prototypie sesyjnym?

## 8. Następny krok

Etap 0.3.1: dodać do etapu **Koncepcja** edycję ręczną, cofnięcie zatwierdzenia i symulowane „Poproś AI o zmianę” z propozycją do przyjęcia lub odrzucenia.

## 9. Ostatnie zmiany

- Utworzono sesyjny flow webinaru TIK od zera.
- Zmieniono menu na Dzisiaj / Projekty / Pomysły / Inbox.
- Zastąpiono stary prototyp „gotowy webinar + Inbox” workspace projektu.
- Dodano materiały z sesyjnymi statusami.
- Dodano checklistę czasową względem daty live.
- Dodano pakiet konsultacyjny dla zewnętrznego ChatGPT.
- Utworzono nową strukturę dokumentacji `docs/growth-os/`.

## 10. Pliki referencyjne

- [vision.md](./vision.md)
- [architecture.md](./architecture.md)
- [decisions.md](./decisions.md)
- [roadmap.md](./roadmap.md)
- [CONSULTING.md](./CONSULTING.md)
- [consultations/2026-09-growth-os-0-3-chatgpt-package.md](./consultations/2026-09-growth-os-0-3-chatgpt-package.md)

## Question for consultant

Current question: Jak zaprojektować edycję, cofnięcie zatwierdzenia i „Poproś AI o zmianę” dla etapu **Koncepcja** bez zamieniania workspace w ciężki CRM?

Context: Etap 0.3 pokazuje proces webinaru TIK od zera, ale etapy są jeszcze zbyt jednorazowe: użytkownik może głównie zatwierdzić kierunek i koncepcję. Następny etap 0.3.1 ma pokazać prawdziwą pracę nad treścią w sesji HTTP.

Expected output: rekomendowany UX dla etapu Koncepcja, statusy, akcje na ekranie, zasady wariantów AI i minimalny zakres prototypu 0.3.1.
