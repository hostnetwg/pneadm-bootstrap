# Panel trenera

Data: 2026-11-25

Trener loguje się na pnedu.pl. Jeśli ma już konto uczestnika na ten sam e-mail, zostaje przy nim. W menu strony, obok „Twoje zasoby”, ma zielony przycisk **Panel trenera (liczba)** o tym samym kształcie. Liczba to udostępnione zapytania. Przy przycisku jest czerwone kółko z liczbą nieodczytanych zgłoszeń, gdy jest ich więcej niż zero. Administrator panelu ADM (aktywna rola `admin` albo `super_admin`, ten sam e-mail na pnedu.pl) ma w menu konta listę **Panele trenerów**. Wejście otwiera panel wybranego instruktora bez zmiany hasła i bez przejmowania jego logowania. Na górze jest powrót do własnego konta. Zapis terminu w tym podglądzie jest prawdziwy: opiekun dostaje powiadomienie tak, jak po zapisie trenera.

Po wejściu widzi osobny panel pod `/trener`. W górnym pasku po lewej jest logo PNE, które wraca na stronę startową. Pod spodem, po lewej, jest menu jak u uczestnika: **Kalendarz** i **Zapytania**. Na formularzu terminu jest domyślnie zaznaczony checkbox „Test ustalania szkoleń dla rad pedagogicznych”. Zaznaczony zapisuje datę, notatkę i powiadomienie tak samo jak zwykły termin. Różnica jest w oznaczeniu: wpis w historii sprawy jest oznaczony jako Test, a pod datą w bloku Ustalenia jest czerwony dopisek DATA TESTOWA. To samo oznaczenie widzi trener. Odznaczenie zdejmuje dopisek. Wpis w historii da się usunąć. Kalendarz pokazuje szkolenia z tabeli `courses` przypisane do tego trenera, które jeszcze się nie skończyły i nie zostały usunięte.

## Włączenie

Na karcie instruktora w ADM są dwa pola:

- **Panel trenera na pnedu.pl** — zakłada albo podpina konto w bazie `pnedu`. Nowe konto dostaje mail z ustawieniem hasła. Istniejące konto dostaje mail z linkiem do panelu i zostaje przy swoim haśle. Wyłączenie chowa panel. Konto uczestnika zostaje.
- **SMS o nowym zapytaniu** — osobno. SMS idzie tylko na numer telefonu z karty instruktora i nie zawiera danych szkoły.

## Sprawa

Samo przypisanie instruktora nic nie pokazuje. Na sprawie zaznaczenie **Pokaż w panelu trenera** rozwija wiadomość do trenera. Sprawa wchodzi do panelu dopiero po **Wyślij**. Trener widzi tę wiadomość osobno, jako wiadomość od administratora. Przy pierwszym wysłaniu dostaje dzwonek, e-mail i SMS, jeśli SMS jest włączony. Na dole maila jest imię i nazwisko administratora, który wysłał zapytanie, oraz stopka NODN „Platforma Nowoczesnej Edukacji”.

**Wyłącz w panelu trenera** chowa sprawę z panelu. To samo robi odznaczenie checkboxa i **Zapisz ustalenia**. Termin, który trener już wpisał, zostaje na sprawie.

Trener widzi datę zapytania, temat, formę, placówkę, treść zapytania oraz imię, e-mail i telefon osoby pytającej. Jeśli administrator dopisał wiadomość, jest ona osobno, jako wiadomość od administratora. Telefon jest w grupach, np. +48 501 654 274. Nie widzi notatek z historii sprawy, cen, wynagrodzenia ani spraw innych trenerów.

Po rozmowie wpisuje datę i godzinę. Gdy termin jest już zapisany, widzi tylko datę i godzinę oraz notatkę, a formularz jest schowany. Przycisk **Zmień** otwiera go ponownie, uzupełniony dotychczasowymi danymi. Przy teście pod datą jest czerwony dopisek DATA TESTOWA. Pod datą jest pole Notatka. Notatka zapisuje się na sprawie i jest widoczna dla administratora przy terminie. Osobno trener może zaznaczyć, że nie chce albo nie może poprowadzić szkolenia. Po zaznaczeniu wpisuje krótkie wyjaśnienie. Administrator widzi je na sprawie i dostaje dzwonek oraz e-mail. Odznaczenie cofa rezygnację i też o tym powiadamia. Zmiana samej notatki nie wysyła ponownego powiadomienia. Termin zapisuje się od razu na sprawie (`start_date`, godzina znana). Gdy zmienia się data lub godzina, opiekun sprawy dostaje dzwonek i e-mail. W mailu jest też notatka, jeśli trener ją wpisał. Gdy opiekuna nie ma, powiadomienie idzie do aktywnych administratorów (`admin`, `super_admin`), a gdy takich ról nie ma — do aktywnych użytkowników panelu. Termin da się poprawić w ustaleniach sprawy. Gdy sprawa jest już powiązana ze szkoleniem, trener terminu nie zmienia.

## Technicznie

Migracje w pneadm, po kolei:

- `2026_11_25_160000_add_trainer_portal.php`
- `2026_11_25_173000_add_trainer_note_to_closed_training_cases.php`
- `2026_11_25_181500_add_trainer_decline_to_closed_training_cases.php`
- `2026_11_25_211500_add_trainer_panel_message_to_closed_training_cases.php`
- `2026_11_25_220600_add_trainer_schedule_is_test_to_closed_training_cases.php`

Deploy: [2026-11-trainer-portal-deploy.md](./deploy/2026-11-trainer-portal-deploy.md).

Powiadomienia: tabela `trainer_portal_notifications` w bazie `pneadm`. Dzwonek ADM jest w menu bocznym. Dzwonek trenera jest w pasku panelu `/trener`.

Test: `sail artisan test --filter=TrainerPortalTest` w pneadm.
