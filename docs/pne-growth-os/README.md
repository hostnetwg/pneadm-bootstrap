# PNE Growth OS

Data utworzenia/aktualizacji: 2026-09-29
Wersja dokumentacji: 0.3.0
Status: Etap 0.3 wdrożony lokalnie jako sesyjny prototyp przygotowania webinaru TIK od zera

## Cel

PNE Growth OS ma być prostą biznesowo warstwą wewnątrz `adm.pnedu.pl`, która pomaga PNE wybierać tematy, planować inicjatywy, angażować ekspertów, tworzyć i wykorzystywać treści, rozwijać relacje oraz mierzyć wyniki.

To nie jest osobna aplikacja ani przebudowa istniejącego systemu. Rozwój ma być addytywny i odwracalny. Po wyłączeniu Growth OS obecne `adm.pnedu.pl` i `pnedu.pl` muszą działać jak wcześniej.

## Status

### Etap 0.3 — webinar TIK od zera

Wdrożono lokalnie (bez bazy i side effectów):

- menu **PNE Rozwój**: Dzisiaj, Projekty, Pomysły, Inbox,
- startowy flow **Zaplanuj webinar TIK**,
- sesyjny projekt webinaru (`DemoTikWebinarProject`),
- workspace projektu: Pomysł i kierunek, Koncepcja, Materiały, Przygotowanie, LIVE, Follow-up,
- jedno główne CTA „Najważniejszy następny krok”,
- checklistę czasową względem daty live,
- materiały z sesyjnymi statusami,
- Inbox jako pomocniczy widok prowadzący do konkretnego miejsca w projekcie.

Nie utworzono tabel, modeli domenowych, jobów, integracji ani połączeń z AI. `pnedu.pl` nie uczestniczy w Etapie 0.3.

### Etap 0.2 — prototyp UX wokół gotowego webinaru TIK

Wykonano lokalnie jako etap przejściowy, ale koncepcja została zastąpiona w 0.3:

- gotowy demonstracyjny webinar TIK,
- inbox decyzji z podglądem,
- karta kampanii read-only.

### Etap 0.1 — szkielet modułu

Wdrożono lokalnie:

- fail-closed feature flag `PNE_GROWTH_OS_ENABLED`,
- trasę `GET /growth`,
- dostęp tymczasowo ograniczony do istniejącej roli `super_admin`,
- warunkową pozycję menu,
- demonstracyjny, read-only pulpit bez danych z bazy,
- testy dostępu i widoczności menu.

Nie utworzono tabel, modeli domenowych, jobów, integracji ani połączeń z AI. `pnedu.pl` nie uczestniczy w Etapie 0.1.

### Etap 0 — analiza i dokumentacja

Wykonano wyłącznie:

- analizę obu repozytoriów i ich relacji,
- mapę istniejących modeli i źródeł danych,
- koncepcję architektury, UX, AI, integracji i bezpieczeństwa,
- mapowanie przyszłych obiektów domenowych bez projektowania tabel,
- roadmapę z bramkami decyzyjnymi.

## Najważniejsze ustalenia

1. `adm.pnedu.pl` pozostaje centrum zarządzania i source of truth dla Growth OS.
2. Growth OS będzie wydzielonym modułem istniejącej aplikacji `pneadm`, a nie trzecim systemem.
3. `pnedu.pl` pozostaje publicznym portalem, checkoutem i panelem uczestnika.
4. Systemy zewnętrzne są wykonawcami; nie przejmują logiki ani kanonicznego stanu procesów.
5. Operacje wysokiego ryzyka wymagają sekwencji: podgląd → zatwierdzenie → wykonanie.
6. Nowy moduł będzie domyślnie wyłączony i ograniczony uprawnieniami.
7. Nie dokładamy synchronicznych zależności Growth OS do zamówień, płatności, faktur, certyfikatów ani provisioningu.
8. Dane osobowe nie mogą trafiać do AI ani `pne_analytics` bez osobnej, jawnej decyzji i podstawy prawnej.
9. Istniejący `Instructor` jest kotwicą dla eksperta; nie tworzymy drugiej niezależnej osoby prowadzącej.
10. Istniejący `MarketingCampaign` opisuje kampanię atrybucyjną/linkową, a nie pełną inicjatywę Growth OS. Pojęcia muszą pozostać rozdzielone.

## Mapa dokumentacji

- [01-VISION.md](./01-VISION.md) — wizja i cele biznesowe.
- [02-CURRENT-SYSTEM.md](./02-CURRENT-SYSTEM.md) — faktyczny stan obu aplikacji.
- [03-ARCHITECTURE.md](./03-ARCHITECTURE.md) — proponowana architektura i granice.
- [04-DATA-MODEL.md](./04-DATA-MODEL.md) — obiekty domenowe i reuse istniejących modeli.
- [05-UX.md](./05-UX.md) — język, ekrany i zasady human approval.
- [06-AI.md](./06-AI.md) — rola AI, dane, audyt i ograniczenia.
- [07-INTEGRATIONS.md](./07-INTEGRATIONS.md) — model adapterów i systemy zewnętrzne.
- [08-SECURITY.md](./08-SECURITY.md) — bezpieczeństwo, kompatybilność i rollback.
- [09-ROADMAP.md](./09-ROADMAP.md) — etapy rozwoju i bramki.
- [10-DECISIONS.md](./10-DECISIONS.md) — decyzje zaakceptowane koncepcyjnie i otwarte.
- [CHANGELOG.md](./CHANGELOG.md) — historia tej dokumentacji.
- [../consultations/2026-09-growth-os-0-3-chatgpt-package.md](../consultations/2026-09-growth-os-0-3-chatgpt-package.md) — pakiet read-only do konsultacji z zewnętrznym ChatGPT.

## Dokumenty systemowe powiązane

- [Przegląd architektury](../architecture/SYSTEM_OVERVIEW.md)
- [Kontekst projektu](../PROJECT_CONTEXT.md)
- [Następne kroki](../NEXT_STEPS.md)
- [Roadmapa AI](../ai/AI_ROADMAP.md)
- [RODO, analityka i AI](../security/RODO_ANALYTICS_AI.md)
- [Bezpieczeństwo danych](../DATA_SAFETY.md)
- [Testy](../TESTING.md)
- [Zasady komunikacji i dokumentacji](../AI_HUMAN_COMMUNICATION.md)
- [ADR-y](../decisions/)

## Zasada aktualizacji

Po każdej większej zmianie Growth OS należy sprawdzić:

- czy zmieniła się architektura lub granica z obecnym systemem,
- czy zmienił się model domenowy lub właściciel danych,
- czy doszła integracja, kolejka, cron albo sekret,
- czy zmienił się workflow, approval albo UX,
- czy doszła decyzja wymagająca wpisu w [10-DECISIONS.md](./10-DECISIONS.md) lub osobnego ADR,
- czy aktualizacji wymaga roadmapa, testy, runbook deployu i changelog.

Dokumentacja opisuje najpierw stan faktyczny, a osobno rekomendacje i decyzje otwarte. Nie wolno przedstawiać planu jako już wdrożonej funkcji.
