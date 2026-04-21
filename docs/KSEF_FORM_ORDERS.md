# KSeF / iFirma — Podmiot3 w `form_orders` (ETAP 3)

Dokument opisuje wdrożenie obsługi dodatkowego podmiotu na fakturze
(Podmiot3 / `OdbiorcaNaFakturze`) dla zamówień z tabeli `form_orders` w
projekcie `pneadm-bootstrap`.

- **Zakres ETAP 2:** metadane sterujące nad istniejącymi kolumnami `recipient_*`
  + obsługa trzech ról zgodnych z publiczną dokumentacją API iFirma.
- **Zakres ETAP 3:** integracja metadanych Podmiotu3 z czterema przyciskami
  wystawiania dokumentu na stronie szczegółów zamówienia oraz wspólny
  builder `Kontrahent` dla wszystkich ścieżek.
- **Obsługiwane role:** `odbiorca`, `jst_recipient` (KSeF rola 8),
  `vat_group_member` (KSeF rola 9).
- **Nie ma** wariantu `custom`.
- **Nie dublujemy** danych `recipient_*`.
- **Bez zmian** w publicznym formularzu `pnedu.pl`.
- **Bez zmian** w strukturze adresowej.
- Pełna zgodność wsteczna zapewniona przez deterministyczny backfill w migracji ETAP 1.

## Spis treści

1. [Nota nazewnicza: dlaczego kolumny nazywają się `recipient_*`](#nota-nazewnicza-dlaczego-kolumny-nazywają-się-recipient_)
2. [Kolumny w `form_orders`](#kolumny-w-form_orders)
3. [Kanoniczna reprezentacja roli](#kanoniczna-reprezentacja-roli)
4. [Backfill dla rekordów historycznych](#backfill-dla-rekordów-historycznych)
5. [Reguła fail-fast](#reguła-fail-fast)
6. [Mapowanie na payload iFirma](#mapowanie-na-payload-ifirma)
7. [Heurystyka z `invoice_notes`](#heurystyka-z-invoice_notes)
8. [UI – formularz i widok szczegółów](#ui--formularz-i-widok-szczegółów)
9. [Role odrzucone w ETAP 2 (i dlaczego)](#role-odrzucone-w-etap-2-i-dlaczego)
10. [ETAP 3 — integracja z przyciskami iFirma na stronie zamówienia](#etap-3--integracja-z-przyciskami-ifirma-na-stronie-zamówienia)
11. [Przewidywany zakres ETAPU 4](#przewidywany-zakres-etapu-4)

## Nota nazewnicza: dlaczego kolumny nazywają się `recipient_*`

Kolumny `recipient_name`, `recipient_address`, `recipient_postal_code`,
`recipient_city` i `recipient_nip` zostały utworzone w pierwotnej migracji
`2025_10_17_205515_create_form_orders_table.php` jako odpowiedniki polskich
`odb_*` (odbiorca). W momencie tworzenia tabeli jedynym scenariuszem był
klasyczny „odbiorca na fakturze”, więc nazwa była semantycznie spójna.

Od ETAP 2 te same kolumny przechowują dane **Podmiotu3** niezależnie od jego
roli (odbiorca / JST / członek grupy VAT). Nazwa `recipient_*` jest więc
historyczna — nie przemianowujemy kolumn, żeby nie rozjechać kontraktu
z publicznym formularzem `pnedu.pl`, gdzie te same nazwy pojawiają się
w modelu, kontrolerze, widokach, mailach i PDF.

Świadomie wybrany **wariant C**:

- DB: nazwy `recipient_*` pozostają bez zmian.
- Komentarze MySQL na tych kolumnach (zaktualizowane migracją
  `2026_04_20_000002_document_podmiot3_and_extend_ksef_role_comments_on_form_orders.php`)
  wyjaśniają, że kolumny trzymają dane Podmiotu3.
- UI konsekwentnie mówi „Podmiot3” w bloku KSeF, a historyczna karta
  „ODBIORCA” w głównej sekcji formularza zachowuje swoją nazwę.
- Dokumentacja (ten plik) odnotowuje różnicę nazwy i semantyki.

Ewentualny pełny rename na `podmiot3_*` (lub `party3_*`) jest rozważany jako
osobny, skoordynowany projekt — musiałby objąć zarówno `pneadm-bootstrap`, jak
i `pnedu.pl` (publiczne formularze, emaile, PDF, API) oraz synchroniczny
deployment obu aplikacji.

## Kolumny w `form_orders`

Metadane KSeF dodane migracją ETAP 1
`2026_04_20_000001_add_ksef_additional_entity_metadata_to_form_orders_table.php`.
Komentarze MySQL zaktualizowane migracją ETAP 2
`2026_04_20_000002_document_podmiot3_and_extend_ksef_role_comments_on_form_orders.php`.

| Kolumna                              | Typ            | Null | Default  | Rola |
|--------------------------------------|----------------|------|----------|------|
| `ksef_entity_source`                 | `varchar(20)`  | nie  | `'none'` | Źródło danych Podmiotu3: `none` lub `recipient`. |
| `ksef_additional_entity_role`        | `varchar(30)`  | tak  | `NULL`   | Kanoniczny kod roli Podmiotu3 (lowercase). |
| `ksef_additional_entity_id_type`     | `varchar(20)`  | tak  | `NULL`   | Typ identyfikatora (`NIP`, `PESEL`, `IDWew`, `BrakID`). |
| `ksef_additional_entity_identifier`  | `varchar(50)`  | tak  | `NULL`   | Wartość identyfikatora (zapis administracyjny). |
| `ksef_admin_note`                    | `text`         | tak  | `NULL`   | Wewnętrzna notatka administratora (nie wysyłana). |

Istniejące kolumny `recipient_*` (patrz nota nazewnicza):

| Kolumna                 | Typ            | Null | Default | Uwagi |
|-------------------------|----------------|------|---------|-------|
| `recipient_name`        | `varchar(500)` | tak  | `NULL`  | Nazwa Podmiotu3. |
| `recipient_address`     | `varchar(500)` | tak  | `NULL`  | Ulica i numer. |
| `recipient_postal_code` | `varchar(50)`  | tak  | `NULL`  | Kod pocztowy. |
| `recipient_city`        | `varchar(255)` | tak  | `NULL`  | Miejscowość. |
| `recipient_nip`         | `varchar(50)`  | tak  | `NULL`  | NIP Podmiotu3 (może być nadpisany przez `ksef_additional_entity_identifier`). |

Indeks `idx_form_orders_ksef_entity_source` na `ksef_entity_source`.

Stałe w kodzie: `App\Models\FormOrder::KSEF_ENTITY_SOURCES`,
`KSEF_ADDITIONAL_ENTITY_ROLES`, `KSEF_ADDITIONAL_ENTITY_ID_TYPES`,
`KSEF_ROLES_REQUIRING_NIP`.

## Kanoniczna reprezentacja roli

Kody ról trzymamy w jednej, kanonicznej postaci — **lowercase, semantyczne**:

| Kanoniczny kod      | iFirma `Rola`            | KSeF  | Opis biznesowy |
|---------------------|--------------------------|-------|----------------|
| `odbiorca`          | `ODBIORCA`               | 1     | Zwykły odbiorca (domyślne zachowanie historyczne). |
| `jst_recipient`     | `JEDN_SAMORZADU_TERYT`   | 8     | Jednostka samorządu terytorialnego — odbiorca. |
| `vat_group_member`  | `CZLONEK_GRUPY_VAT`      | 9     | Członek grupy VAT — odbiorca. |

Zasady:

- **Baza i aplikacja** znają wyłącznie kanoniczne kody.
- **UI** pokazuje etykiety użytkowe z odwołaniem do kodu iFirma (np.
  „JST — rola 8 (iFirma: JEDN_SAMORZADU_TERYT)”).
- **Mapowanie iFirma** konwertuje kanoniczny kod do wartości oczekiwanej przez
  API (`FormOrder::ksefRoleIfirmaCode()`). Nigdy odwrotnie — w bazie nie pojawi
  się wartość `'ODBIORCA'`, `'JST'` czy `'8'`.

## Backfill dla rekordów historycznych

Migracja ETAP 1 wykonuje jeden deterministyczny `UPDATE` odwzorowujący
**dokładny warunek** budowania bloku `Kontrahent.OdbiorcaNaFakturze` w istniejącym
kodzie `FormOrdersController::createIfirmaInvoiceWithReceiver()` /
`...WithKsef()`:

```sql
UPDATE form_orders
   SET ksef_entity_source = 'recipient',
       ksef_additional_entity_role = 'odbiorca'
 WHERE recipient_name        IS NOT NULL AND recipient_name        <> ''
   AND recipient_postal_code IS NOT NULL AND recipient_postal_code <> ''
   AND recipient_city        IS NOT NULL AND recipient_city        <> '';
```

Pozostałe rekordy mają `ksef_entity_source = 'none'` (default kolumny).

ETAP 2 nie wprowadza dodatkowego backfillu — nowe role (`jst_recipient`,
`vat_group_member`) są wybierane świadomie przez administratora. Nie
zgadujemy z heurystyk (np. z `invoice_notes`).

## Reguła fail-fast

`App\Services\IfirmaAdditionalEntityMapper::build($order)` rzuca
`RuntimeException` (kontroler zwraca HTTP 422 JSON) w następujących
przypadkach:

1. `ksef_entity_source = 'recipient'` + rola inna niż `null`/`''`/
   jedna z obsługiwanych (`odbiorca`, `jst_recipient`, `vat_group_member`)
   → komunikat z listą dozwolonych wartości.
2. `ksef_entity_source = 'recipient'` + `id_type` inny niż `null`/`''`/`'NIP'`
   → komunikat. **Nigdy nie wykonujemy cichego fallbacku do `recipient_nip`**
   dla innych `id_type`.
3. `ksef_entity_source = 'recipient'` + brak któregokolwiek z:
   `recipient_name`, `recipient_postal_code`, `recipient_city`.
4. `ksef_entity_source = 'recipient'` + rola ∈ `{jst_recipient, vat_group_member}`
   + pusty NIP (po normalizacji cyfrowej). KSeF nie przyjmie JST ani
   członka grupy VAT bez NIP, więc odrzucamy request przed uderzeniem do iFirma.

Dodatkowo kontroler zwraca **HTTP 400**, gdy
`ksef_entity_source = 'none'` w ścieżkach explicit wymagających Podmiotu3
(`createIfirmaInvoiceWithReceiver`, `createIfirmaInvoiceWithKsef`).

## Mapowanie na payload iFirma

Źródło prawdy: [`https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/`](https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/).

Struktura zwracana przez `IfirmaAdditionalEntityMapper::build($order)`:

```php
[
    'UzywajDanychOdbiorcyNaFakturach' => true,
    'Nazwa'        => $order->recipient_name,
    'KodPocztowy'  => $order->recipient_postal_code,
    'Miejscowosc'  => $order->recipient_city,
    'Ulica'        => $order->recipient_address, // pominięte gdy puste
    'NIP'          => <patrz niżej>,             // pominięte gdy brak
    'Kraj'         => 'Polska',
    'Rola'         => <wynik FormOrder::ksefRoleIfirmaCode($role)>,
]
```

Reguły dla pola `NIP` (po usunięciu znaków nie-cyfrowych):

| `id_type`       | `identifier`    | `NIP` w payloadzie                      |
|-----------------|-----------------|-----------------------------------------|
| `NULL` / `''`   | (ignorowana)    | z `recipient_nip`                       |
| `'NIP'`         | puste           | z `recipient_nip`                       |
| `'NIP'`         | niepuste        | z `ksef_additional_entity_identifier`   |
| inne            | (dowolna)       | **fail-fast**, brak requestu do iFirma  |

Dla ról `jst_recipient` i `vat_group_member` pusty NIP (po normalizacji)
kończy się fail-fastem. Dla roli `odbiorca` pusty NIP jest dopuszczalny
(osoba prywatna bez NIP).

## Heurystyka z `invoice_notes`

Widoki `create.blade.php`, `edit.blade.php` i `show.blade.php` uruchamiają
prostą heurystykę na treści `invoice_notes`. Frazy takie jak `jst`,
`rola 8`, `rola 9`, `grupa vat`, `podmiot 3`, `odbior` generują **tylko alert
informacyjny** (`alert-info`).

- **Nie ustawiamy** automatycznie żadnego pola.
- **Nie nadpisujemy** decyzji administratora.
- Alert widoczny w formularzu edycji i na widoku szczegółów.

## UI – formularz i widok szczegółów

**Formularz:** `resources/views/form-orders/partials/ksef-additional-entity-form.blade.php`
(dołączony do `create.blade.php` i `edit.blade.php` pod kartą `ODBIORCA`).

Elementy:

- Select `ksef_entity_source` (`none` / `recipient`).
- Select `ksef_additional_entity_role` z etykietami kanoniczny kod + kod iFirma
  oraz inline `alert-info` dla ról JST/grupy VAT (semantyka + wymagany NIP).
- Select `ksef_additional_entity_id_type` z `alert-warning` przy wyborze typu
  innego niż `NIP`.
- Input `ksef_additional_entity_identifier` z podpowiedzią o regule
  nadpisywania `recipient_nip`.
- Textarea `ksef_admin_note` (wewnętrzna).
- Informacja: wartości ról / typu / identyfikatora **nie są usuwane
  automatycznie** przy zmianie `ksef_entity_source` na `none`.
- Nota nazewnicza przypominająca, że `recipient_*` to historyczna nazwa kolumn,
  a semantycznie przechowują dane Podmiotu3.

**Widok szczegółów:** `resources/views/form-orders/partials/ksef-additional-entity-show.blade.php`
pokazuje podsumowanie z badge `aktywny`/`nieaktywny`, rolą kanoniczną
i kodem iFirma, „Podmiot3 efektywny” z docelowymi wartościami `Rola`/`NIP`
oraz badge `fail-fast` dla nieobsługiwanych konfiguracji (rola, id_type,
brakujący NIP dla JST/grupy VAT).

## Role odrzucone w ETAP 2 (i dlaczego)

iFirma udostępnia w polu `OdbiorcaNaFakturze.Rola` jeszcze cztery wartości,
których **świadomie nie wdrażamy** w ETAP 2:

| Rola iFirma           | KSeF | Powód odrzucenia |
|-----------------------|------|------------------|
| `DODATKOWY_NABYWCA`   | —    | Semantycznie to podmiot po stronie nabywcy, nie odbiorcy. Nasze pole `recipient_*` nie jest właściwym miejscem. Brak realnego use-case w projekcie. Do rozważenia w osobnym etapie z dedykowanym blokiem `additional_buyer_*`. |
| `DOKONUJACY_PLATNOSCI`| ~4   | Płatnik. Powiązane ze sprawą faktora (KSeF rola 4) — nie wdrażamy bez jednoznacznego potwierdzenia mapowania FAKTOR w iFirma. |
| `PRACOWNIK`           | 10   | Brak use-case biznesowego (faktury szkoleniowe nie są fakturowane na pracownika). Wprowadzałoby pola bez wartości (pracownik nie ma NIP). |
| `INNA`                | —    | Wymaga pola `NazwaRoli` (free-text). Łamie zasadę zamkniętej, kanonicznej listy ról. Preferujemy dodawanie konkretnych ról zamiast escape-hatchy. |

**KSeF rola 4 Faktor** nie występuje w publicznej dokumentacji iFirma jako
osobna wartość `Rola`. Nie wdrażamy bez potwierdzenia mapowania (oficjalny
dokument API iFirma albo test integracyjny na koncie testowym z inspekcją
payloadu wysłanego do KSeF).

## ETAP 3 — integracja z przyciskami iFirma na stronie zamówienia

Strona szczegółów zamówienia (`/form-orders/{id}`) udostępnia cztery
przyciski wystawiania dokumentu. ETAP 3 ujednolica sposób budowania
obiektu `Kontrahent` dla wszystkich tych ścieżek i podpina metadane KSeF
Podmiotu3 tam, gdzie endpoint iFirma to jawnie wspiera.

### Mapa przycisków → endpointów iFirma → zachowania Podmiotu3

| Przycisk                                    | Metoda kontrolera                   | Endpoint iFirma                       | `OdbiorcaNaFakturze` | Wysyłka do KSeF |
| ------------------------------------------- | ----------------------------------- | ------------------------------------- | -------------------- | --------------- |
| Wystaw PRO-FORMA iFirma                     | `createIfirmaProForma`              | `fakturaproformakraj.json`            | ❌ nigdy             | ❌ nigdy        |
| Wystaw Fakturę iFirma                       | `createIfirmaInvoice`               | `fakturakraj.json`                    | ❌ nigdy             | ❌ nigdy        |
| Wystaw Fakturę iFirma z Odbiorcą            | `createIfirmaInvoiceWithReceiver`   | `fakturakraj.json`                    | ✅ jeśli KSeF `recipient` **lub** kompletne `recipient_*` | ❌ nigdy        |
| Wystaw fakturę i prześlij do KSeF (czerwony)| `createIfirmaInvoiceWithKsef`       | `fakturakraj.json` + `sendInvoiceToKsef` | ✅ jak „z Odbiorcą” (`invoice_with_receiver`) | ✅ zawsze       |

Tryb `podmiot3_mode=invoice_with_receiver` (fioletowy i czerwony): **brak** gate 400
przy `ksef_entity_source = 'none'`; przy `none` i niekompletnych `recipient_*`
wystawiana jest faktura tylko z nabywcą (bez `OdbiorcaNaFakturze`). Tryb
`required` zostaje w builderze na potrzeby testów / ewentualnej przyszłej ścieżki
z twardym wymogiem Podmiotu3 — obecnie kontroler `form_orders` go nie używa.

**E-mail przy czerwonym przycisku:** wysyłka z iFirma (`sendInvoiceByEmail`) jest
wykonywana dopiero po **sukcesie** `sendInvoiceToKsef` (przy błędzie KSeF
kontroler zwraca odpowiedź błędu przed blokiem wysyłki e-mail).

### Wspólny builder — `App\Services\IfirmaKontrahentBuilder`

Wszystkie cztery metody kontrolera budują `Kontrahent` przez jedno miejsce:

- `buildForInvoice(FormOrder $order, ['podmiot3_mode' => string]): array`
  - Format „Polska / PrefiksUE='PL' / OsobaFizyczna=false / Email”.
  - `podmiot3_mode`:
    - **`ignore`** (przycisk „Wystaw Fakturę iFirma”) — **nigdy** nie woła
      mappera; zawsze faktura bez `OdbiorcaNaFakturze`, nawet przy
      `ksef_entity_source='recipient'` i niekompletnych `recipient_*`
      (brak 422 z fail-fastu Podmiotu3).
    - **`auto`** (domyślny, zarezerwowany na ewentualne przyszłe użycie) —
      dokleja `OdbiorcaNaFakturze` gdy `isKsefAdditionalEntityEnabled()`;
      mapper fail-fast → **HTTP 422**.
    - **`required`** (czerwony „prześlij do KSeF”) — jeśli Podmiot3 wyłączony →
      `IfirmaKontrahentException` → **HTTP 400**; w przeciwnym razie jak `auto`.
    - **`invoice_with_receiver`** (fioletowy „z Odbiorcą”) — przy
      `ksef_entity_source='recipient'` pełny `IfirmaAdditionalEntityMapper::build()`
      (role, identyfikator, fail-fast); przy `none` — `buildLegacyRecipientPhysicalOnly()`
      (tylko `recipient_*`, rola `ODBIORCA`, bez czytania `ksef_additional_entity_*`);
      jeśli `recipient_*` niekompletne, faktura **bez** `OdbiorcaNaFakturze`.
  - Nieznany `podmiot3_mode` → `InvalidArgumentException` (kontroler → **HTTP 500**).
- `buildForProForma(FormOrder $order): array`
  - Format pro-forma (`Kraj='PL'`, tylko niepuste pola).
  - **Nigdy** nie dokleja `OdbiorcaNaFakturze`.
  - **Nie modyfikuje** pola `Uwagi` — pro forma nie dostaje technicznych
    dopisków o Podmiocie3 (decyzja z ETAP 3 — klient widzi pro formę, nie
    chcemy jej zaśmiecać metadanymi technicznymi).

### Dlaczego pro forma NIE dostaje `OdbiorcaNaFakturze`

- Publiczna dokumentacja iFirma
  [„Faktura pro forma”](https://api.ifirma.pl/wystawianie-faktury-proforma/)
  w tabeli pól obiektu `Kontrahent` dla `fakturaproformakraj.json` **nie
  wymienia** pola `OdbiorcaNaFakturze` (ani w tabeli, ani w przykładowym
  JSON-ie).
- Dokument
  [„Dodatkowy podmiot na fakturze”](https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/)
  opisuje `OdbiorcaNaFakturze` ogólnie, ale **nie wymienia typów dokumentów**,
  dla których to pole jest wspierane.
- Semantycznie pro forma nie jest dokumentem podatkowym w rozumieniu ustawy
  i **nie podlega KSeF**. Nie ma FA(3) dla pro form, więc Podmiot3 w rozumieniu
  KSeF nie ma tu zastosowania biznesowego.
- Zasada projektu: „nie zgaduj obsługi endpointu, jeśli nie wynika jasno z
  kodu i dokumentacji iFirma”.

Do testowania Podmiotu3 w iFirma bez wysyłki do KSeF służy przycisk
**„Wystaw Fakturę iFirma z Odbiorcą”** — wystawia fakturę krajową z
`OdbiorcaNaFakturze`, bez dodatkowego kroku `sendInvoiceToKsef`. Niebieski
przycisk „Wystaw Fakturę iFirma” służy wyłącznie do faktury **bez** Podmiotu3.

### Mapowanie błędów HTTP

| Kod | Kiedy                                                                                          |
| --- | ---------------------------------------------------------------------------------------------- |
| 400 | Gate `podmiot3_mode=required` zawiódł (`ksef_entity_source = 'none'` dla ścieżki KSeF). |
| 500 | Literówka w `podmiot3_mode` (programista) — `InvalidArgumentException`.                         |
| 400 | Brak `buyer_name` / `product_name` / `product_price`.                                          |
| 409 | Zamówienie ma już fakturę w bazie i nie przekazano `force=true` (WithReceiver/WithKsef/Invoice).|
| 422 | Konfiguracja Podmiotu3 nieobsługiwana: nieznana rola, `id_type != NIP`, niekompletne `recipient_*`, pusty NIP dla `jst_recipient` / `vat_group_member`. |

### Co nie zmieniło się w ETAP 3

- Modele danych, migracje, kolumny MySQL, kontrakt z `pnedu.pl` — bez zmian.
- `IfirmaAdditionalEntityMapper` — rozszerzony o `buildLegacyRecipientPhysicalOnly()`
  (legacy odbiorca z `recipient_*` przy wyłączonym KSeF); `build()` bez zmian.
- Metody `sendInvoiceToKsef`, `applyIfirmaPaymentSettlementToInvoiceData`,
  `applyIfirmaProFormaPaymentTerms` i reszta logiki płatności / e-mail —
  bez zmian.
- Widok `show.blade.php` — tylko dodane krótkie opisy pod przyciskami
  wyjaśniające zachowanie wobec Podmiotu3 i KSeF.

### Testy

`tests/Unit/IfirmaKontrahentBuilderTest.php` pokrywa:

- tryb `auto` / brak opcji + `source='none'` → `Kontrahent` bez `OdbiorcaNaFakturze`;
- tryb `auto` + aktywny Podmiot3 → `Kontrahent.OdbiorcaNaFakturze` (mapper);
- tryb `ignore` + aktywny Podmiot3 / niekompletne `recipient_*` → brak
  `OdbiorcaNaFakturze`, brak wyjątku z mappera;
- tryb `invoice_with_receiver` + `source='none'` + kompletne `recipient_*` →
  `OdbiorcaNaFakturze` z legacy (rola `ODBIORCA`);
- tryb `invoice_with_receiver` + `source='none'` + niekompletne `recipient_*` →
  brak `OdbiorcaNaFakturze`;
- tryb `invoice_with_receiver` + `source='recipient'` → jak mapper (`auto`);
- tryb `auto` + `source='recipient'` + niekompletne `recipient_*` → `RuntimeException`;
- gate `podmiot3_mode=required` + `source='none'` → `IfirmaKontrahentException`;
- `podmiot3_mode=required` + `role=jst_recipient` z pustym NIP → `RuntimeException`
  z mappera (HTTP 422), **nie** opakowany w `IfirmaKontrahentException`;
- nieznany `podmiot3_mode` → `InvalidArgumentException`;
- normalizacja NIP nabywcy (usuwanie myślników / separatorów);
- walidacja e-maila (pusty / nieprawidłowy → `Email=null`);
- pro forma → `Kraj='PL'`, tylko niepuste pola, **nigdy**
  `OdbiorcaNaFakturze` (nawet gdy metadane Podmiotu3 są aktywne).

`tests/Unit/IfirmaAdditionalEntityMapperTest.php` i
`tests/Unit/FormOrderKsefHelpersTest.php` pozostają bez zmian (kontrakt
mappera i modelu nienaruszony).

## Przewidywany zakres ETAPU 4

- Potwierdzenie i ewentualne wdrożenie obsługi `FAKTOR` po zweryfikowaniu
  mapowania w iFirma.
- Obsługa identyfikatorów innych niż `NIP`
  (`IdentyfikatorWewnetrznyZNip` w iFirma) dla edge-case JST i członków grupy VAT.
- Dodatkowe role (`DODATKOWY_NABYWCA`, `DOKONUJACY_PLATNOSCI`, `PRACOWNIK`)
  wdrożone tylko, jeśli pojawi się realny przypadek biznesowy.
- Ewentualne dołączenie `OdbiorcaNaFakturze` do pro formy — **tylko** po
  potwierdzeniu w oficjalnej dokumentacji iFirma albo w teście integracyjnym
  na koncie sandbox.
- Potencjalny pełny rename `recipient_*` → `podmiot3_*` jako osobny sprint
  obejmujący `pneadm-bootstrap` + `pnedu.pl`.

ETAP 3 nie wymaga rollbacku ETAPU 1/2 — rozszerzenia są kompatybilne wstecznie
z modelem danych i mapperem. Kod kontrolera stał się krótszy (~120 usuniętych
linii zduplikowanego składania `Kontrahent`). Jedyna korekta kontraktu HTTP:
przycisk „Wystaw Fakturę iFirma” **nigdy** nie dołącza Podmiotu3 (tryb
`podmiot3_mode=ignore`), więc nie zwraca już 422 z powodu niekompletnych
`recipient_*` przy włączonym `ksef_entity_source='recipient'` — w takim
przypadku wystawiana jest czysta faktura krajowa (tylko nabywca). Ścieżki
WithReceiver / WithKsef i kody błędów 400/422 dla nich pozostają bez zmian.
