# PNE Operations — stan faktyczny

Ostatnia weryfikacja kodu: 2026-10-30 (czyj ruch)  
Repozytoria:

- `pneadm`: etap 1 + etap 2 + powiązanie ze szkoleniem + e-mail z terminem / multi-adres / brief prowadzącego + `inquired_at` + `waiting_on` na liście.
- `pnedu`: formularz zapisuje sprawę razem z oryginałem.

Ten plik nie sprawdza serwera produkcyjnego. Wdrożenie jest ręczne, po `git pull` na SeoHost.

## Bieżący etap

**Etap 1 i etap 2 (negocjacja w ADM) są w kodzie.** Na karcie sprawy: instruktor, cena ryczałtowa dla szkoły (PLN brutto), wynagrodzenie instruktora (PLN brutto), niezależne potwierdzenia, opcjonalny termin (dzień albo data+godzina). Po powiązaniu ze szkoleniem aktualny trener i termin pochodzą z `courses`; ustalenia na sprawie zostają historyczne. Nie ma portalu trenera ani silnika procesów.

## Aktualny blocker

Brak blokera w kodzie. Ostatnia migracja spraw: `2026_10_30_100000_add_waiting_on_to_closed_training_cases_table`.

## Rekomendowany następny krok

Codzienne użycie ustaleń na karcie sprawy. Etap 3+ (konto trenera / brief) dopiero po osobnej decyzji Waldemara.

## Co działa

- Katalog ofert bez terminu `training_offers` w bazie `pneadm`, CRUD w ADM, publikacja na pnedu.pl. Kanon: [TRAINING_OFFERS.md](../TRAINING_OFFERS.md).
- Publiczne trasy pnedu: lista, strona oferty, formularz ogólny i formularz oferty. Oba zapisują `closed_training_cases` w `pneadm`, potem wysyłają `TrainingOfferInquiryMail`. Gdy mail padnie, sprawa zostaje. Formularz wymaga rodzaju placówki. Nazwa szkoły zostaje opcjonalna. Przy wyborze „Inna” zapisuje się doprecyzowanie. Sprawa sprzed tej zmiany pokazuje „Nie podano”.
- W ADM, menu `Szkolenia → Sprawy szkoleń zamkniętych`: lista (kolumna **Symbol** `[SZ-{id}]`; **Zapytanie** = `inquired_at` z formularza albo wymagane przy ręcznym wpisie, sortowalne; **Kontakt**: imię, telefon, e-mail, na dole CENA + ikona (i) z modalem: nazwa szkoły, rodzaj, rada pedagogiczna (liczba osób), WWW, forma, oferta; na karcie pole **Liczba osób w radzie pedagogicznej**; **Ustalenia**: TERMIN / instruktor z wynagrodzeniem (brutto/netto) pod nazwiskiem, ✓ gdy potwierdzone; **Ostatni wpis**: status z liczbą wpisów historii w nawiasie, data, opiekun; **Ruch**: badge PNE/Szkoła/Prowadzący + następne działanie i termin; sortowanie po Symbol/id i Ostatnim wpisie w preferencjach; wyszukiwanie po symbolu/id), ręczny wpis, karta ze statusem (Nowa / W toku / Oczekuje / Zaplanowana / Przeprowadzona / Anulowana / Zamknięta; Otwarte bez Anulowanej i Zamkniętej; Anulowana = niedogadane, Zamknięta = zakończone pozytywnie), opiekunem, polem **Czyj ruch**, następnym działaniem i historią. Notatka (sticky przy przewijaniu karty; **Przypnij** / **Odepnij** w nagłówku — wybór w localStorage): forma kontaktu (Telefon / E-mail / SMS / Messenger / Inny) i „z kim” obok siebie; dopisek tylko przy „Inny”; też przy edycji notatki w historii; etykieta w jednej linii. W historii ikony: e-mail, telefon, SMS, Messenger, Zmiana (ołówek); kolory tła: notatka–trener (niebieski), notatka–zamawiający (żółty), Zmiana (szary), e-mail (zielony), bez oznaczenia (biały); 2px odstępu między wpisami. Na karcie: ustalenia negocjacyjne (instruktor, cena ryczałtowa szkoły PLN brutto, wynagrodzenie instruktora PLN brutto/netto do wyboru, potwierdzenia, termin dzień lub z godziną). Po powiązaniu ze szkoleniem trener/termin realizacji = `courses`; pola sprawy historyczne i zablokowane (ceny nadal edytowalne). Bind z różnicą wymaga potwierdzenia. Przy rozjeździe trenera/terminu ze szkoleniem na karcie jest **Synchronizuj** (kopiuje trenera i termin ze szkolenia na sprawę; zmiana trenera cofa potwierdzenie wynagrodzenia). Odpięcie / soft delete szkolenia cofa potwierdzenie wynagrodzenia i prosi o ponowną weryfikację — bez cichego kopiowania z kursu. „Utwórz szkolenie ze sprawy” prefilluje trenera i daty ze sprawy. Krótki status bez kroków negocjacji. E-mail z `[SZ-{id}]`: główny + 2 dodatkowe adresy szkoły, checkboxy, test operatorski, **Przygotuj e-mail z terminem** (online/stacjonarny; status → Zaplanowana), propozycja dla szkoły, **mail / brief do prowadzącego** (bez ceny; opcjonalnie połącz kontakty; przy wysyłce do szkoły checkbox powiadomienia prowadzącego; opcjonalny checkbox ustawia „czyj ruch” na szkołę/prowadzącego). Zaświadczenia nadal tylko na `courses`.
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
