# Polityka stref czasowych — pneadm + pnedu

Jeden dokument kanoniczny dla obu serwisów. Ostatnia aktualizacja: 2026-10-07.

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

## Ceny kursów nagranych (`product_prices`)

`promotion_starts_at`, `promotion_ends_at`, `access_starts_at`, `access_expires_at` oraz historia Omnibus (`price_offer_histories.effective_from`, `effective_to`, `excluded_at`) to momenty w **UTC** (kolumna `TIMESTAMP`, sesja `+00:00`). Długość dostępu (dni, miesiące, lata) nie jest godziną — liczy się od nadania albo od daty startu.

W formularzu ADM administrator wpisuje **czas polski**. Zapis: `Europe/Warsaw` → UTC. Odczyt: cast `App\Casts\UtcImmutableDatetime` (to samo w `pnedu`), potem wyświetlenie w `Europe/Warsaw`.

Nie czytać tych kolumn castem `datetime` / `immutable_datetime` — Laravel zinterpretuje godzinę UTC jako czas aplikacji i przesunie ją o 1–2 h. Zakres `TIMESTAMP` kończy się 19.01.2038; rok spoza zakresu (np. literówka 2926) wraca jako błąd formularza, nie jako 500.

## Analityka (`pne_analytics`)

`occurred_at` — ten sam kontrakt co `order_date`: **UTC w bazie**, **Europe/Warsaw** w UI.

### Zapis (pnedu / pneadm — `AnalyticsService`)

```php
'occurred_at' => now('UTC')->toDateTimeString();
```

Nie używać `now()->toDateTimeString()` — przy `DB_TIMEZONE=+00:00` zapisze czas polski jak UTC (+2 h w debug panelu).

### Wyświetlanie (adm — debug eventów)

```php
$event->formatUtcDatetimeLocal('occurred_at');
```

Metoda na `AnalyticsModel` — ta sama logika co `FormOrder::formatOrderDateLocal()`.

### Po wdrożeniu na prod

```bash
# pnedu — joby analityki muszą wczytać nowy kod
php artisan queue:restart
```

### Eventy zapisane między wdrożeniem DB_TIMEZONE a fixem AnalyticsService

Krótkie okno: `occurred_at` może mieć +2 h w bazie. Po fixie zapisu nowe eventy są OK. Korekta opcjonalna (SQL, tylko po audycie):

```sql
-- Tylko jeśli eventy z tego okna mają +2 h względem rzeczywistości
UPDATE analytics_events
SET occurred_at = DATE_SUB(occurred_at, INTERVAL 2 HOUR)
WHERE occurred_at >= '2026-07-04 18:00:00'
  AND occurred_at < '2026-07-04 22:00:00';
```

Dostosuj zakres dat do swojego wdrożenia.

## Eksport / import MySQL

Patrz: [PHPMYADMIN_EXPORT_IMPORT_TIMEZONE.md](../PHPMYADMIN_EXPORT_IMPORT_TIMEZONE.md)

Przed eksportem/importem: `SET time_zone = '+00:00';`

## Checklist wdrożenia

- [ ] `DB_TIMEZONE=+00:00` w `.env` pnedu i pneadm (prod + dev)
- [ ] `config:clear` na obu serwisach
- [ ] Test: nowe zamówienie z pnedu → UI = zegarek PL
- [ ] `form-orders:normalize-order-dates --dry-run` → akceptacja → korekta prod
- [ ] Usunąć / zdeprecjonować sprzeczne `pnedu/TIMEZONE-FIX.md` (+02:00)
