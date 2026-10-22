# PNE Operations — architektura

Status: zasady domenowe po konsultacji 2026-10-17. Przyjęte decyzje: [DECISIONS.md](./DECISIONS.md). To nie jest schemat SQL.

## Granice

| Domena | Gdzie żyje | Rola w Operations |
|---|---|---|
| Oferta bez terminu | `pneadm.training_offers` | Katalog. Sprawa może wskazać ofertę. Oferta nie jest sprawą. |
| Sprawa szkolenia zamkniętego | jeszcze nie istnieje | Zapytanie i negocjacja z konkretną placówką. Nie jest ofertą, kursem ani sprawą windykacji. |
| Szkolenie | `pneadm.courses` | Potwierdzony termin, uczestnicy, ClickMeeting, zaświadczenia, zamówienia. |
| Trener | `pneadm.instructors` | Rekord biznesowy. Dziś bez konta. |
| Uczestnik | `pnedu.users` + `participants` | Konto na froncie. To nie jest pracownik PNE ani trener. |
| Operator PNE | `pneadm.users` + `roles` | Opiekun sprawy w ADM. |
| Windykacja | `debt_cases` | Wzorzec UX sprawy. Inna domena, inne tabele. |
| Growth OS | `growth_*` | Przygotowanie treści i webinaru marketingowego. Inna domena. |

`pnedu.pl` przyjmuje zapytanie od szkoły. `adm.pnedu.pl` jest miejscem pracy PNE i źródłem prawdy sprawy. Trener, gdy kiedyś odpowie, dostaje brief przygotowany przez PNE, nie kartę sprawy.

## Zapytanie nie jest kursem

To już jest prawdą w kodzie ofert:

- `training_offers` nie ma daty, uczestników ani zamówień ([TRAINING_OFFERS.md](../TRAINING_OFFERS.md)).
- `courses` wymaga `start_date` i `end_date` (`CoursesController::store`).
- Jedyna istniejąca ścieżka „oferta → szkolenie” to ręczny formularz `GET /training-offers/{id}/create-course`. Zapisuje opcjonalne `courses.training_offer_id`. Nie tworzy szkolenia sama.

Rekomendacja (wdrożone): sprawa ma opcjonalne `training_offer_id`. Po ludzkiej decyzji szkolenie wskazuje sprawę przez `courses.closed_training_case_id` (nullable, unique, 1:0..1). Brak automatycznej konwersji. Soft delete szkolenia czyści FK i dopisuje historię sprawy.

## Proces później, nie teraz

Na etapie 1–2 wystarczy sprawa szkolenia zamkniętego ze statusem i następnym działaniem. Szablon procesu wchodzi dopiero po realnych sprawach i tylko wtedy, gdy praktyka pokaże powtarzalny przebieg. Do tego czasu proces jest kierunkiem, nie projektem tabel. Logika kroków nie może siedzieć w kontrolerze formularza publicznego.

Status i następne działanie są niezależne (DEC-OPS-010). Status jest krótki, na przykład „oczekuje”. Następne działanie niesie treść: „jeżeli trener nie odpowie do 21.10, skontaktować się ponownie”. Status nie nazywa kroku negocjacji.

## Sprawa szkolenia zamkniętego

Na etapie 1 istnieje jeden konkretny obiekt: sprawa zapytania o szkolenie zamknięte (DEC-OPS-009). Nie ma pola `type` i nie ma wspólnej tabeli „spraw operacyjnych”. Drugi przypadek, gdy naprawdę się pojawi, dostanie własny obiekt albo — później — instancję procesu wskazującą istniejący obiekt. Tego schematu teraz nie rysujemy.

Sens obiektu, bez projektu kolumn:

- źródło: formularz oferty, formularz ogólny albo wpis ręczny w ADM,
- opcjonalne wskazanie oferty, a kurs dopiero po świadomej decyzji człowieka,
- trener wskazany przez PNE dopiero w późniejszym etapie, nie jako konto,
- krótki status, opiekun z `pneadm.users`, tekst i termin następnego działania,
- dane zapytania można poprawić na karcie; niezmienna kopia oryginału zostaje przy sprawie,
- zgoda: fakt i czas,
- osobna historia zdarzeń pisana przez operatora ADM.

Pola negocjacyjne etapu 2 są na `closed_training_cases` (DEC-OPS-013): instruktor, cena ryczałtowa szkoły, wynagrodzenie, potwierdzenia, start/end. Bez tabeli rund. Po powiązaniu ze szkoleniem źródłem realizacji trenera/terminu jest `courses`. Nie ma dziś tabeli szkół — placówka zostaje tekstem, dopóki powtarzalność klientów nie wymusi katalogu.

### Odrzucone na ten moment

- Rozszerzanie `training_offers` o zapytania. Oferta jest szablonem publikowanym wielu szkołom.
- Rozszerzanie `courses`. Szkolenie powstaje po uzgodnieniu.
- `growth_tasks` jako globalna lista zadań. Zadanie należy do `growth_campaigns` i jest checklistą webinaru.
- `debt_cases` jako wspólna sprawa. Sprawa windykacji jest 1:1 z `form_orders` i niesie fakturę, KSeF i iFirma.
- Jedna tabela spraw z polem `type` albo jedna polimorficzna tabela „na wszystko”.

Wzorzec do skopiowania, nie do współdzielenia tabel: `debt_cases.next_action_at` + `assigned_to_id` + `debt_case_actions`. Z Growth OS warto później podejrzeć checklistę względem daty (`GrowthOperationalTasks`), gdy dojdzie proces webinaru. Nie współdzielimy tabel ani kontrolerów.

## Jak to się później łączy z windykacją

Windykacja (`/accounting/collections`, tabele `debt_cases`, `debt_case_actions`, `debt_case_contacts`) zostaje źródłem prawdy nieopłaconej faktury. Szkolenie zamknięte dostanie własną sprawę handlową. To dwa różne obiekty biznesowe. Wspólny ma być dopiero późniejszy proces, który wskazuje na istniejący obiekt, a go nie zastępuje.

```text
debt_cases                         sprawa szkolenia zamkniętego
faktura, iFirma, opiekun           szkoła, trener, opiekun
        \                               /
         proces (później, etap 6)
         subject_type + subject_id
         kroki, termin, instrukcja, historia pracy
```

`debt_cases.form_order_id` jest unikalny, a rekord niesie numer faktury, KSeF, kwotę i status iFirma. Przeniesienie windykacji do nowej tabeli „na wszystko” rozbiłoby działający moduł księgowy. Nowa sprawa handlowa też nie dostaje kolumn faktury.

Spięcie, które zostawiamy na etap 6:

- ten sam opiekun: `pneadm.users`,
- instancja procesu ma `subject_type` i `subject_id` (sprawa szkolenia, sprawa windykacji, później webinar),
- status obiektu zostaje przy obiekcie (faktura opłacona, szkoła potwierdziła termin), a proces trzyma tylko robotę do zrobienia,
- stare sprawy windykacji działają dalej bez instancji procesu; proces dokleja się tam, gdzie PNE uzna, że checklista jest powtarzalna.

Na etapie 1 nie dodajemy `process_id` do windykacji ani do nowej sprawy. Jedna migracja w etapie 6 wystarczy, gdy pojawi się instancja. Dzięki temu moduły się nie zlewają, a później mają wspólny zaczep.

## Baza

Sprawy należą do **`pneadm`**.

- Tam są oferty, szkolenia, trenerzy i operatorzy.
- pnedu już zapisuje dane biznesowe w `pneadm` (`FormOrder::$connection = 'pneadm'`).
- `pnedu.users` to konta uczestników, nie miejsce na sprawy firmy.

Migracje spraw powstają w repozytorium `pneadm`.

## Jak aplikacje rozmawiają dziś

Nie ma szyny zdarzeń.

1. **Wspólny MySQL.** pnedu ma połączenie `pneadm` (`pnedu/config/database.php`). pneadm ma połączenie `pnedu` używane przez `PneduUser`.
2. **HTTP z tokenem, punktowo.** pnedu woła API pneadm (certyfikaty, zapisy na nagranie). pneadm woła pnedu `POST /api/internal/cache/...` (`PneduFrontendCacheInvalidationService`), gdy trzeba zrzucić cache.
3. **E-mail** jest powiadomieniem. Dla zapytań o rady jest dziś jedynym śladem.
4. **Pliki** nie są wspólnym dyskiem. Na SeoHost to dwa katalogi domen. Grafiki ADM serwuje pnedu przez URL `/media/pneadm/` (symlink albo proxy).

Dla etapu 1 rekomendowany zapis to ten sam wzorzec co zamówienie: pnedu zapisuje wiersz w `pneadm`, potem wysyła dotychczasowy e-mail. Najpierw baza, potem mail. Gdy mail padnie, sprawa zostaje. Nowe API HTTP nie jest potrzebne, dopóki druga aplikacja nie musi wykonać kodu, którego nie ma lokalnie (cache, certyfikat).

## Portal trenera a uprawnienia

Na pnedu nie ma ról. Middleware to `auth` i `verified`. Wpuszczenie trenera w te same trasy dałoby mu panel uczestnika, nie ADM — i tak jest za szeroko, jeśli trasy nie będą filtrowane po `instructor_id`.

Role ADM (`CheckPermission`, `users.role_id`) nie nadają się jako konto trenera. Trener nie dostaje logowania do `adm.pnedu.pl`.

`instructors.email` jest unikalny, ale nie jest normalizowany do małych liter i nie ma klucza do `pnedu.users`. E-mail konta uczestnika jest normalizowany (`NormalizesUserEmail`, `email_unique_slot`). Automatyczne łączenie po adresie pomyli wielkość liter, współdzieloną skrzynkę, zmianę adresu i trenera, który jest też uczestnikiem. Docelowo obowiązuje DEC-OPS-006: jawne powiązanie konta pnedu z rekordem `instructors`, włączane i odbierane przez PNE, niezależnie od roli uczestnika. E-mail tylko podpowiada kandydata. Flaga przy użytkowniku nie wystarcza.

Trener nie ogląda karty sprawy. Widzi brief opisany w DEC-OPS-007 oraz, w dalszym portalu, własne szkolenia (`courses.instructor_id`). Nie widzi bazy klientów, cudzych szkoleń, zamówień, faktur, windykacji ani notatek wewnętrznych PNE.

Ankieta natywna kopiuje `courses.instructor_id` do `surveys.instructor_id` (`NativeSurveyProvisioner`). Przy jednym prowadzącym na szkoleniu da się pokazać trenerowi wyniki jego kursów. Szkolenie ma jednego `instructor_id`, nie listę prowadzących.

## Materiały

`course_file_links` to URL (często Dysk Google), nie upload pliku. `course_videos` to URL YouTube/Vimeo. Nie ma wspólnej biblioteki plików. Portal trenera z [INSTRUCTOR_PORTAL.md](../INSTRUCTOR_PORTAL.md) powinien dokładać link do szkolenia trenera, z moderacją w ADM jeszcze do decyzji.

## Audyt, którego nie wystarczy

`activity_logs` + trait `LogsActivity` zapisuje CRUD modeli ADM, gdy ktoś jest zalogowany w ADM. Publiczny formularz pnedu nie utworzy takiego wpisu (`shouldLogActivity` wymaga `auth()->check()`). Log trzyma atrybuty rekordu i nie jest opowieścią „rozmawialiśmy ze szkołą”. Zostaje audytem technicznym. Historia sprawy jest osobną tabelą zdarzeń.

## SeoHost

Hosting współdzielony, bez Supervisora. Kolejka pneadm idzie cronem co minutę (`queue:work --stop-when-empty`). PHP prod: `/opt/alt/php82/usr/bin/php`. Limity LVE karzą za zbędne procesy PHP — stąd statyczne grafiki zamiast proxy (repo `pnedu`, plik `DEPLOY-PERF.md`).

Skutek dla spraw:

- zapis synchroniczny do MySQL, nie nowy długo żyjący worker,
- e-mail może zostać synchroniczny jak dziś albo wejść w istniejącą kolejkę database; nie stawiamy daemona,
- brak dodatkowego HTTP pnedu → pneadm przy każdym formularzu,
- załączniki, jeśli kiedyś powstaną, lądują tam, skąd czyta ADM (`pneadm` storage), nie na dysku frontu.

## Brief dla trenera

Gdy dojdzie etap odpowiedzi trenera, PNE nie udostępnia mu karty sprawy. Przygotowuje brief (DEC-OPS-007): temat, oczekiwania, odbiorcy, orientacyjna liczba uczestników, forma, czas, preferowany termin lub zakres, godziny, miejscowość albo rejon przy szkoleniu stacjonarnym, ewentualnie istniejąca oferta.

Poza briefem zostają dane kontaktowe szkoły, surowe `message` z formularza, notatki i korespondencja wewnętrzna, cena dla klienta, marża i inne sprawy tego klienta. Odpowiedź trenera wraca do historii sprawy po stronie ADM.

## Bezpieczeństwo danych

Formularz zbiera imię, e-mail, telefon, nazwę placówki i treść. To dane osobowe. Etap 1 zapisuje zgodę i trzyma te dane w ADM. Trener ich nie dostaje. W dokumentacji i przykładach nie używamy prawdziwych zgłoszeń.
