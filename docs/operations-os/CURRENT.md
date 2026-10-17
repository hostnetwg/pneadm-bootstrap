# PNE Operations — stan faktyczny

Ostatnia weryfikacja: 2026-10-17  
Repozytoria (lokalne `main`, bez weryfikacji produkcji):

- `pneadm`: etap 1 jest w lokalnym kodzie, jeszcze bez commita. Ostatni commit na GitHubie to `c4abbad` (sama dokumentacja).
- `pnedu`: zapis sprawy jest w lokalnym kodzie, jeszcze bez commita. Ostatni commit na GitHubie to `51328d8`.

## Bieżący etap

**Etap 1 w kodzie lokalnym.** Formularz pnedu zapisuje sprawę szkolenia zamkniętego, a ADM ma listę i kartę. Nie ma tego na produkcji. Nie ma portalu trenera ani silnika procesów.

## Aktualny blocker

Migracja `2026_10_17_160000_create_closed_training_cases_tables` jest na lokalnej bazie `pneadm`. Produkcja jej nie ma. Kod nie jest jeszcze w commicie.

## Rekomendowany następny krok

Jeden: Waldemar sprawdza lokalnie listę `Sprawy szkoleń zamkniętych` i formularz na pnedu.pl. Wdrożenie na produkcję dopiero po jego zgodzie na commit, push i deploy.

## Co działa

- Katalog ofert bez terminu `training_offers` w bazie `pneadm`, CRUD w ADM, publikacja na pnedu.pl. Kanon: [TRAINING_OFFERS.md](../TRAINING_OFFERS.md).
- Publiczne trasy pnedu: lista, strona oferty, formularz ogólny i formularz oferty. Oba zapisują `closed_training_cases` w `pneadm`, potem wysyłają `TrainingOfferInquiryMail`. Gdy mail padnie, sprawa zostaje.
- W ADM, menu `Szkolenia → Sprawy szkoleń zamkniętych`: lista, ręczny wpis, karta ze statusem (Nowa / W toku / Oczekuje / Zamknięta), opiekunem, następnym działaniem i historią. Temat szkolenia jest osobno od wiadomości klienta: formularz ogólny zapisuje wpisane pole, formularz oferty zapisuje tytuł oferty z chwili zapytania. Pusty temat pokazuje się jako „Nie podano”. Bez ceny, trenera, briefu i bez tworzenia `courses`.
- Z oferty w ADM można otworzyć formularz nowego `courses` z prefill i zapisać `courses.training_offer_id`. Zapis szkolenia jest ręczny (daty są wymagane). To osobna ścieżka, nie konwersja sprawy.
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
