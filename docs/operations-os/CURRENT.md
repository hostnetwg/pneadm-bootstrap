# PNE Operations — stan faktyczny

Ostatnia weryfikacja kodu: 2026-10-22  
Repozytoria:

- `pneadm`: etap 1 + etap 2 (pola negocjacyjne na sprawie) + powiązanie ze szkoleniem.
- `pnedu`: formularz zapisuje sprawę razem z oryginałem.

Ten plik nie sprawdza serwera produkcyjnego. Wdrożenie jest ręczne, po `git pull` na SeoHost.

## Bieżący etap

**Etap 1 i etap 2 (negocjacja w ADM) są w kodzie.** Na karcie sprawy: instruktor, cena ryczałtowa dla szkoły (PLN brutto), wynagrodzenie instruktora (PLN brutto), niezależne potwierdzenia, opcjonalny termin (dzień albo data+godzina). Po powiązaniu ze szkoleniem aktualny trener i termin pochodzą z `courses`; ustalenia na sprawie zostają historyczne. Nie ma portalu trenera ani silnika procesów.

## Aktualny blocker

Brak blokera w kodzie. Ostatnia migracja etapu 2: `2026_10_22_100000_add_negotiation_fields_to_closed_training_cases`.

## Rekomendowany następny krok

Codzienne użycie ustaleń na karcie sprawy. Etap 3+ (konto trenera / brief) dopiero po osobnej decyzji Waldemara.

## Co działa

- Katalog ofert bez terminu `training_offers` w bazie `pneadm`, CRUD w ADM, publikacja na pnedu.pl. Kanon: [TRAINING_OFFERS.md](../TRAINING_OFFERS.md).
- Publiczne trasy pnedu: lista, strona oferty, formularz ogólny i formularz oferty. Oba zapisują `closed_training_cases` w `pneadm`, potem wysyłają `TrainingOfferInquiryMail`. Gdy mail padnie, sprawa zostaje. Formularz wymaga rodzaju placówki. Nazwa szkoły zostaje opcjonalna. Przy wyborze „Inna” zapisuje się doprecyzowanie. Sprawa sprzed tej zmiany pokazuje „Nie podano”.
- W ADM, menu `Szkolenia → Sprawy szkoleń zamkniętych`: lista (kolumna **Symbol** `[SZ-{id}]`; **Kontakt**: imię, telefon, e-mail, na dole CENA + ikona (i) z modalem: nazwa szkoły, rodzaj, WWW, forma, oferta; **Ustalenia**: TERMIN / instruktor z wynagrodzeniem (brutto/netto) pod nazwiskiem, ✓ gdy potwierdzone; **Ostatni wpis**: status z liczbą wpisów historii w nawiasie, data, opiekun; sortowanie po Symbol/id i Ostatnim wpisie w preferencjach; wyszukiwanie po symbolu/id), ręczny wpis, karta ze statusem (Nowa / W toku / Oczekuje / Zaplanowana / Przeprowadzona / Anulowana / Zamknięta; Otwarte bez Anulowanej i Zamkniętej; Anulowana = niedogadane, Zamknięta = zakończone pozytywnie), opiekunem, następnym działaniem i historią. Notatka (sticky przy przewijaniu karty): opcjonalna forma kontaktu (tel. / e-mail / inny + dopisek) i „z kim” (trener / zamawiający) — też przy edycji notatki w historii; etykieta w jednej linii, kolor tła: trener (niebieski), zamawiający (żółty), bez oznaczenia (biały). Na karcie: ustalenia negocjacyjne (instruktor, cena ryczałtowa szkoły PLN brutto, wynagrodzenie instruktora PLN brutto/netto do wyboru, potwierdzenia, termin dzień lub z godziną). Po powiązaniu ze szkoleniem trener/termin realizacji = `courses`; pola sprawy historyczne i zablokowane (ceny nadal edytowalne). Bind z różnicą wymaga potwierdzenia. Przy rozjeździe trenera/terminu ze szkoleniem na karcie jest **Synchronizuj** (kopiuje trenera i termin ze szkolenia na sprawę; zmiana trenera cofa potwierdzenie wynagrodzenia). Odpięcie / soft delete szkolenia cofa potwierdzenie wynagrodzenia i prosi o ponowną weryfikację — bez cichego kopiowania z kursu. „Utwórz szkolenie ze sprawy” prefilluje trenera i daty ze sprawy. Krótki status bez kroków negocjacji. E-mail do szkoły z `[SZ-{id}]`. Zaświadczenia nadal tylko na `courses`.
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
