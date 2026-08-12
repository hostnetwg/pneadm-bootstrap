# KSeF / iFirma — Podmiot3 w `form_orders` (ETAP 3 — wdrożony w adm)

Dokument opisuje wdrożenie obsługi dodatkowego podmiotu na fakturze
(Podmiot3 → root `PodmiotyDodatkowe` w API iFirma) dla zamówień z tabeli
`form_orders` w projekcie `pneadm` / `adm.pnedu.pl`.

**Łańcuch produkcyjny (świadomie):** panel adm → API iFirma (`fakturakraj.json`)
→ iFirma wysyła dokument do [KSeF](https://ksef.podatki.gov.pl/)
(`sendInvoiceToKsef`). Aplikacja **nie** łączy się bezpośrednio z KSeF MF.

Źródła API: [dokumentacja iFirma](https://api.ifirma.pl/),
[Dodatkowy podmiot na fakturze](https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/),
[Wysyłanie faktury do KSeF](https://api.ifirma.pl/wysylanie-faktury-do-ksef/).

## Status wdrożenia (2026-08)

| Etap | Zakres | Stan |
|------|--------|------|
| **ETAP 1** | Kolumny metadanych `ksef_*` + backfill `recipient` → `odbiorca` | ✅ wdrożony |
| **ETAP 2** | Role `odbiorca` / `jst_recipient` (8) / `vat_group_member` (9), UI metadanych w adm, fail-fast | ✅ wdrożony |
| **ETAP 3** | Przyciski iFirma na `/form-orders/{id}`, `IfirmaKontrahentBuilder`, `PodmiotyDodatkowe` (zmiana API iFirma 2026-08-04), dwufazowy czerwony przycisk + sync KSeF | ✅ wdrożony w **adm** |
| **ETAP 4** | Faktor / inne role / rename `recipient_*` / Podmiot3 na pro formie | ⏸ częściowo: **A2 wdrożone** (JST/VAT + IDWew → `IdentyfikatorWewnetrznyZNip` + `NIP` z `recipient_nip`) |
| **pnedu.pl** | Oznaczenia KSeF / role w publicznym formularzu zamówienia | ❌ poza zakresem (świadomie) |

Badge „ETAP 3” w UI adm (`show` / `edit` / `create`) oznacza powyższy stan metadanych +
integracji przycisków — **nie** oznacza, że publiczny formularz na pnedu.pl cokolwiek wie o KSeF.

- **Obsługiwane role:** `odbiorca`, `jst_recipient` (KSeF rola 8),
  `vat_group_member` (KSeF rola 9).
- **Nie ma** wariantu `custom`.
- **Nie dublujemy** danych `recipient_*`.
- **Bez zmian** w publicznym formularzu `pnedu.pl` (m.in. `/courses/{id}/order-form`).
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
12. [Proponowany kolejny krok operacyjny](#proponowany-kolejny-krok-operacyjny)

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
2. `ksef_entity_source = 'recipient'` + `id_type` spoza `null`/`''`/`NIP`/`IDWew`
   → komunikat. **Nigdy nie wykonujemy cichego fallbacku do `recipient_nip`**
   dla innych `id_type` (np. PESEL, BrakID).
3. `ksef_entity_source = 'recipient'` + brak któregokolwiek z:
   `recipient_name`, `recipient_postal_code`, `recipient_city`.
4. `ksef_entity_source = 'recipient'` + rola ∈ `{jst_recipient, vat_group_member}`
   + pusty NIP (po normalizacji cyfrowej). KSeF nie przyjmie JST ani
   członka grupy VAT bez NIP, więc odrzucamy request przed uderzeniem do iFirma.
5. `id_type = IDWew` + rola JST/VAT + pusty `recipient_nip` → fail-fast
   (wymagane oba: `IdentyfikatorWewnetrznyZNip` z identyfikatora oraz `NIP`
   z `recipient_nip` — wariant A2).

Dodatkowo kontroler zwraca **HTTP 400**, gdy
`ksef_entity_source = 'none'` w ścieżkach explicit wymagających Podmiotu3
(`createIfirmaInvoiceWithReceiver`, `createIfirmaInvoiceWithKsef`).

## Mapowanie na payload iFirma

Źródło prawdy: [`https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/`](https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/).

**Od 2026-08-04** iFirma nie używa już `Kontrahent.OdbiorcaNaFakturze` / `DaneOdbiorcy`.
Podmiot3 trafia na fakturę w root **`PodmiotyDodatkowe`** (tablica wpisów). Pole
`UzywajDanychOdbiorcyNaFakturach` zastąpiono przez **`CzyDomyslny`**
([historia zmian API](https://api.ifirma.pl/)).

Struktura jednego wpisu zwracanego przez `IfirmaAdditionalEntityMapper::build($order)`
i doklejanego przez `IfirmaKontrahentBuilder::buildPodmiotyDodatkowe()`:

```php
// root payloadu faktury (fakturakraj.json):
'PodmiotyDodatkowe' => [
    [
        'CzyDomyslny' => true,
        'Nazwa'        => $order->recipient_name,
        'KodPocztowy'  => $order->recipient_postal_code,
        'Miejscowosc'  => $order->recipient_city,
        'Ulica'        => $order->recipient_address, // pominięte gdy puste
        'NIP'          => <patrz niżej>,             // pominięte gdy brak
        'Kraj'         => 'Polska',
        'Rola'         => <wynik FormOrder::ksefRoleIfirmaCode($role)>,
    ],
],
```

`Kontrahent` zawiera **wyłącznie nabywcę** — bez zagnieżdżonego odbiorcy.

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

- Checkbox „Użyj danych Odbiorcy (`recipient_*`) jako Podmiot3” → zapisuje
  `ksef_entity_source` = `recipient` (zaznaczony) / `none` (odznaczony).
  Na **create** domyślnie zaznaczony; na show/edit odzwierciedla wartość z bazy.
  Wybór roli JST / grupa VAT automatycznie zaznacza checkbox (jeśli był odznaczony).
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

**Widok szczegółów (`/form-orders/{id}`):** w karcie **DANE DO FAKTURY**
(pod Nabywcą/Odbiorcą) zintegrowane są:

1. `ksef-additional-entity-inline.blade.php` — edycja metadanych (zapis AJAX),
2. `ksef-additional-entity-show.blade.php` — podgląd efektywny (`aktywny`/`nieaktywny`,
   rola iFirma, NIP w payloadzie, badge `fail-fast`).

Po zapisie AJAX podgląd odświeża się bez przeładowania strony (`summary_html`).

## Role odrzucone w ETAP 2 (i dlaczego)

iFirma udostępnia w polu `Rola` wpisu `PodmiotyDodatkowe` jeszcze cztery wartości
([dokumentacja](https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/)),
których **świadomie nie wdrażamy** (decyzja z ETAP 2, nadal aktualna):

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

| Przycisk                                    | Metoda kontrolera                   | Endpoint iFirma                       | `PodmiotyDodatkowe` | Wysyłka do KSeF |
| ------------------------------------------- | ----------------------------------- | ------------------------------------- | ------------------- | --------------- |
| Wystaw PRO-FORMA iFirma                     | `createIfirmaProForma`              | `fakturaproformakraj.json`            | ❌ nigdy             | ❌ nigdy        |
| Wystaw Fakturę iFirma                       | `createIfirmaInvoice`               | `fakturakraj.json`                    | ❌ nigdy             | ❌ nigdy        |
| Wystaw Fakturę iFirma z Odbiorcą            | `createIfirmaInvoiceWithReceiver`   | `fakturakraj.json`                    | ✅ jeśli KSeF `recipient` **lub** kompletne `recipient_*` | ❌ nigdy        |
| Wystaw fakturę i prześlij do KSeF (czerwony)| `createIfirmaInvoiceWithKsef`       | `fakturakraj.json` + `sendInvoiceToKsef` | ✅ jak „z Odbiorcą” (`invoice_with_receiver`) | ✅ zawsze       |

Tryb `podmiot3_mode=invoice_with_receiver` (fioletowy i czerwony): **brak** gate 400
przy `ksef_entity_source = 'none'`; przy `none` i niekompletnych `recipient_*`
wystawiana jest faktura tylko z nabywcą (bez `PodmiotyDodatkowe`). Tryb
`required` zostaje w builderze na potrzeby testów / ewentualnej przyszłej ścieżki
z twardym wymogiem Podmiotu3 — obecnie kontroler `form_orders` go nie używa.

**E-mail przy czerwonym przycisku:** wysyłka z iFirma (`sendInvoiceByEmail`) jest
wykonywana dopiero po **sukcesie** KSeF (NumerKSeF). Przy starcie fazy `ksef`, gdy
`send_email=true`, zapisywane jest `form_orders.ksef_email_pending = true` **przed**
pollem — intencja przeżywa timeout HTTP / brak NumerKSeF. Po pełnym sukcesie maili
flaga wraca do `false`. Przy błędzie / częściowej wysyłce flaga zostaje (można
dogonić przez **Odśwież KSeF**).

**UI / API (2026-07):** przycisk czerwony wywołuje endpoint w **dwóch fazach**
(`phase=create` → zapis `invoice_number` w zamówieniu i odświeżenie pola w
formularzu, potem `phase=ksef` → KSeF + polling). Przy timeoutie KSeF numer
faktury iFirma pozostaje zapisany (`partial_success` / `invoice_created` w JSON).
Serwis: `App\Services\IfirmaFormOrderKsefSubmissionService`.

**Filtr nawigacji „Tylko z NIP bez KSeF” (2026-08):** na `/form-orders/{id}` checkbox
`filter_no_ksef=1` — zamówienia z **NIP nabywcy** (`buyer_nip` z cyframi),
wypełnionym klasycznym `invoice_number` i pustym `ksef_number`
(kolejka do dogonięcia KSeF; bez FV lub bez NIP — poza filtrem). Współdziała z
`filter_no_participant`, `filter_no_invoice`, `filter_payment_gateway` i `course_id` (prev/next + badge).
Legacy `filter_new=1` mapuje się na `filter_no_invoice`.

**Filtr nawigacji „bramka płatności” (2026-08):** checkbox `filter_payment_gateway=1` —
zamówienia z `payment_mode=online_gateway` (PayU/Paynow), **niezależnie od statusu**
płatności w bramce; **bez** anulowanych (`cancelled_at`); **bez** FV odroczonej
(`deferred_invoice`).

**Synchronizacja KSeF / danych FV (2026-08):** ikona odświeżenia przy **Numerze faktury**,
przy **ID iFirma** oraz przy polu Numer KSeF na `/form-orders/{id}` → `POST …/ifirma/sync-ksef`.
Przycisk przy numerze FV wysyła `prefer_number_lookup=1` + aktualną wartość z inputa
(nawet niespiętą „Zapisz”). Gdy input numeru FV jest **pusty**, ten sam przycisk **czyści**
w zamówieniu `invoice_number`, `ifirma_invoice_id`, `ksef_number`, `invoice_issue_date`, `invoice_due_date` —
np. po przeniesieniu FV do innego zamówienia. Preferuje wyszukanie dokumentu po **`invoice_number`**
(lista iFirma, jak windykacja) — przydatne po ręcznym wystawieniu FV w panelu iFirma
lub gdy stare `ifirma_invoice_id` wskazuje usunięty dokument. Ikona przy **ID iFirma**
(oraz przy KSeF) synchronizuje po zapisanym `ifirma_invoice_id` (`prefer_number_lookup=0`)
— uzupełnia brakujący `invoice_number` z `PelnyNumer`, **daty FV** oraz **NumerKSeF**.
Ręcznie wpisanego `invoice_number` **nie nadpisuje**. Gdy w iFirma **brak** `NumerKSeF`, lokalny numer KSeF **nie jest
czyszczony** (wcześniej sync kasował ręczny wpis). Gdy sync uzyska NumerKSeF i
`ksef_email_pending=true`, wysyła FV mailem przez iFirma (te same adresy co czerwony
przycisk; ~400 ms między adresami; bez agresywnego retry). Po pełnym sukcesie
czyści flagę — kolejne Odśwież nie wysyła ponownie. **Bez** flagi sync **nie** wysyła
maila. Zbiorcze Odśwież / kolejka KSeF — poza zakresem tego etapu (gdy bulk: kolejka
concurrency 1 + opóźnienie między jobami). Serwis:
`App\Services\IfirmaFormOrderKsefSyncService`.

**Daty FV przy wystawianiu (2026-08):** po `fakturakraj.json` panel robi `GET` faktury
(jak przy sync KSeF) i zapisuje `invoice_issue_date` / `invoice_due_date` od razu
wraz z numerem FV i ID iFirma; JSON odpowiedzi zawiera te daty, a UI odświeża blok dat
bez przeładowania strony.

### Wspólny builder — `App\Services\IfirmaKontrahentBuilder`

Wszystkie cztery metody kontrolera budują `Kontrahent` przez jedno miejsce:

- `buildForInvoice(FormOrder $order): array` — tylko nabywca (`Kontrahent`).
- `buildPodmiotyDodatkowe(FormOrder $order, ['podmiot3_mode' => string]): array`
  - zwraca listę wpisów do root `PodmiotyDodatkowe` (zwykle 0 lub 1);
  - `podmiot3_mode`:
    - **`ignore`** (przycisk „Wystaw Fakturę iFirma”) — pusta tablica.
    - **`auto`** — wpis gdy `isKsefAdditionalEntityEnabled()` (mapper, fail-fast 422).
    - **`required`** — gate 400 gdy Podmiot3 wyłączony; w przeciwnym razie jak `auto`.
    - **`invoice_with_receiver`** (fioletowy i czerwony KSeF) — przy `recipient` pełny
      mapper; przy `none` legacy z `recipient_*` (rola `ODBIORCA`) lub pusta tablica.
- `buildForProForma(FormOrder $order): array`
  - Format pro-forma (`Kraj='PL'`, tylko niepuste pola).
  - **Nigdy** nie dokleja `OdbiorcaNaFakturze`.
  - **Nie modyfikuje** pola `Uwagi` — pro forma nie dostaje technicznych
    dopisków o Podmiocie3 (decyzja z ETAP 3 — klient widzi pro formę, nie
    chcemy jej zaśmiecać metadanymi technicznymi).

### Dlaczego pro forma NIE dostaje `PodmiotyDodatkowe`

- Publiczna dokumentacja iFirma
  [„Faktura pro forma”](https://api.ifirma.pl/wystawianie-faktury-proforma/)
  dla `fakturaproformakraj.json` **nie potwierdza** obsługi bloku
  `PodmiotyDodatkowe` / historycznego `OdbiorcaNaFakturze`.
- Semantycznie pro forma nie jest dokumentem podatkowym w rozumieniu ustawy
  i **nie podlega KSeF**. Nie ma FA(3) dla pro form, więc Podmiot3 w rozumieniu
  KSeF nie ma tu zastosowania biznesowego.
- Zasada projektu: „nie zgaduj obsługi endpointu, jeśli nie wynika jasno z
  kodu i dokumentacji iFirma”.

Do testowania Podmiotu3 w iFirma bez wysyłki do KSeF służy przycisk
**„Wystaw Fakturę iFirma z Odbiorcą”** — wystawia fakturę krajową z
`PodmiotyDodatkowe`, bez dodatkowego kroku `sendInvoiceToKsef`. Niebieski
przycisk „Wystaw Fakturę iFirma” służy wyłącznie do faktury **bez** Podmiotu3.
Czerwony przycisk = ta sama budowa nabywcy/Podmiotu3 + `sendInvoiceToKsef`
([endpoint iFirma](https://api.ifirma.pl/wysylanie-faktury-do-ksef/)).

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

`tests/Unit/IfirmaKontrahentBuilderTest.php` pokrywa (kontrakt po migracji
API 2026-08 — `PodmiotyDodatkowe`, nie `Kontrahent.OdbiorcaNaFakturze`):

- tryb `auto` / `source='none'` → pusta tablica `PodmiotyDodatkowe`;
- tryb `auto` + aktywny Podmiot3 → wpis w `PodmiotyDodatkowe` (mapper);
- tryb `ignore` → zawsze pusta tablica, bez wyjątku z mappera;
- tryb `invoice_with_receiver` + `source='none'` + kompletne `recipient_*` →
  legacy wpis z rolą `ODBIORCA`;
- tryb `invoice_with_receiver` + niekompletne `recipient_*` → pusta tablica;
- tryb `invoice_with_receiver` + `source='recipient'` → jak mapper (`auto`);
- gate `podmiot3_mode=required` + `source='none'` → `IfirmaKontrahentException`;
- `jst_recipient` z pustym NIP → `RuntimeException` (HTTP 422);
- pro forma → **nigdy** nie buduje `PodmiotyDodatkowe`.

Powiązane: `IfirmaAdditionalEntityMapperTest`, `FormOrderKsefHelpersTest`,
`IfirmaFormOrderKsefSubmissionServiceTest`, `IfirmaFormOrderKsefSyncServiceTest`
(patrz `docs/TESTING.md`).

## Przewidywany zakres ETAPU 4

- Potwierdzenie i ewentualne wdrożenie obsługi faktora / `DOKONUJACY_PLATNOSCI`
  po zweryfikowaniu mapowania w iFirma (w publicznej dokumentacji brak osobnej
  wartości `FAKTOR` w polu `Rola`).
- ✅ **A2 (2026-08):** `IdentyfikatorWewnetrznyZNip` + `NIP` z `recipient_nip`
  dla ról JST / grupa VAT przy `id_type=IDWew`
  ([dokumentacja iFirma](https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/)).
  Dla `odbiorca` + IDWew nadal tylko `IdentyfikatorWewnetrznyZNip`.
- Dodatkowe role (`DODATKOWY_NABYWCA`, `DOKONUJACY_PLATNOSCI`, `PRACOWNIK`, `INNA`)
  tylko przy realnym przypadku biznesowym.
- Podmiot3 na pro formie — **tylko** po potwierdzeniu w dokumentacji iFirma
  lub teście sandbox (obecnie świadomie wyłączone).
- Rename `recipient_*` → `podmiot3_*` — osobny sprint `pneadm` + `pnedu.pl`
  (poza obecnym zakresem „tylko adm”).

ETAP 3 nie wymaga rollbacku ETAPU 1/2 — rozszerzenia są kompatybilne wstecznie
z modelem danych i mapperem. Przycisk „Wystaw Fakturę iFirma” **nigdy** nie
dołącza Podmiotu3 (`podmiot3_mode=ignore`).

## Proponowany kolejny krok operacyjny

**Zalecenie (bez zmian w pnedu.pl):** najpierw walidacja produkcyjna ścieżki adm,
dopiero potem kod ETAP 4.

1. **Smoke test na zamówieniu testowym w adm** (np. kopia #8348):
   - ustaw `ksef_entity_source=recipient` + rolę `odbiorca` → fioletowy przycisk
     (faktura z `PodmiotyDodatkowe`, bez KSeF) → sprawdź podgląd w panelu iFirma;
   - na drugim zamówieniu / po resecie: rola `jst_recipient` + NIP JST →
     **czerwony** przycisk (create → KSeF) → sprawdź `NumerKSeF` w adm i w iFirma;
   - upewnij się, że e-mail z FV idzie dopiero po nadaniu numeru KSeF.
2. **Decyzja Waldemara — ETAP 4.1:**
   - **A2)** ✅ wdrożone — JST/VAT + IDWew → `IdentyfikatorWewnetrznyZNip` + `NIP` (`recipient_nip`);
   - **B)** nowa rola biznesowa (np. płatnik), jeśli pojawi się konkretne zamówienie;
   - **C)** procedura operacyjna + szkolenie zespołu z UI adm;
   - **D)** później: pola KSeF w formularzu publicznym pnedu.pl (osobny projekt).

Następny krok po A2: smoke na zamówieniu z JST+IDWew (np. #7987) fioletowym/czerwonym przyciskiem.

## Identyfikatory faktury na `form_orders`

Obok klasycznego numeru i KSeF zapisujemy wewnętrzny ID dokumentu iFirma:

| Kolumna | Źródło iFirma | Uwagi |
|---------|---------------|--------|
| `invoice_number` | `PelnyNumer` | Numer widoczny dla klienta / księgowości |
| `ksef_number` (+ `ksef_status`, `ksef_sent_at`, …) | NumerKSeF | Po akceptacji w KSeF |
| `ksef_email_pending` | — (panel) | Intencja: wyślij FV mailem po NumerKSeF; migracja `2026_08_10_101120_add_ksef_email_pending_to_form_orders_table.php` |
| `ifirma_invoice_id` | `Identyfikator` / FakturaId | Migracja `2026_07_31_160000_add_ifirma_invoice_id_to_form_orders_table.php`; klucz do API `fakturakraj/{id}` |

Zapis `ifirma_invoice_id` przy wystawianiu FV krajowej, FV z odbiorcą oraz FV+KSeF
(`FormOrdersController`, `IfirmaFormOrderKsefSubmissionService`). Pro-forma **nie**
ustawia tego pola (ani `invoice_number`). W UI szczegółów zamówienia: „ID iFirma”
pod numerem faktury (z ikoną odświeżenia po ID). Historyczne zamówienia bez ID dostaną je przy kolejnym kontakcie
z iFirma (wystawienie / faza KSeF) albo przy przyszłym backfillu.
