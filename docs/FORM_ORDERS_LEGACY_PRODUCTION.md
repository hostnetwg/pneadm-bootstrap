# Wdrożenie produkcyjne — operacyjne zamówienia + legacy close

Checklist dla **pneadm** na produkcji. Dev został już zweryfikowany (domknięto ~2956 legacy, kolejka ~43).

## 1. Wgraj kod

Na serwerze produkcyjnym (w katalogu aplikacji pneadm):

```bash
git pull origin main   # lub właściwa gałąź release
composer install --no-dev --optimize-autoloader
npm ci && npm run build   # jeśli zmiany frontendu
php artisan optimize:clear
```

> Na prod zwykle **bez** prefiksu `sail` — poniżej `php artisan`. Dostosuj do swojego deployu.

## 2. Migracje

```bash
php artisan migrate --force
```

Wymagane m.in.:

- `2026_07_03_000001` — anulowanie (`cancelled_*`)
- `2026_07_03_000002` — `participant_id` na FOP
- `2026_07_03_000003` — bezpłatny dostęp bez FV (`invoice_exempt_*`)
- `2026_07_03_000004` — legacy close (`legacy_handled_*`)

## 3. Raport CSV **przed** domknięciem (audyt)

```bash
# Pełna kolejka „Do obsługi” (stan przed close)
php artisan form-orders:export-handling-csv --scope=handling

# Rekordy, które zostaną domknięte — dwie grupy osobno
php artisan form-orders:export-handling-csv --scope=legacy_unlinked
php artisan form-orders:export-handling-csv --scope=legacy_archival

# Lub obie grupy w jednym pliku
php artisan form-orders:export-handling-csv --scope=legacy_both
```

Pliki trafiają do `storage/app/exports/` (UTF-8 BOM, separator `,`).

**Oczekiwane liczby (porównaj z dev):**

| Scope | Dev (referencja) |
|-------|------------------|
| `legacy_unlinked` | ~2525 |
| `legacy_archival` | ~431 |
| `legacy_both` | ~2956 |

## 4. Dry-run domknięcia legacy

```bash
php artisan form-orders:close-legacy-handled --dry-run
```

Porównaj liczby z CSV i z dev. **Nie kontynuuj**, jeśli rozjazd > kilku rekordów bez wyjaśnienia.

## 5. Domknięcie legacy (zapis)

```bash
php artisan form-orders:close-legacy-handled
```

Potwierdzenie w output: ~2956 zamkniętych (unlinked + archival).

## 6. Raport CSV **po** domknięciu

```bash
# Pozostała realna kolejka operacyjna
php artisan form-orders:export-handling-csv --scope=handling

# Tylko aktywne szkolenia (szybki filtr UI)
php artisan form-orders:export-handling-csv --scope=handling_active
```

**Oczekiwane:** `handling` ~40–50, `handling_active` niewiele (zależnie od bieżących szkoleń).

## 7. Weryfikacja UI

1. `/form-orders` — licznik **„Do obsługi (aktywne)”** — nie tysiące.
2. Link **„Backlog legacy”** (`handling_all`) — po close powinien być bliski `handling`.
3. `/courses` — kolumna **U** — sensowne liczby przy trwających szkoleniach.

## 8. Rollback (ostrożnie)

Pola `legacy_handled_*` można cofnąć SQL-em **tylko** jeśli masz backup i wiesz, co robisz:

```sql
-- NIE uruchamiaj bez backupu i akceptacji biznesowej
UPDATE form_orders SET legacy_handled_at = NULL, legacy_handled_reason = NULL, legacy_handled_by = NULL
WHERE legacy_handled_at IS NOT NULL AND legacy_handled_by IS NULL;
```

Preferowany rollback: przywrócenie kopii bazy z przed migracji.

## Powiązane

- [FORM_ORDERS_LEGACY_HANDLING.md](./FORM_ORDERS_LEGACY_HANDLING.md) — logika grup i komenda
- [UI_MODALS.md](./UI_MODALS.md) — modale Bootstrap w panelu
