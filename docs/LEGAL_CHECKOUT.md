# Minimalny legalny checkout pnedu.pl

Stan: wdrożenie kodu 2026-09-08 (bez migracji produkcyjnej).

## Zakres

Płatne formularze `order-form`, `order-form-v2`, `deferred-order` i `pay-online` mają wspólny minimalny blok prawny. Nie wymagają checkboxa Regulaminu, RODO ani marketingu. Jedynym warunkowym oświadczeniem jest wcześniejsze rozpoczęcie usługi i dostarczenie elementów cyfrowych.

Oświadczenie jest wymagane tylko dla:

- osoby prywatnej,
- profilu `jdg` („JDG — zakup niezawodowy”),
- gdy termin live przypada nie później niż z końcem 14. dnia od zamówienia w strefie `Europe/Warsaw`.

Źródłem prawdy dla szkoleń live jest backend `pnedu/app/Services/LegalCheckoutService.php`.

Checkout kursów nagranych (`ProductLegalCheckoutService` + `WithdrawalWindowService`):

- oświadczenie jest wymagane dla osoby prywatnej / JDG, gdy **planowany start świadczenia wpada w 14 dni od zawarcia umowy** (Europe/Warsaw), także przy przedsprzedaży za 3 dni;
- start po upływie tego terminu nie wymaga zgody na wcześniejsze świadczenie;
- kwalifikacja kursu (`LEGAL_PRODUCT_QUALIFICATION`) jest `pending` — zakres oświadczenia zostaje `service_and_digital`;
- checkout kursów nagranych pokazuje osobne brzmienie `product_early_performance` (wersja `2026-09-13-course-v1`); szkolenia live zostają przy `early_performance` / `2026-09-08-v2`;
- dokładna treść zaakceptowanego oświadczenia jest zapisywana na `form_orders.early_performance_statement_text` i wraca w potwierdzeniu z zamówienia, nie z aktualnej etykiety formularza;
- osoba/JDG widzi krótki komunikat o 14 dniach z linkiem do `/odstapienie-od-umowy`; szkoła nie;
- gwarancja satysfakcji to **osobna obietnica** (liczba dni z oferty, od rozpoczęcia dostępu); zgłoszenie e-mailem lub telefonicznie; pełne zasady nadal nie są w Regulaminie.

Potwierdzenie kursu: `ProductOrderConfirmationMail` dołącza PDF specyfikacji zamówienia, właściwy Regulamin i wzór odstąpienia. `legal_confirmation_sent_at` oznacza przekazanie do mailera, nie potwierdzone doręczenie. Błędy: `legal_confirmation_failed_at` + komenda `legal:retry-product-confirmations`.

Projekt nowej wersji Regulaminu: `draft-pending-approval` (nieopublikowany). Lista decyzji: [LEGAL_PRODUCT_LAUNCH_DECISIONS.md](./LEGAL_PRODUCT_LAUNCH_DECISIONS.md).

Przy ogłoszonej obniżce ceny (szkolenia i kursy nagrane) front pokazuje „Najniższą cenę z 30 dni przed obniżką” z historii ofert (`price_offer_histories`). To obowiązek ustawy o informowaniu o cenach, nie część oświadczenia 14-dniowego.

Od 2026-09-21 ta sama linia Omnibus jest też na kartach **szkoleń zakończonych** (`/szkolenia-indywidualne` → sekcja archiwum), na stronie opisu kursu przy wielu wariantach z promocją, w nagłówku ceny formularza V2 oraz na `pay-online` — zawsze tylko gdy promocja jest aktywna.

## Dowody

Migracja `2026_09_08_210000_add_legal_checkout_evidence_to_orders.php` rozszerza `form_orders` i `online_payment_orders` o:

- `customer_profile`,
- `terms_version`, `terms_hash`,
- `early_performance_scope`,
- `early_performance_statement_version`,
- `early_performance_accepted_at`,
- `legal_confirmation_sent_at`.

Historyczne rekordy mają wartości `NULL`. Edycja lub ponowienie płatności nie nadpisują istniejącego dowodu Regulaminu/oświadczenia.

## Dokumenty i potwierdzenia

- Bieżąca wersja: `pnedu/config/legal.php`.
- Niezmienna treść: `pnedu/resources/views/legal/terms/versions/{wersja}.blade.php`.
- Publiczne HTML/PDF: `/regulamin/{wersja}` i `/regulamin/{wersja}.pdf`.
- Wzór odstąpienia: `/odstapienie-od-umowy`.
- Potwierdzenia zamówienia zawierają Regulamin właściwej wersji i wzór odstąpienia w PDF.

Nową wersję Regulaminu dodaje się jako nowy plik; nie wolno zmieniać pliku wersji przypisanej już do zamówień. Następnie trzeba dopisać definicję i ustawić `current_version` w `config/legal.php`.

## Uczestnik zgłoszony przez inną osobę

Publiczna informacja art. 14 znajduje się pod `/rodo-art-14`. Potwierdzenie zamówienia wysyłane do uczestnika oraz pierwszy e-mail dostępu z `FormOrderPneduProvisionService` podają nazwę zamawiającego i link do tej informacji, jeżeli e-mail uczestnika jest inny niż e-mail zamawiającego.

## Sendy i cookies

Listy kursowe są operacyjne i nie zapisują pola `gdpr` jako dowodu marketingowego. Dla zamówienia online — zarówno powiązanego z `form_orders`, jak i samodzielnego — synchronizacja następuje dopiero przy pierwszym przejściu statusu na `paid`; dla faktury odroczonej — po skutecznym zapisie.

GA/GTM uruchamiamy dopiero po „Akceptuję analityczne”. Własny lejek operacyjny (wejście na kurs i formularz, interakcje techniczne, „Aktywni teraz”) działa bez tej zgody, jako cookies niezbędne do obsługi zakupu. Zgoda Google nie włącza sygnałów reklamowych. Użytkownik może zmienić decyzję przez link „Ustawienia cookies” w stopce. Awaryjny powrót do blokady lejka: `FIRST_PARTY_OPERATIONAL_WITHOUT_ANALYTICS_CONSENT=false`.
