# Polityka stref czasowych — pneadm + pnedu

Jeden dokument kanoniczny dla obu serwisów. Ostatnia aktualizacja: 2026-07-04.

## Dwa typy pól dat

| Typ | Przykłady | Typ MySQL | Zapis | Wyświetlanie |
|-----|-----------|-----------|-------|--------------|
| **Moment zdarzenia (UTC)** | `form_orders.order_date`, `analytics_events.occurred_at`, `created_at` | `TIMESTAMP` lub UTC datetime | Zawsze **UTC** w bazie | **Europe/Warsaw** w UI |
| **Termin kalendarzowy (PL)** | `courses.start_date`, `courses.end_date` | `DATETIME` | Godzina wpisana w formularzu adm (czas polski) | Bez konwersji strefy |

## Konfiguracja (.env — oba projekty)

```env
APP_TIMEZONE=Europe/Warsaw
DB_TIMEZONE=+00:00
```

Po zmianie: `php artisan config:clear`

**Krytyczne:** połączenie do bazy `pneadm` w **pnedu** musi mieć `DB_TIMEZONE=+00:00` (wcześniej domyślnie `+02:00` psuło zapis `order_date` o −2 h).

## Kod — zamówienia (`form_orders.order_date`)

### Zapis (pnedu, pneadm)

```php
$order->order_date = now('UTC');
// lub FormOrder::create(['order_date' => now('UTC'), ...]);
```

Model `FormOrder` w obu projektach: mutator `setOrderDateAttribute` wymusza UTC.

### Wyświetlanie

```php
$order->formatOrderDateLocal();           // d.m.Y H:i
$order->formatOrderDateLocal('Y-m-d H:i:s');
```

Nie używać `$order->order_date->format(...)` — cast Laravel + sesja MySQL mogą wprowadzić błąd.

Wzorzec (jak w panelu analityki debug-events):

```php
Carbon::createFromFormat('Y-m-d H:i:s', $rawFromDb, 'UTC')
    ->timezone(config('app.timezone'))
    ->format('...');
```

### Filtry / analityka po dniu

Granice dnia liczyć w `Europe/Warsaw`, porównywać w SQL jako UTC:

```php
use App\Support\UtcStorageDate;

[$fromUtc, $toUtc] = UtcStorageDate::utcRangeForLocalDays($from, $to);
$query->whereBetween('order_date', [$fromUtc, $toUtc]);
```

## Korekta danych historycznych

**Zakres operacyjny:** tylko `submission_source = pnedu_order_form` (błąd −2 h przy zapisie z pnedu.pl).

Rekordy legacy (`submission_source IS NULL`, ~4800 szt.) — **poza automatyczną korektą**. Po migracji z certgen źródłem prawdy jest wyłącznie `pneadm.form_orders`; ewentualna korekta legacy wymagałaby osobnej decyzji biznesowej (nie porównujemy już z `certgen.zamowienia_FORM`).

Wdrożenie prod: **[FORM_ORDERS_TIMEZONE_PRODUCTION.md](./FORM_ORDERS_TIMEZONE_PRODUCTION.md)**

```bash
# Raport CSV przed korektą (prod / dev)
sail artisan form-orders:normalize-order-dates --scope=pnedu_bug --since=2025-10-18 --dry-run --export-csv

# Korekta po akceptacji CSV
sail artisan form-orders:normalize-order-dates --scope=pnedu_bug --since=2025-10-18 --force
```

| Kohorta | Objaw | Korekta UTC |
|---------|-------|-------------|
| `pnedu_order_form` od 2025-10-18 | UI −2 h vs rzeczywistość | `+2 HOUR` |
| `submission_source IS NULL` | ewentualnie inny offset z importu | **nie automatyzujemy** |

## Analityka (`pne_analytics`)

`occurred_at` zapisywane w UTC; debug panel od początku konwertował poprawnie — stąd zgodność z rzeczywistą godziną przy błędnym `order_date`.

## Eksport / import MySQL

Patrz: [PHPMYADMIN_EXPORT_IMPORT_TIMEZONE.md](../PHPMYADMIN_EXPORT_IMPORT_TIMEZONE.md)

Przed eksportem/importem: `SET time_zone = '+00:00';`

## Checklist wdrożenia

- [ ] `DB_TIMEZONE=+00:00` w `.env` pnedu i pneadm (prod + dev)
- [ ] `config:clear` na obu serwisach
- [ ] Test: nowe zamówienie z pnedu → UI = zegarek PL
- [ ] `form-orders:normalize-order-dates --dry-run` → akceptacja → korekta prod
- [ ] Usunąć / zdeprecjonować sprzeczne `pnedu/TIMEZONE-FIX.md` (+02:00)
