# PNE Operations — decyzje

Statusy: **Proposed** (rekomendacja Cursora, czeka na Waldemara), **Accepted**, **Superseded**.

Przyjęte: DEC-OPS-002, DEC-OPS-006, DEC-OPS-007, DEC-OPS-008, DEC-OPS-009, DEC-OPS-010, DEC-OPS-011, DEC-OPS-012, DEC-OPS-013. Nazwa przedsięwzięcia (DEC-OPS-001) zostaje robocza. DEC-OPS-003, DEC-OPS-004 i DEC-OPS-005 czekają. Etap 1 i 2 (pola negocjacyjne) są w kodzie.

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
- Przyjęcie: Waldemar zaakceptował ten zakres 2026-10-17. Po konsultacji doprecyzowany przez DEC-OPS-009 i DEC-OPS-010: obiekt to sprawa szkolenia zamkniętego, status jest krótki. Implementacja czeka na osobną zgodę i na publikację tej aktualizacji.

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
- Treść: po karcie sprawy dochodzi negocjacja prowadzona przez PNE (etap 2). Konto trenera powstaje tuż przed przekazaniem mu briefu (etapy 3–4, DEC-OPS-006 i DEC-OPS-007). Kalendarz, materiały i ankiety trenera są późniejsze.
- Uzasadnienie: ulga pamięci nie wymaga konta trenera. Pokazanie danych trenerowi wymaga najmniejszych uprawnień, których na pnedu jeszcze nie ma. Kolejność etapów nie jest jeszcze osobno przyjęta.

## DEC-OPS-006 — Jawne powiązanie konta trenera, nie flaga i nie sam e-mail

- Status: Accepted
- Data: 2026-10-17
- Treść: przyszłe konto trenera na pnedu.pl nie opiera się na fladze w rodzaju `is_instructor`. Ta sama osoba może być użytkownikiem portalu, uczestnikiem i trenerem. Powiązanie konta pnedu z konkretnym rekordem `instructors` ustawia PNE. Da się je włączyć i odebrać niezależnie od zwykłego konta uczestnika. E-mail może tylko podpowiedzieć kandydata. Nie jest źródłem autoryzacji ani automatycznego powiązania.
- Uzasadnienie: `instructors.email` nie jest normalizowany i nie ma klucza do `pnedu.users`. Automatyczne spięcie pomyli konta uczestników i współdzielone skrzynki. Konsultacja 2026-10-17, przyjęte przez Waldemara. Modelu bazy i RBAC jeszcze nie projektujemy.
- Przyjęcie: Waldemar, 2026-10-17.

## DEC-OPS-007 — Zgoda, minimalizacja i brief dla trenera

- Status: Accepted
- Data: 2026-10-17
- Treść: zapis zapytania przechowuje fakt i czas zgody z formularza. Trener nie dostaje pełnej karty sprawy z ADM. Dostaje brief przygotowany przez PNE, tylko z tym, czego potrzebuje do odpowiedzi: temat, oczekiwania, grupa odbiorców, orientacyjna liczba uczestników, online albo stacjonarnie, oczekiwany czas, preferowany termin lub zakres, preferowane godziny, miejscowość albo rejon przy szkoleniu stacjonarnym, ewentualnie wskazanie istniejącej oferty PNE. Domyślnie nie widzi nazwiska, telefonu, e-maila, dokładnego adresu, pełnej nazwy placówki, notatek wewnętrznych, historii korespondencji PNE ze szkołą, ceny dla klienta, marży, innych danych finansowych i innych spraw tego klienta. Surowe pole `message` z formularza publicznego nie jest mu przekazywane automatycznie. Przepływ: zapytanie klienta, potem PNE porządkuje potrzeby, potem brief, potem odpowiedź trenera.
- Uzasadnienie: formularz zbiera dane osobowe w celu obsługi zapytania przez PNE. Wiadomość może zawierać dane, których trener nie potrzebuje. Konsultacja 2026-10-17, przyjęte przez Waldemara.
- Przyjęcie: Waldemar, 2026-10-17.

## DEC-OPS-009 — Etap 1 bez generycznego pola typu

- Status: Accepted
- Data: 2026-10-17
- Treść: pierwszy obiekt nie jest ogólną sprawą operacyjną z polem `type`. Jest konkretną sprawą zapytania o szkolenie zamknięte. Webinar, windykacja i to zapytanie nie są wariantami jednej tabeli. Wspólny mechanizm, gdy powstanie, łączy je przez `subject_type` + `subject_id` (DEC-OPS-008).
- Uzasadnienie: dziś jest jeden rzeczywisty typ takiej sprawy. Pole `type` niczego nie upraszcza, a abstrakcja przed drugim prawdziwym przypadkiem jest przedwczesna. Konsultacja 2026-10-17, przyjęte przez Waldemara.
- Przyjęcie: Waldemar, 2026-10-17.

## DEC-OPS-010 — Krótki status, osobno od następnego działania

- Status: Accepted
- Data: 2026-10-17
- Treść: status etapu 1 nie odtwarza kroków negocjacji. Słownik jest krótki i ogólny, w rodzaju: nowa, w toku, oczekuje, zamknięta. Nazwy na ekranie można doprecyzować przy implementacji. Status nie jest krokiem procesu. Bieżąca sytuacja wynika z następnego działania, jego terminu i historii. Przykład: status „oczekuje”, a następne działanie „jeżeli trener nie odpowie do 21.10, skontaktować się ponownie”. Nie dodajemy teraz statusów typu „czekamy na trenera” albo „szkoła analizuje”. Osobne pole „na kogo czekamy” dochodzi tylko wtedy, gdy praktyka pokaże taką potrzebę.
- Uzasadnienie: kilkanaście statusów zbudowałoby ukryty workflow. Konsultacja 2026-10-17, przyjęte przez Waldemara.
- Przyjęcie: Waldemar, 2026-10-17.

## DEC-OPS-011 — Najpierw praktyka, potem abstrakcja

- Status: Accepted
- Data: 2026-10-17
- Treść: PNE Operations ma być proste. Kolejność: realny problem, najmniejsze rozwiązanie, codzienne użycie, obserwacja, dopiero potem kolejna funkcja albo abstrakcja. Nie budujemy BPM na zapas i nie tworzymy generycznych modeli tylko dlatego, że kiedyś mogą się przydać. Rozbudowa wynika z praktyki Waldemara, Łukasza i trenerów.
- Uzasadnienie: Waldemar potwierdził tę zasadę po konsultacji 2026-10-17.
- Przyjęcie: Waldemar, 2026-10-17.

## DEC-OPS-012 — E-mail do szkoły z karty sprawy

- Status: Accepted (uzupełnione 2026-10-29)
- Data: 2026-10-18
- Treść: z karty sprawy szkolenia zamkniętego można wysłać e-mail na adresy kontaktowe sprawy: **główny** (`contact_email`) oraz do **2 dodatkowych** (`contact_email_alt1`, `contact_email_alt2`). Przy wysyłce operator zaznacza checkboxami odbiorców. From: `info@system.pnedu.pl`, Reply-To: `kontakt@pnedu.pl`, nazwa: Platforma Nowoczesnej Edukacji. Temat zawiera identyfikator `[SZ-{id}]`. Po udanej wysyłce pełna treść ląduje w historii. Błąd wysyłki nie tworzy wpisu „wysłano” i nie zmienia statusu ani następnego działania. ADM nie odbiera poczty. Odpowiedzi szkoły czyta się w skrzynce `kontakt@pnedu.pl` i w razie potrzeby dopisuje ręcznie. Bez BCC, załączników i wysyłki do trenera. **Szablon „info o terminie”**: prefill + edycja; online z linkiem ClickMeeting (`meeting_link`, fallback `live/{token}`); stacjonarny bez linku; po wysyłce do szkoły status → **Zaplanowana**. **Wysyłka testowa** na stałe adresy operatorskie (bez zmiany statusu).
- Uzasadnienie: korespondencja ma zostać przy sprawie, bez budowania skrzynki w ADM. From zostaje jak w pozostałych mailach systemowych panelu, żeby nie psuć dostarczalności. Kilka adresów = sekretariat + dyrektor bez obchodzenia walidacji `type=email`.
- Przyjęcie: Waldemar, 2026-10-18 (From `info@system.pnedu.pl`). Uzupełnienie 2026-10-29: dodatkowe e-maile, szablon terminu, test, status Zaplanowana.

## DEC-OPS-013 — Pola negocjacyjne na sprawie (etap 2)

- Status: Accepted
- Data: 2026-10-22
- Treść: na `closed_training_cases` są opcjonalne: `instructor_id`, `client_price` + `client_price_confirmed_at`, `instructor_fee` + `instructor_fee_basis` (`gross`/`net`, domyślnie brutto) + `instructor_fee_confirmed_at`, `start_date` / `end_date`, `schedule_time_known`. Cena klienta = ryczałt za całe szkolenie zamknięte, PLN brutto. Wynagrodzenie = kwota PLN z oznaczeniem brutto albo netto (bez %/godzinowych). Jedna bieżąca kwota + historia `TYPE_UPDATED`; bez tabeli rund. Termin może być samym dniem (`schedule_time_known=false`). Po powiązaniu z `courses` aktualny trener i termin pochodzą ze szkolenia; pola sprawy historyczne i zablokowane po stronie serwera; ceny nadal na sprawie. Zmiana kwoty, podstawy brutto/netto lub trenera cofa odpowiednie potwierdzenie. Odpięcie / soft delete szkolenia nie przywraca potwierdzeń — czyści potwierdzenie wynagrodzenia i prosi o weryfikację. Wpisanie terminu nie tworzy szkolenia. Prefill „Utwórz ze sprawy” bierze trenera i daty ze sprawy.
- Uzasadnienie: ulga pamięci przy negocjacji przed powstaniem `courses`, bez BPM i bez portalu trenera.
- Przyjęcie: Waldemar, 2026-10-22 (odpowiedzi: ryczałt, brutto, kwota PLN, dzień bez godziny OK, odpięcie bez cichego kopiowania). Dopisek 2026-10-22: wynagrodzenie może być netto (wybór na karcie).
