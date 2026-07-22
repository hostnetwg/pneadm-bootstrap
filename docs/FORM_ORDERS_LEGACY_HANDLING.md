# Legacy — operacyjne domknięcie starych zamówień

## Lista `/form-orders` (kolejka dzienna)

- **Domyślny widok** (bez `?quick=`): jak przycisk **Do obsługi (aktywne)** (`needsActiveHandling`).
- **Wszystkie zamówienia:** `?quick=all` (przycisk „Wszystkie”).
- **Liczniki** (badge przy filtrach + pasek „Wszystkie zamówienia: … | Wartość sprzedaży…”): ładowane **po liście** przez `GET /form-orders/index-stats` (AJAX, bez przeładowania). Zmiana filtrów / wyszukiwanie nadal przeładowuje stronę i najpierw pokazuje listę.
- Cache: badge stats 30 s, grupy duplikatów 60 s (jak wcześniej).

### Pobierz z GUS (create / edit)

Na `/form-orders/create` i `/form-orders/{id}/edit` przy NIP nabywcy i odbiorcy jest przycisk **Pobierz z GUS** (`POST /form-orders/gus-lookup-by-nip`). Uzupełnia nazwę, kod pocztowy, miasto, adres i NIP (jak formularz zamówienia na pnedu.pl). Wymaga `GUS_BIR_USER_KEY` w `.env` (ten sam klucz co pnedu).

**Mapowanie adresu (`GusBirService`):**
- gdy GUS zwraca ulicę → pole **Adres** = `Ulica` + `NrNieruchomosci` (+ `/NrLokalu`);
- gdy brak ulicy (mała miejscowość) → pole **Adres** = `Miejscowosc` + `NrNieruchomosci` (+ `/NrLokalu`), np. `Węgój 7`;
- pole **Miasto** = `MiejscowoscPoczty` (albo `Miejscowosc`, gdy poczta pusta).

---

## Kontekst

Po migracji z Publigo / importu CSV część zamówień ma **FV**, ale:
- brak `product_id` (nie da się dodać uczestnika w PNEADM), albo
- szkolenie **po terminie** i uczestnik nigdy nie trafił do `participants`.

Te rekordy nie powinny zaśmiecać codziennej kolejki „Do obsługi”.

## Pola w `form_orders`

| Pole | Znaczenie |
|------|-----------|
| `legacy_handled_at` | Kiedy operacyjnie zamknięto (nie usuwa danych) |
| `legacy_handled_reason` | Powód audytowy |
| `legacy_handled_by` | ID użytkownika (komenda ustawia `NULL`) |

## Komenda

```bash
# Dev — najpierw ZAWSZE dry-run
sail artisan form-orders:close-legacy-handled --dry-run

# Po akceptacji liczb — zapis (dev / staging / prod)
sail artisan form-orders:close-legacy-handled

# Tylko jedna grupa
sail artisan form-orders:close-legacy-handled --group=unlinked --dry-run
sail artisan form-orders:close-legacy-handled --group=archival --dry-run
```

### Grupy

1. **unlinked** — FV + brak rozpoznania kursu (`product_id` / legacy Publigo).
2. **archival** — FV + kurs po `end_date` + uczestnik zamówienia bez wpisu w `participants`.

## UI

- **Do obsługi (aktywne)** — szybki filtr / licznik: tylko trwające szkolenia (`needsActiveHandling`).
- **Backlog legacy** — filtr `handling_all` / licznik do czasu uruchomienia komendy.

## Wdrożenie na produkcję

Szczegółowa checklista: [FORM_ORDERS_LEGACY_PRODUCTION.md](./FORM_ORDERS_LEGACY_PRODUCTION.md)

Skrót:

1. Wgraj kod + `php artisan migrate --force`
2. **CSV przed close:** `export-handling-csv --scope=legacy_both` i `--scope=handling`
3. `php artisan form-orders:close-legacy-handled --dry-run` — porównaj liczby z dev (~2525 + ~431)
4. Po akceptacji: `php artisan form-orders:close-legacy-handled`
5. **CSV po close:** `export-handling-csv --scope=handling` (~40–50 wierszy)
6. Sprawdź licznik „Do obsługi (aktywne)” w UI

## Analiza (dev)

Skrypt pomocniczy: `sail php scripts/analyze-needs-handling.php`

## Raport CSV

```bash
# Kolejka do obsługi (domyślnie)
sail artisan form-orders:export-handling-csv --scope=handling

# Aktywne szkolenia
sail artisan form-orders:export-handling-csv --scope=handling_active

# Podgląd grup legacy przed close
sail artisan form-orders:export-handling-csv --scope=legacy_unlinked
sail artisan form-orders:export-handling-csv --scope=legacy_archival
sail artisan form-orders:export-handling-csv --scope=legacy_both

# Własna ścieżka
sail artisan form-orders:export-handling-csv --scope=handling --output=/tmp/handling.csv
```

Pełna checklista produkcyjna: [FORM_ORDERS_LEGACY_PRODUCTION.md](./FORM_ORDERS_LEGACY_PRODUCTION.md)
