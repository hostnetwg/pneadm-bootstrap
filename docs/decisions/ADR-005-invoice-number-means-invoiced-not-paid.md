# ADR-005: `invoice_number` oznacza „zafakturowane / rozliczone operacyjnie”, nie „opłacone”

Data utworzenia/aktualizacji: 2026-06-25  
Status: **zaakceptowane** (decyzja właściciela Waldemara + konsultacja ChatGPT, 2026-06-25). Dotyczy dokumentacji/semantyki; bez zmian kodu.

## Kontekst

PNEdu ma dwie ścieżki zakupu:

1. **Płatność online** (bramka PayU/PayNow) — istnieje techniczne potwierdzenie wpływu środków od operatora płatności.
2. **Płatność odroczona / faktura** — klient „Wysyła zamówienie", otrzymuje fakturę z terminem płatności. **Nie istnieje** proces oznaczania takiego zamówienia jako opłacone na podstawie wyciągu bankowego.

W praktyce operacyjnej PNEdu od dawna obowiązuje reguła:

> Jeżeli w `form_orders.invoice_number` pojawia się numer faktury, zamówienie uznaje się za **rozliczone operacyjnie** (zafakturowane).

Numer faktury jest ustawiany w `pneadm` w dwóch sytuacjach:

- automatycznie po wystawieniu faktury krajowej przez iFirma (`FormOrdersController`, pole `invoice_number` = `PelnyNumer` z iFirma),
- ręcznie przez administratora (edycja zamówienia z listy / `show` / strony edycji).

Ważne: **faktura PRO-FORMA NIE ustawia `invoice_number`** — zapisywana jest w polu `notes`. Pro-forma nie jest więc rozliczeniem.

Stary lejek operacyjny (`CourseFunnelStatsService` w `pneadm`) już dziś używa tej reguły:

- „złożone" = rekord w `form_orders` (z wykluczeniem ręcznie zamkniętych bez faktury),
- „zafakturowane" = `invoice_number IS NOT NULL AND != '' AND != '0'`,
- istnieje alias `orders_paid`, który faktycznie liczy zafakturowane (`= orders_invoiced`).

Powstaje ryzyko, że w nowej analityce eventowej i dashboardach `invoice_number` zostanie błędnie zinterpretowany jako „opłacone" (`paid`), co zafałszowałoby raporty przychodu i konwersji.

## Decyzja

1. `form_orders.invoice_number` (niepuste, różne od `''` i `'0'`) oznacza **zafakturowane / rozliczone operacyjnie**, a **nie** fizyczny wpływ pieniędzy.
2. W analityce i dashboardach rozdzielamy dwa źródła prawdy o rozliczeniu:
   - **online**: `online_paid` = event `payment_status_changed` z `payment_status = paid` (źródło prawdy: bramka),
   - **odroczone**: `deferred_invoiced` = pierwsze pojawienie się `invoice_number` (przyszły event `invoice_created`).
3. Metryka łączna:
   - `settled_orders_total = online_paid + deferred_invoiced`,
   - po polsku: „Rozliczone łącznie = opłacone online + zafakturowane odroczone".
4. Unikamy płaskiego określenia „opłacone" dla obu ścieżek razem, chyba że jest wyraźnie opisane jako uproszczenie.
5. Dla ścieżki odroczonej `invoice_number` jest obecnym biznesowym sygnałem rozliczenia, ponieważ **nie istnieje** jeszcze proces uzgadniania przelewów bankowych. Faktyczne potwierdzanie wpływu środków po wyciągu bankowym (np. przyszły event `bank_payment_confirmed`) to osobny, odleglejszy etap — poza zakresem obecnej analityki.
6. Alias `orders_paid` w starym lejku (`CourseFunnelStatsService`) **zostaje** bez zmian kodu (kompatybilność starego kodu i widoków). W dokumentacji opisany jest jako historyczny/kompatybilnościowy alias `orders_invoiced`. W nowych agregatach i dashboardach **nie używamy** nazwy `orders_paid` dla faktur odroczonych; preferowane: `orders_invoiced` / `deferred_invoiced`.

## Nazewnictwo

Metryki liczby zamówień:

- `online_paid` — zamówienia online opłacone (bramka, `payment_status_changed: paid`),
- `deferred_invoiced` — zamówienia odroczone zafakturowane (pierwsze `invoice_number`),
- `settled_orders_total` — suma powyższych,
- `orders_invoiced` — preferowana nazwa dla „zafakturowane" (zamiast `orders_paid`),
- `orders_paid` — DOZWOLONE tylko jako historyczny alias **jasno opisany** jako `orders_invoiced`.

Metryki przychodu:

- `ordered_revenue_gross` — przychód zamówiony, wg daty `form_order_created`,
- `online_paid_revenue_gross` — przychód opłacony online, wg daty `payment_status_changed: paid`,
- `deferred_invoiced_revenue_gross` — przychód zafakturowany odroczony, wg daty `invoice_created`,
- `settled_revenue_gross` — suma `online_paid_revenue_gross + deferred_invoiced_revenue_gross`.

Cel rozróżnienia przychodu:

- `ordered_revenue_gross` służy do **oceny sprzedaży wygenerowanej przez kampanie** (ile zamówień powstało, niezależnie od rozliczenia),
- `settled_revenue_gross` służy do **oceny przychodu rozliczonego operacyjnie** (opłacone online + zafakturowane odroczone).

Określenie `paid` / „opłacone" rezerwujemy wyłącznie dla ścieżki online (bramka). Dla odroczonych używamy `invoiced` / „zafakturowane".

## Konsekwencje

- Przyszły dashboard będzie pokazywał osobno: opłacone online i zafakturowane odroczone, a łącznie jako „rozliczone".
- Stary alias `orders_paid` w `CourseFunnelStatsService` pozostaje na razie bez zmian kodu (kompatybilność widoków `marketing-funnel`), ale jest oznaczony jako mylący — docelowo do zmiany nazwy na `orders_invoiced` po decyzji właściciela (osobny krok, nie refaktoryzowane teraz).
- Kolumny `paid_orders` / `invoiced_orders` w planie schematu agregatów (`DATABASE_SCHEMA_PLAN.md`) są obecnie placeholderami (zawsze `0`); przy wdrażaniu agregatów płatności trzeba zmapować je zgodnie z tą decyzją (`paid_orders` tylko online; `invoiced_orders` z `invoice_created`).

## Wpływ na dashboard

- Nie mieszać „opłacone online" z „zafakturowane odroczone" w jednej kolumnie bez etykiety.
- Domyślnie pokazywać trzy wartości: opłacone online, zafakturowane odroczone, rozliczone łącznie.
- Przychód rozliczony liczyć z odpowiednich dat (online: data `paid`; odroczone: data `invoice_created`), oddzielnie od przychodu zamówionego (data `form_order_created`).

## Wpływ na przyszły event `invoice_created` (nie wdrażany w tym kroku)

- **Trigger**: pierwsze ustawienie `invoice_number` w `form_orders` (iFirma lub ręcznie), w `pneadm`.
- **Znaczenie**: zamówienie zafakturowane / rozliczone operacyjnie. NIE oznacza wpływu przelewu.
- **Payload (bezpieczny)**: `form_order_id`, `course_id`, `order_flow`, `invoice_path_type` (`ifirma`/`manual`), `amount_gross` (jeśli bezpiecznie dostępne), ewentualnie `buyer_type` (neutralne).
- **Zakazane**: numer faktury, NIP, nazwa nabywcy/odbiorcy, adres, dane uczestników, dane fakturowe, dane/raw response iFirma, payload KSeF, raw request.
- **Idempotencja**: `event_uuid = invoice_created|{form_order_id}` → jeden event na zamówienie, nawet jeśli numer faktury zostanie później poprawiony. Korekty/zmiany numeru/anulowanie/KSeF nie są śledzone na tym etapie.

## Edge case: online + faktura

Zamówienie online może później otrzymać `invoice_number` (faktura księgowa do płatności online). Wtedy:

- źródłem prawdy o opłaceniu pozostaje bramka (`payment_status_changed: paid`),
- przyszły `invoice_created` dla takiego zamówienia jest tylko znacznikiem księgowym i **nie może** drugi raz zwiększać metryki „rozliczone łącznie".

Dlatego:

```text
settled_orders_total = online_paid + deferred_invoiced
```

a NIE:

```text
online_paid + all_invoice_created
```

`deferred_invoiced` liczymy tylko dla zamówień ze ścieżki odroczonej, aby nie dublować online z fakturą.

## Wykrywanie `deferred_invoiced` (dla przyszłych agregatów)

Ścieżkę odroczoną rozpoznajemy po **jednoznacznym, bezpiecznym polu** zamówienia z `FormOrder`:

- `payment_mode`,
- `order_flow`,
- albo równoważne jednoznaczne pole `FormOrder`.

**Nie** wykrywać ścieżki odroczonej przez **brak** powiązanego `OnlinePaymentOrder` — to warunek pośredni i może prowadzić do błędów (np. nieudana inicjalizacja online).

Zasada liczenia:

```text
invoice_created z order_flow=deferred  -> liczy się do deferred_invoiced
invoice_created z order_flow=online    -> tylko znacznik księgowy, NIE zwiększa settled_orders_total
```

Dla ścieżki online źródłem prawdy o opłaceniu pozostaje bramka: `payment_status_changed: paid`.

## Poza zakresem obecnego modelu

Poniższe obszary **nie są** wdrażane ani modelowane na tym etapie. Mogą w przyszłości wymagać osobnych eventów i osobnych decyzji:

- faktyczne potwierdzanie przelewów bankowych po wyciągu (rekonsyliacja),
- częściowe płatności,
- zaliczki,
- faktury zaliczkowe,
- wiele faktur do jednego zamówienia,
- korekty faktur,
- anulowanie faktury,
- zwroty płatności online,
- chargebacki,
- ręczne korekty statusów płatności,
- rozliczenia mieszane (część online, część fakturą),
- KSeF,
- szczegółowe dane księgowe.

Potencjalne przyszłe eventy (NIE wdrażane teraz, nazwy poglądowe):

```text
bank_payment_confirmed
invoice_corrected
invoice_cancelled
refund_created
chargeback_received
```

Przyjęty obecnie model `invoice_created` jest świadomym uproszczeniem: jeden event na pierwsze poprawne `invoice_number`, bez śledzenia korekt, anulowań, wielu faktur ani KSeF.

## Ryzyko mylenia „zafakturowane" z „opłacone"

| Ryzyko | Ograniczenie |
|---|---|
| `orders_paid` (alias) interpretowany jako realnie opłacone | dopisana adnotacja, docelowa zmiana nazwy na `orders_invoiced` |
| `paid_orders` w schemacie agregatów użyte dla odroczonych | mapować tylko online do `paid_orders`, odroczone do `invoiced_orders` |
| dashboard pokazuje „opłacone" łącznie | rozdzielić online vs odroczone, etykieta „rozliczone łącznie" |
| pro-forma uznana za rozliczenie | pro-forma nie ustawia `invoice_number` (jest w `notes`) — nie liczona |
| przychód odroczony liczony jak opłacony | rozdzielić `ordered_` / `online_paid_` / `deferred_invoiced_` / `settled_` revenue |

## Status implementacji

- Etap 2C-1 (2026-06-25): event `invoice_created` **wdrożony lokalnie** w `pneadm` przez observer `App\Observers\FormOrderObserver` + `App\Services\Analytics\InvoiceAnalyticsTracker`. Trigger: przejście `invoice_number` empty→present; idempotencja `invoice_created|{form_order_id}`; payload bezpieczny (bez PII/numeru faktury/iFirma/KSeF). Ograniczenie: observer łapie tylko zapisy przez Eloquent. Testy: `tests/Feature/AnalyticsInvoiceCreatedStage2C1Test.php`.
- NIE wdrożono (świadomie): agregatów/dashboardu faktur, mapowania kolumn `paid_orders`/`invoiced_orders`, zmiany aliasu `orders_paid`, eventów `bank_payment_confirmed`/`invoice_corrected`/`invoice_cancelled`/`refund_created`/`chargeback_received`.

## Do aktualizacji po wdrożeniu (przyszłe kroki, nie teraz)

- Mapowanie kolumn agregatów (`paid_orders`, `invoiced_orders`) i ewentualna zmiana nazwy aliasu `orders_paid` → `orders_invoiced`.
- Rozszerzenie dashboardu o rozdzielone metryki rozliczeń i przychodu.
- Komenda rekonsyliacyjna dla `invoice_number` ustawianych poza Eloquent (bezpośrednie `UPDATE` / importy).
