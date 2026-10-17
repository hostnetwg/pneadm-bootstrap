# PNE Operations — stan faktyczny

Ostatnia weryfikacja: 2026-10-17  
Repozytoria (lokalne `main`, bez weryfikacji produkcji):

- `pneadm` `ec69ddcce2c273625b9d128038cf1cf294aac09f` (2026-10-17)
- `pnedu` `7a3cc87c7f1b6be849d11ae253dce6b52c45e8ff` (2026-10-07)

## Bieżący etap

**Etap 0.** Analiza kodu i kanon dokumentacji. Nie ma tabeli spraw, listy zapytań ani portalu trenera.

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

## Aktualny blocker

Decyzja o zakresie etapu 1 jest przyjęta (DEC-OPS-002, 2026-10-17). Kodu jeszcze nie ma. Blokuje start implementacji tylko tyle, że ta dokumentacja ma najpierw trafić na GitHub pod konsultację.

## Rekomendowany następny krok

Po publikacji dokumentacji: zapisać zapytanie z pnedu w bazie `pneadm` i pokazać je w ADM jako kartę sprawy (status, opiekun, następne działanie, notatka / historia). E-mail zostaje powiadomieniem. Szczegóły: [ROADMAP.md](./ROADMAP.md) etap 1. Windykacja zostaje osobnym obiektem (DEC-OPS-008).
