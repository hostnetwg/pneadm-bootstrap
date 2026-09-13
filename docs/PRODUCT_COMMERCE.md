# Sprzedaż produktów na pnedu.pl

Data utworzenia: 2026-09-11; aktualizacja: 2026-09-13
Status: etapy 1–4 wdrożone lokalnie — katalog, checkout, płatności i fulfillment

## Cel

Rozszerzenie obecnej sprzedaży szkoleń terminowych (`courses`) o inne typy produktów bez przepisywania stabilnego checkoutu:

1. kursy online nagrane (`online_courses`),
2. w przyszłości ebooki i usługi (np. konsultacje),
3. gotowość architektoniczna na produkty fizyczne.

Pierwszym wdrażanym typem jest `online_course`.

## Decyzje biznesowe

- Jedno zamówienie może kupować dostęp dla wielu uczestników.
- Cena zamówienia = cena wariantu za jednego uczestnika × liczba uczestników.
- Dostęp może być bezterminowy albo czasowy, np. 1 rok lub 2 lata.
- Płatność online: konto i dostęp po potwierdzeniu płatności przez bramkę.
- Faktura odroczona: wystawienie faktury i nadanie dostępu są niezależnymi akcjami operatora, tak jak przy `courses`.
- Okres czasowego dostępu liczy się od faktycznego nadania dostępu.
- Przedłużenie aktywnego dostępu liczy się od aktualnej daty wygaśnięcia; po wygaśnięciu — od nowego nadania.
- Nowy zakup nigdy nie skraca istniejącego dostępu; dostęp bezterminowy pozostaje bezterminowy.
- Konto pnedu.pl ma być tworzone automatycznie przy nadaniu dostępu.
- Kanały MVP: PayU, PayNow i faktura odroczona.
- Obecnie rozliczenie VAT: zwolnienie `ZW`, nie stawka 0%. Podstawa prawna pozostaje do potwierdzenia księgowego.
- Zaświadczenie po 100% lekcji jest planem późniejszym i nie blokuje pierwszego etapu sprzedaży.

## Strategia migracji

Obecne `form_orders.product_id` nadal oznacza `courses.id`. Nie zmieniamy tej semantyki w działającej sprzedaży.

Nowe typy produktów będą podłączane addytywnie:

```text
online_courses
      ↓ resource_id
products
      ↓
product_offers
      ↓
product_prices

form_orders
      ↓
order_items
      ↓
order_item_recipients
      ↓
order_fulfillments
      ↓
online_course_enrollments
```

Stare zamówienia szkoleń nie wymagają backfillu na początku. `courses` można później podłączać do katalogu przez adapter, etapami.

## Etap 1 — katalog i konfiguracja sprzedaży

### Tabele

Migracja:

`database/migrations/2026_09_11_214500_create_product_catalog_tables.php`

#### `products`

Kanoniczna tożsamość produktu:

- `type` — stabilny alias biznesowy, obecnie `online_course`,
- `resource_id` — dla kursu online: `online_courses.id`,
- `name`, `slug`, opcjonalne `sku`,
- `fulfillment_type` — obecnie `online_course_access`,
- `is_active`,
- `requires_shipping` — dla kursu online zawsze `false`,
- `meta_title`, `meta_description`.

Nie zapisujemy nazw klas PHP w kolumnie `type`.

#### `product_offers`

Konfiguracja kanału sprzedaży:

- kanał `pnedu`,
- aktywność i przyszła publikacja w katalogu,
- zakup dla wielu odbiorców,
- dozwolone metody: faktura odroczona, PayU, PayNow.

Aktywna sprzedaż wymaga co najmniej jednego sposobu płatności.

**Katalog** (`is_public`) i **zakup** (`offer.is_active`) są niezależne. Kurs może być widoczny na `/kursy` bez sprzedaży. Taka karta nie idzie do sitemapy i ma `noindex`.

#### `product_prices`

Wariant ceny za jednego uczestnika:

- cena podstawowa i opcjonalna promocja (data końca, omnibus jak przy szkoleniach, opcjonalny licznik `show_promotion_countdown`),
- waluta (MVP: PLN),
- sposób rozliczenia VAT (`exempt` = `ZW`, `standard` = stawka procentowa),
- reguła dostępu:
  - `unlimited`,
  - `duration_from_grant`,
  - `fixed_until`,
- długość w dniach, miesiącach lub latach,
- aktywność i kolejność.

Reguła dostępu jest częścią wariantu, dzięki czemu jeden kurs może mieć równocześnie np. dostęp na rok, dwa lata i bezterminowy.

### Panel administracyjny

Adres:

`/online-courses/{online_course}/sales`

Na liście `/online-courses` znajduje się kolumna i przycisk **Sprzedaż**. W edycji kursu jest osobna zakładka.

Panel pozwala:

- utworzyć produkt i domyślną ofertę pnedu,
- ustawić slug, SKU i SEO,
- osobno włączyć zakup (`Można kupić`) i widoczność w katalogu (`Pokazuj w katalogu /kursy`),
- wybrać sposoby płatności,
- dodawać, edytować, dezaktywować i usuwać warianty cenowe,
- skonfigurować promocję i okres dostępu.

`Pokazuj w katalogu` publikuje kartę na `/kursy`. Żeby dało się kupić, potrzebne są jeszcze `Można kupić`, co najmniej jeden sposób płatności i aktywny wariant ceny. Kurs tylko w katalogu (sprzedaż wyłączona) ma na pnedu.pl komunikat „Sprzedaż wyłączona”, `noindex` i nie trafia do sitemapy.

### Modele i serwisy

- `App\Models\Product`
- `App\Models\ProductOffer`
- `App\Models\ProductPrice`
- `App\Services\OnlineCourseSalesCatalogService`
- `App\Http\Controllers\OnlineCourseSalesController`
- `App\Http\Controllers\ProductPriceController`

`ProductPrice::accessExpiresAtFromGrant()` oblicza pierwszą datę wariantu w panelu. Regułę przedłużeń wykonuje `ProductAccessExpiryService` w aplikacji pnedu.

## Etap 2 — generyczne zamówienie

Migracja:

`database/migrations/2026_09_12_050500_create_product_order_tables.php`

Dodaje:

- `form_orders.order_kind`: `training` albo `product`,
- `order_items`: snapshot produktu, wariantu, ceny, VAT i reguły dostępu,
- `order_item_recipients`: odbiorcy konkretnej pozycji,
- `order_fulfillments`: idempotencja, wynik, enrollment i daty dostępu,
- nullable `online_payment_orders.course_id` dla zamówień niezwiązanych z `courses`.

`form_orders.product_id` pozostaje puste przy nowym produkcie i nadal oznacza wyłącznie `courses.id` w starym flow.

## Etap 3 — publiczna oferta i checkout

Publiczne trasy pnedu:

- `/kursy` — katalog; menu **Kursy** bezpośrednio po **Szkolenia**,
- `/kursy/{slug}` — oferta kursu i warianty,
- `/kursy/{slug}/zamowienie` — checkout wielu uczestników, z pobraniem danych nabywcy/odbiorcy z GUS po NIP,
- `/kursy/{slug}/zamowienie/{ident}` — edycja odroczonego zamówienia,
- `/zamowienia-kursow/{ident}` — podsumowanie z numerem ID, PDF i przyciskiem EDYTUJ.

Kurs pojawia się publicznie tylko wtedy, gdy kurs źródłowy, produkt i oferta są aktywne, oferta jest publiczna oraz istnieje aktywny wariant. Kursy nagrane nie są dodawane na stronę główną.

Checkout zapisuje snapshot i obsługuje fakturę odroczoną, PayU i PayNow. PayU/PayNow pobierają nazwę, ilość i cenę jednostkową z `order_items`, jeżeli `course_id` jest puste.

Bezpłatny zapis publiczny to osobna ścieżka (`/kursy/{slug}/zapis`), nie wariant 0 zł w checkoutcie. Flaga `product_prices.is_complimentary` pokazuje **„Zapisz się bezpłatnie”** i od razu tworzy konto oraz `online_course_enrollments` ze źródłem `free_signup`. Obok może stać płatny wariant. Checkout i PayU odrzucają wariant bezpłatny.

SEO:

- unikalne title/meta/canonical,
- katalog i publiczne oferty w dynamicznej sitemapie,
- podsumowanie i checkout z `noindex`.

## Etap 4 — fulfillment

Kanoniczna implementacja działa w `pnedu`:

- `ProductOrderFulfillmentService`,
- `ProductAccessExpiryService`,
- wewnętrzny endpoint `POST /api/internal/form-orders/{id}/fulfill-product`,
- płatność online `paid` uruchamia automatyczne konto + enrollment,
- panel pneadm wywołuje endpoint dla ręcznej akcji **Nadaj dostęp** (jeden uczestnik albo wszyscy),
- panel pneadm ma **Wycofaj dostęp** (jeden uczestnik albo wszyscy) bez ClickMeeting: usuwa enrollment, zeruje fulfillment i `pnedu_provisioned_at`, zostawia konto pnedu.pl,
- edycja uczestników w ADM synchronizuje `order_item_recipients`; zmiana e-mailu osoby z nadanym dostępem sama wycofuje stary dostęp,
- istniejące konto jest używane po znormalizowanym e-mailu,
- nowe konto otrzymuje wiadomość z ustawieniem hasła,
- retry nie tworzy drugiego fulfillmentu ani nie przedłuża ponownie dostępu,
- po nadaniu wszystkich dostępów `form_orders.pnedu_provisioned_at` jest ustawiane; przy częściowym albo błędnym fulfillmentcie pole jest czyszczone.

Status operacyjny listy `/form-orders` rozpoznaje `order_kind = product` po `order_fulfillments`, a nie po `courses`/`participants`. Zamówienie produktowe bez dostępu albo bez FV zostaje w kolejce „Do obsługi” (także w wariancie aktywnych). Badge **U** na `/courses` nadal liczy wyłącznie szkolenia live.

Na `/dashboard/kursy-online` (pnedu) uczestnik widzi zablokowaną kartę kursu, dopóki ADM nie nada dostępu albo — przy nieopłaconej płatności online — dokończy wpłatę albo sam zrezygnuje. Rezygnacja kasuje tylko jego kartę; zamówienie zostaje dla pozostałych uczestników. Karta jest tylko dla e-maila uczestnika, bez sztucznego `online_course_enrollments`.

Reguły czasu dostępu:

1. start: od razu po nadaniu **albo** `access_starts_at` na wariancie (przedsprzedaż),
2. nowy lub wygasły dostęp czasowy — od późniejszej z dat: nadanie albo zaplanowany start,
3. aktywny dostęp czasowy — od obecnego wygaśnięcia,
4. późniejsza istniejąca data nie jest skracana przez wariant `fixed_until`,
5. bezterminowy dostęp nigdy nie jest skracany.
6. zmiana daty startu w ADM dotyczy tylko nowych zamówień (snapshot na `order_items`).

## Etap 5 — przedsprzedaż i gwarancja satysfakcji

Migracja: `database/migrations/2026_09_12_104200_add_presale_and_satisfaction_guarantee.php`

- `product_offers.satisfaction_guarantee_days` — jedna obietnica na ofertę (domyślnie 30 dla kursów online, 0 = wyłączona, np. ebook),
- `product_prices.access_starts_at` + `access_note` — start i notatka per wariant,
- te same pola jako snapshot na `order_items` oraz start/notatka na `online_course_enrollments`.

Zegar 30 dni liczy się od dnia, w którym uczestnik **faktycznie** dostaje dostęp (zaplanowany start albo nadanie, gdy startu nie ma). Zwrot jest wyłącznie ręczny: e-mail / telefon + ADM (wycofanie dostępu, bramka, iFirma). Nie ma przycisku „Chcę zwrot” na koncie.

Oświadczenie o utracie 14-dniowego odstąpienia (`early_performance_accepted`) zostaje osobno: wymagane tylko przy profilu osoba/JDG **i** natychmiastowym starcie. Przy przyszłej dacie startu ustawowego 14 dni nadal obowiązuje, więc checkbox jest ukryty.

Na `/dashboard/kursy-online` enrollment ze startem w przyszłości pokazuje zablokowaną kartę „Dostęp od DD.MM.YYYY” bez wejścia do lekcji.

## Etap 6 — Omnibus (najniższa cena z 30 dni)

Migracja: `database/migrations/2026_09_13_163000_create_price_offer_histories_table.php`

Przy ogłoszonej obniżce (ustawa o informowaniu o cenach) pokazujemy **najniższą cenę z 30 dni przed obniżką**, nie „cenę regularną” z pola `price`.

- historia ofert: `price_offer_histories` (kursy nagrane `product_prices` i szkolenia `course_price_variants`),
- zapis automatyczny przy zapisie wariantu w ADM oraz co godzinę (`omnibus:sync-prices`) — start/koniec promocji czasowej bez ręcznego zapisu,
- w Polsce kolejne pogłębienie promocji to **nowa obniżka** i nowe okno 30 dni,
- korekta ręczna: wyłączenie wpisu (test / pomyłka) z powodem i autorem; nie ma wolnego wpisywania „ładniejszej” ceny Omnibus,
- gdy w oknie nic nie ma (pierwsza oferta): fallback do ceny regularnej `price`.

Panel: zakładka **Sprzedaż** kursu nagrane oraz edycja wariantu szkolenia. Sformułowanie na froncie: „Najniższa cena z 30 dni przed obniżką”.

Zalogowany uczestnik na `/kursy` i karcie oferty widzi swój dostęp (przejście do kursu / data końca / przedłużenie tymi samymi wariantami). Brak osobnej ceny przedłużenia. Po wygaśnięciu — zwykły zakup.

## Ograniczenie przed produkcją

Checkout kursów używa Regulaminu `2026-09-08`. Oświadczenie kursów nagranych ma osobne brzmienie (`2026-09-13-course-v1`, zakres nadal `service_and_digital`). Kwalifikacja produktu nie jest zatwierdzona. Projekt nowej wersji Regulaminu nie jest opublikowany. Lista otwartych decyzji: [LEGAL_PRODUCT_LAUNCH_DECISIONS.md](./LEGAL_PRODUCT_LAUNCH_DECISIONS.md). To nie blokuje lokalnych testów, ale blokuje produkcyjne GO.

### Później

- kupony: `coupons`, `coupon_redemptions`, `order_adjustments`,
- ebook: fulfillment pliku/linku,
- konsultacja: fulfillment ręczny lub rezerwacja,
- produkt fizyczny: adres dostawy, shipment i magazyn dopiero przy realnym zakresie.

## Ryzyka i zasady

1. Nie zmieniać znaczenia `form_orders.product_id` bez osobnego ADR i migracji.
2. Nie podłączać kursów online do `participants`; ich dostępem pozostaje `online_course_enrollments`.
3. Wariant ceny musi być kopiowany do zamówienia jako snapshot.
4. VAT musi być zapisany per pozycja; nie zakładać na stałe zwolnienia.
5. Publiczna sprzedaż wymaga przeglądu Regulaminu pod kątem treści cyfrowych i prawa odstąpienia.
6. Produkty fizyczne wymagają później własnego fulfillmentu; sama flaga `requires_shipping` nie oznacza wdrożonej wysyłki.

## Testy

```bash
sail artisan test --filter=PriceOmnibusServiceTest
sail artisan test --filter=OnlineCourseSalesCatalogTest
sail artisan test --filter=ProductPriceTest
sail artisan test --filter=ProductOrderAdminTest
sail artisan test --filter=FormOrderOperationalStatusTest
sail artisan test --filter=FormOrderOperationalStatusServiceSqlTest

# w projekcie pnedu
sail artisan test tests/Feature/ProductCheckoutTest.php
sail artisan test tests/Feature/DashboardPendingProductCoursesTest.php
sail artisan test tests/Unit/ProductAccessExpiryServiceTest.php
sail artisan test tests/Unit/ProductLegalCheckoutServiceTest.php
```

Pełna checklista: [TESTING.md](./TESTING.md).
