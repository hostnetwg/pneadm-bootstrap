# PNE Operations — decyzje

Statusy: **Proposed** (rekomendacja Cursora, czeka na Waldemara), **Accepted**, **Superseded**.

Przyjęte są DEC-OPS-002 i DEC-OPS-008. Nazwa przedsięwzięcia (DEC-OPS-001) jedzie razem z dokumentacją jako robocza. Pozostałe pozycje czekają. Kodu etapu 1 jeszcze nie ma: najpierw ta dokumentacja ma być na GitHubie dla konsultacji.

## DEC-OPS-001 — Nazwa przedsięwzięcia

- Status: Proposed
- Data: 2026-10-17
- Treść: dokumentacja nazywa się PNE Operations i leży w `pneadm/docs/operations-os/`. „Process OS” zostaje określeniem późniejszego mechanizmu (roadmapa etap 6), nie modułem do zbudowania teraz.
- Uzasadnienie: nazwa „silnik procesów” pcha w BPM zanim powstanie pierwsza sprawa. Istniejących modułów (`training_offers`, Growth OS, windykacja) nie przemianowujemy.

## DEC-OPS-002 — Etap 1 to zapis sprawy, nie proces

- Status: Accepted
- Data: 2026-10-17
- Treść: pierwszy wdrożeniowy etap to formularz pnedu → rekord w `pneadm` → lista i karta w ADM (status, opiekun, następne działanie, historia). E-mail zostaje powiadomieniem. Bez portalu trenera i bez silnika kroków.
- Uzasadnienie: dziś zapytanie znika w skrzynce. To jest luka opisana w [TRAINING_OFFERS.md](../TRAINING_OFFERS.md) jako kolejny etap.
- Przyjęcie: Waldemar zaakceptował ten zakres 2026-10-17. Implementacja startuje po publikacji tej dokumentacji.

## DEC-OPS-003 — Sprawa nie jest szkoleniem

- Status: Proposed
- Data: 2026-10-17
- Treść: zapytanie i negocjacja są osobnym rekordem. `courses` powstaje albo jest wiązane dopiero świadomą akcją człowieka, istniejącym formularzem tworzenia szkolenia.
- Uzasadnienie: `courses` wymaga terminu. Oferta i kurs są już rozdzielone w kodzie.

## DEC-OPS-004 — Osobna domena wobec Growth OS i windykacji

- Status: Proposed
- Data: 2026-10-17
- Treść: nie używamy `growth_tasks` ani `debt_cases` jako tabel spraw operacyjnych. Wolno skopiować układ „opiekun + termin następnego działania + historia”.
- Uzasadnienie: oba moduły mają własne klucze obce i własne znaczenie statusu. Łączenie utrudni uprawnienia i rozwój.

## DEC-OPS-008 — Windykacja zostaje osobnym obiektem, proces ją później wskazuje

- Status: Accepted
- Data: 2026-10-17
- Treść: `debt_cases` nie staje się tabelą spraw firmy i nie jest przenoszona do nowego modelu. Przyszła instancja procesu wskazuje istniejący obiekt (`subject_type` + `subject_id`), w tym sprawę windykacji i sprawę szkolenia zamkniętego. Do etapu 6 żaden z tych modułów nie dostaje kolumny procesu.
- Uzasadnienie: windykacja jest działającym modułem faktur (1:1 z zamówieniem, iFirma, KSeF). Szkolenie zamknięte jest negocjacją zanim powstanie szkolenie. Wspólna tabela zmieszałaby księgowość z handlem. Wspólny zaczep załatwia późniejszy proces, bez drugiej kopii windykacji.
- Przyjęcie: Waldemar potwierdził ten kierunek 2026-10-17.

## DEC-OPS-005 — Kolejność: negocjacja w ADM przed pełnym portalem

- Status: Proposed
- Data: 2026-10-17
- Treść: po karcie sprawy dochodzi negocjacja prowadzona przez PNE (etap 2). Konto trenera jest cienkie i powstaje tuż przed pokazaniem mu sprawy (etapy 3–4). Kalendarz, materiały i ankiety trenera są późniejsze.
- Uzasadnienie: ulga pamięci nie wymaga konta trenera. Pokazanie danych trenerowi wymaga najmniejszych uprawnień, których na pnedu jeszcze nie ma.

## DEC-OPS-006 — Tożsamość trenera jawna, nie po samym e-mailu

- Status: Proposed
- Data: 2026-10-17
- Treść: gdy powstanie konto trenera, powiązanie z `instructors` ustawia operator. Sam `instructors.email` może podpowiedzieć kandydata, nie zakłada konta i nie loguje.
- Uzasadnienie: e-mail trenera nie jest normalizowany i nie ma klucza do `pnedu.users`. Automatyczne spięcie pomyli konta uczestników i współdzielone skrzynki. Zgodne z zastrzeżeniem w [INSTRUCTOR_PORTAL.md](../INSTRUCTOR_PORTAL.md).

## DEC-OPS-007 — Zgoda i minimalizacja

- Status: Proposed
- Data: 2026-10-17
- Treść: zapis zapytania przechowuje treść zgody z formularza (czas). Trener nie dostaje pełnego rekordu kontaktowego szkoły, dopóki osobna decyzja nie określi, które pola są mu potrzebne do odpowiedzi.
- Uzasadnienie: formularz zbiera dane osobowe wyłącznie „w celu obsługi zapytania”. Portal trenera jest późniejszy i węższy.
