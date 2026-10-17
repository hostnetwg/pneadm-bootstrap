# PNE Operations — stan faktyczny

Ostatnia weryfikacja: 2026-10-17  
Repozytoria (lokalne `main`, bez weryfikacji produkcji):

- `pneadm`: zachowanie aplikacji jak w `ec69ddcc` (2026-10-17). Kanon spraw, łącznie z decyzjami po konsultacji, jest w `docs/operations-os/`.
- `pnedu` `51328d8` (2026-10-17; wskaźnik do kanonu). Zachowanie formularza jak w `7a3cc87c`.

## Bieżący etap

**Etap 0.** Analiza, kanon i konsultacja są opisane. W kodzie nie ma tabeli spraw, listy zapytań ani portalu trenera. Cztery pytania z konsultacji są rozstrzygnięte w [DECISIONS.md](./DECISIONS.md) (DEC-OPS-006, 007, 009, 010, 011). To decyzje, nie funkcje.

## Aktualny blocker

Zakres etapu 1 jest przyjęty. Kodu nie ma. Czeka na osobne polecenie Waldemara, żeby zacząć implementację.

## Rekomendowany następny krok

Jeden: przygotować implementację etapu 1 jako konkretną sprawę szkolenia zamkniętego (krótki status, opiekun, następne działanie, historia, e-mail jako powiadomienie). Bez pola `type`, portalu, briefu i silnika procesów. Kodu nie ma, dopóki Waldemar nie każe go pisać.

## Co działa

- Katalog ofert bez terminu `training_offers` w bazie `pneadm`, CRUD w ADM, publikacja na pnedu.pl. Kanon: [TRAINING_OFFERS.md](../TRAINING_OFFERS.md).
- Publiczne trasy pnedu: lista, strona oferty, formularz ogólny i formularz oferty. Oba kończą się synchronicznym e-mailem `TrainingOfferInquiryMail`. Zapytanie nie jest zapisywane w bazie.
- Z oferty w ADM można otworzyć formularz nowego `courses` z prefill i zapisać `courses.training_offer_id`. Zapis szkolenia jest ręczny (daty są wymagane). Kod jest na `main` w `pneadm`. Dokumenty [TRAINING_OFFERS.md](../TRAINING_OFFERS.md) i [NEXT_STEPS.md](../NEXT_STEPS.md) nadal opisują ten moduł jako wdrożony lokalnie, do deployu. W tym etapie nie sprawdzano, czy migracje są już na produkcji.
- Trenerzy są rekordami `instructors` w `pneadm`. Nie mają konta ani relacji do `users`.
- Konta pnedu.pl (`pnedu.users`) to konta uczestników: imię, nazwisko, e-mail, hasło. Nie ma ról.
- Konta ADM (`pneadm.users`) mają jedną rolę (`super_admin`, `admin`, `manager`, `user`) i uprawnienia.
- Ankiety, materiały (linki), nagrania (URL) i zaświadczenia wiszą na konkretnym `courses`.
- Windykacja ma sprawę, opiekuna, termin następnego działania i historię działań — wyłącznie dla nieopłaconej faktury zamówienia.
- Growth OS ma kampanię, zadania i decyzje wokół przygotowania webinaru / treści. To osobna domena.

## Czego nie ma

- Rekordu zapytania o szkolenie zamknięte.
- Karty sprawy, statusu sprawy, następnego działania i opiekuna sprawy.
- Historii ustaleń ze szkołą i trenerem (poza skrzynką e-mail i notatkami ludzi).
- Tabeli szkół / placówek. Placówka na formularzu to zwykły tekst.
- Powiązania `Instructor` ↔ użytkownik pnedu lub ADM.
- Portalu trenera i roli trenera.
- Szablonu procesu, instancji procesu i SOP w systemie.
- Zadań operacyjnych poza Growth OS i poza działaniami windykacji.
