# PNE Operations — stan faktyczny

Ostatnia weryfikacja kodu: 2026-10-20  
Repozytoria:

- `pneadm`: etap 1 + powiązanie sprawy ze szkoleniem (`courses.closed_training_case_id`), WWW strony placówki, podgląd wiadomości na liście.
- `pnedu`: `30b48b1` — formularz zapisuje sprawę razem z oryginałem.

Ten plik nie sprawdza serwera produkcyjnego. Wdrożenie jest ręczne, po `git pull` na SeoHost.

## Bieżący etap

**Etap 1 jest w kodzie na GitHubie.** Formularz pnedu zapisuje sprawę szkolenia zamkniętego, a ADM ma listę i kartę. Powiązanie ze szkoleniem jest ręczne (utwórz ze sprawy / powiąż istniejące). Nie ma portalu trenera ani silnika procesów. Etap 2 nie jest rozpoczęty.

## Aktualny blocker

Brak blokera w kodzie. Ostatnia migracja to `2026_10_20_114100_add_closed_training_case_id_to_courses_table` w `pneadm`.

## Rekomendowany następny krok

Codzienne użycie karty sprawy. Nowa funkcja albo etap 2 dopiero po osobnej decyzji Waldemara.

## Co działa

- Katalog ofert bez terminu `training_offers` w bazie `pneadm`, CRUD w ADM, publikacja na pnedu.pl. Kanon: [TRAINING_OFFERS.md](../TRAINING_OFFERS.md).
- Publiczne trasy pnedu: lista, strona oferty, formularz ogólny i formularz oferty. Oba zapisują `closed_training_cases` w `pneadm`, potem wysyłają `TrainingOfferInquiryMail`. Gdy mail padnie, sprawa zostaje. Formularz wymaga rodzaju placówki. Nazwa szkoły zostaje opcjonalna. Przy wyborze „Inna” zapisuje się doprecyzowanie. Sprawa sprzed tej zmiany pokazuje „Nie podano”.
- W ADM, menu `Szkolenia → Sprawy szkoleń zamkniętych`: lista (kolumna **Symbol** `[SZ-{id}]` jak w temacie e-maila do szkoły; wyszukiwanie po `SZ-3` / `[SZ-3]` / samym id; podgląd wiadomości), ręczny wpis, karta ze statusem (Nowa / W toku / Oczekuje / Zamknięta), opiekunem, następnym działaniem i historią. Na karcie symbol widać przy tytule. Na karcie można poprawić osobę, e-mail, telefon, nazwę szkoły, WWW strony szkoły, rodzaj placówki, formę i powiązaną ofertę z katalogu. Zmiana oferty nie zmienia tematu. Zapis zostaje w historii. Osobno zostaje niezmienny oryginał z formularza albo z ręcznego wpisu. Sprawa poprawiana przed tą kopią pokazuje, że oryginał nie został zapisany. Z karty można wysłać e-mail do szkoły na aktualny adres sprawy (From `info@system.pnedu.pl`, Reply-To `kontakt@pnedu.pl`); wysłana wiadomość trafia do historii. Odpowiedzi szkoły nadal czyta się w skrzynce `kontakt@pnedu.pl` i w razie potrzeby dopisuje ręcznie. Wpisy historii można edytować i usuwać (zwykły tekst; e-mail — TinyMCE). Usunięcie wpisu o e-mailu nie cofa wysłanej wiadomości. Temat szkolenia jest osobno od wiadomości klienta: formularz ogólny zapisuje wpisane pole, formularz oferty zapisuje tytuł oferty z chwili zapytania. Pusty temat pokazuje się jako „Nie podano”. Bez ceny, trenera i briefu. Po uzgodnieniu: „Utwórz szkolenie ze sprawy” albo „Powiąż istniejące” (`courses.closed_training_case_id`, 1:0..1). Powiązanie nie zmienia statusu sprawy. Soft delete szkolenia czyści FK i dopisuje historię.
- Z oferty w ADM nadal można otworzyć formularz nowego `courses` z prefill i zapisać `courses.training_offer_id`. Z karty sprawy — osobna ścieżka create-course ze sprawy. Zapis szkolenia jest ręczny (daty są wymagane).
- Trenerzy są rekordami `instructors` w `pneadm`. Nie mają konta ani relacji do `users`.
- Konta pnedu.pl (`pnedu.users`) to konta uczestników: imię, nazwisko, e-mail, hasło. Nie ma ról.
- Konta ADM (`pneadm.users`) mają jedną rolę (`super_admin`, `admin`, `manager`, `user`) i uprawnienia.
- Ankiety, materiały (linki), nagrania (URL) i zaświadczenia wiszą na konkretnym `courses`.
- Windykacja ma sprawę, opiekuna, termin następnego działania i historię działań — wyłącznie dla nieopłaconej faktury zamówienia.
- Growth OS ma kampanię, zadania i decyzje wokół przygotowania webinaru / treści. To osobna domena.

## Czego nie ma

- Tabeli szkół / placówek. Placówka na formularzu to zwykły tekst.
- Powiązania `Instructor` ↔ użytkownik pnedu lub ADM.
- Portalu trenera i roli trenera.
- Szablonu procesu, instancji procesu i SOP w systemie.
- Zadań operacyjnych poza Growth OS i poza działaniami windykacji.
