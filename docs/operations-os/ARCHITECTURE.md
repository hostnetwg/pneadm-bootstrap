# PNE Operations — architektura

Status: rekomendacja techniczna etapu 0, oparta o kod z 2026-10-17. Decyzje biznesowe są w [DECISIONS.md](./DECISIONS.md) i mają status Proposed, dopóki Waldemar ich nie przyjmie.

## Granice

| Domena | Gdzie żyje | Rola w Operations |
|---|---|---|
| Oferta bez terminu | `pneadm.training_offers` | Katalog. Sprawa może wskazać ofertę. Oferta nie jest sprawą. |
| Sprawa / zapytanie | jeszcze nie istnieje | Potrzeba klienta, negocjacja, status, następny krok. |
| Szkolenie | `pneadm.courses` | Potwierdzony termin, uczestnicy, ClickMeeting, zaświadczenia, zamówienia. |
| Trener | `pneadm.instructors` | Rekord biznesowy. Dziś bez konta. |
| Uczestnik | `pnedu.users` + `participants` | Konto na froncie. To nie jest pracownik PNE ani trener. |
| Operator PNE | `pneadm.users` + `roles` | Opiekun sprawy w ADM. |
| Windykacja | `debt_cases` | Wzorzec UX sprawy. Inna domena, inne tabele. |
| Growth OS | `growth_*` | Przygotowanie treści i webinaru marketingowego. Inna domena. |

`pnedu.pl` przyjmuje zapytanie od szkoły i w przyszłości pokaże trenerowi tylko jego wycinek. `adm.pnedu.pl` jest miejscem pracy PNE i źródłem prawdy sprawy.

## Zapytanie nie jest kursem

To już jest prawdą w kodzie ofert:

- `training_offers` nie ma daty, uczestników ani zamówień ([TRAINING_OFFERS.md](../TRAINING_OFFERS.md)).
- `courses` wymaga `start_date` i `end_date` (`CoursesController::store`).
- Jedyna istniejąca ścieżka „oferta → szkolenie” to ręczny formularz `GET /training-offers/{id}/create-course`. Zapisuje opcjonalne `courses.training_offer_id`. Nie tworzy szkolenia sama.

Rekomendacja: sprawa dostaje opcjonalne `training_offer_id` i, dopiero po ludzkiej decyzji, opcjonalne `course_id`. Brak automatycznej konwersji.

## Proces później, nie teraz

Na etapie 1–2 wystarczy sprawa ze statusem i następnym działaniem. Szablon procesu (`ProcessTemplate` → instancja → kroki) wchodzi dopiero po realnych sprawach, jako osobne tabele. Logika kroków nie może siedzieć w kontrolerze formularza publicznego.

Status sprawy i następne działanie są niezależne. Przykład: status „czekamy na trenera”, a następne działanie „jeśli do 20.10 nie ma odpowiedzi, zadzwonić ponownie”.

## Rekomendowany model minimalny (jeszcze nie w kodzie)

Jedna sprawa operacyjna z polem typu, nie silnik BPM i nie osobna tabela wyłącznie na rady pedagogiczne.

**Sprawa**

- `type` — na start tylko `closed_training`. Kolejne typy dochodzą, gdy pojawi się drugi proces.
- `source` — formularz oferty, formularz ogólny, wpis ręczny w ADM.
- `training_offer_id` — null, gdy szkoła nie weszła z konkretnej oferty.
- `course_id` — null do momentu świadomego powiązania.
- `instructor_id` — null, dopóki PNE kogoś nie wskaże.
- `status` — krótki słownik obsługi, nie krok BPM.
- `assignee_user_id` — `pneadm.users`.
- `next_action` (tekst) i `next_action_at`.
- snapshot kontaktu z formularza: imię i nazwisko, e-mail, telefon, placówka (tekst), temat, forma, wiadomość.
- zgoda RODO: fakt i czas. Dziś checkbox jest wymagany, ale nigdzie nie jest zapisywany.

**Zdarzenie sprawy** (osobna tabela)

- kto (operator ADM, później trener), kiedy, rodzaj (notatka, zmiana statusu, odpowiedź trenera, odpowiedź szkoły), treść.

Pola ceny, terminów i wielokrotnych rund negocjacji należą do etapu 2, nie do pierwszej migracji. Nie ma dziś tabeli szkół — placówka zostaje tekstem, dopóki powtarzalność klientów nie wymusi katalogu.

### Odrzucone na ten moment

- Rozszerzanie `training_offers` o zapytania. Oferta jest szablonem publikowanym wielu szkołom.
- Rozszerzanie `courses`. Szkolenie powstaje po uzgodnieniu.
- `growth_tasks` jako globalna lista zadań. Zadanie należy do `growth_campaigns` i jest checklistą webinaru.
- `debt_cases` jako wspólna sprawa. Sprawa windykacji jest 1:1 z `form_orders` i niesie fakturę, KSeF i iFirma.
- Jedna polimorficzna tabela „na wszystko”.

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

`instructors.email` jest unikalny, ale nie jest normalizowany do małych liter i nie ma klucza do `pnedu.users`. E-mail konta uczestnika jest normalizowany (`NormalizesUserEmail`, `email_unique_slot`). Automatyczne łączenie po adresie pomyli wielkość liter, współdzieloną skrzynkę, zmianę adresu i trenera, który jest też uczestnikiem. Docelowo: jawne `instructors` → id konta pnedu, ustawiane przez PNE. E-mail może tylko podpowiedzieć dopasowanie.

Trener w przyszłości widzi wyłącznie własne `courses.instructor_id` i sprawy, do których PNE go dopuści. Nie widzi bazy klientów, cudzych szkoleń, zamówień, faktur, windykacji ani notatek wewnętrznych PNE.

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

## Bezpieczeństwo danych

Formularz zbiera imię, e-mail, telefon, nazwę placówki i treść. To dane osobowe. Etap 1 ma zapisać zgodę i nie pokazywać tych danych trenerowi. W dokumentacji i przykładach nie używamy prawdziwych zgłoszeń.
