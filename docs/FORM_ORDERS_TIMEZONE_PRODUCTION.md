# Wdrożenie produkcyjne — korekta dat zamówień (order_date)

Checklist dla **pneadm + pnedu** na produkcji. Naprawia zamówienia z formularza **pnedu.pl** zapisane z błędem **−2 h** (sesja MySQL `+02:00` na wspólnej bazie `pneadm`).

Pełna polityka: [TIMEZONE_POLICY.md](./TIMEZONE_POLICY.md)

## Co korygujemy (i czego nie)

| Kohorta | Liczba (dev, orientacyjnie) | Akcja |
|---------|----------------------------|--------|
| `submission_source = pnedu_order_form` od 2025-10-18 | ~758 | **+2 h UTC** — ta procedura |
| `submission_source IS NULL` (dawny import z certgen) | ~4792 | **Poza zakresem** — `form_orders` jest źródłem prawdy; certgen nie jest używany operacyjnie |
| `pneadm_manual` | ~9 | Bez korekty |

## 1. Wgraj kod (oba serwisy)

### pneadm (adm.pnedu.pl)

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

### pnedu (pnedu.pl)

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

## 2. Konfiguracja `.env` (oba serwisy — WYMAGANE)

```env
APP_TIMEZONE=Europe/Warsaw
DB_TIMEZONE=+00:00
```

```bash
php artisan config:clear
php artisan cache:clear
# opcjonalnie: restart PHP-FPM / queue workers
```

## 3. Test nowego zamówienia (przed masową korektą)

1. Złóż zamówienie testowe na pnedu.pl.
2. Porównaj godzinę na podsumowaniu i w adm `/form-orders/{id}` — musi = czas polski z zegarka.
3. W debug eventów analityki czas zdarzenia powinien być spójny (±1 min).

## 4. Raport CSV **przed** korektą (audyt)

Na serwerze **pneadm** — **po wdrożeniu kodu i `.env`**, tuż przed masową korektą:

```bash
# BEFORE = moment wdrożenia fixu w UTC (NIE wklejaj dosłownie „YYYY-MM-DD” — to placeholder!)
# Przykład: wdrożenie 4 lipca 2026 ok. 21:11 czasu polskiego → UTC 19:11:
php artisan form-orders:normalize-order-dates \
  --scope=pnedu_bug \
  --since=2025-10-18 \
  --before="2026-07-04 19:11:03" \
  --dry-run \
  --export-csv
```

Dostosuj `--before` do rzeczywistej godziny wdrożenia na prod (UTC). Zamówienia złożone **po** wdrożeniu fixu mają poprawny `order_date` i nie wchodzą w korektę.

Ręcznie poprawione wcześniej (np. test 7423 na dev): `--exclude-ids=7423`.

Plik trafia do `storage/app/exports/form_orders_order_date_pnedu_bug_YYYY-MM-DD_HHMMSS.csv` (UTF-8 BOM, separator `,`).

Kolumny: `id`, `ident`, `order_date_utc_before`, `display_pl_before`, `order_date_utc_after`, `display_pl_after`, …

**Oczekiwana liczba rekordów:** ~750–760 (porównaj z dev). Po wdrożeniu fixu kodu **nowe** zamówienia nie trafiają do tej kohorty.

Przejrzyj w CSV kilka znanych zamówień (daty muszą przesunąć się o +2 h w kolumnie `display_pl_after`).

## 5. Dry-run (podsumowanie w konsoli)

```bash
php artisan form-orders:normalize-order-dates \
  --scope=pnedu_bug \
  --since=2025-10-18 \
  --dry-run
```

## 6. Korekta (zapis)

**Po akceptacji CSV:**

```bash
php artisan form-orders:normalize-order-dates \
  --scope=pnedu_bug \
  --since=2025-10-18 \
  --before="2026-07-04 19:00:00" \
  --force
```

Bez `--force` komenda zapyta o potwierdzenie interaktywnie.

## 7. Weryfikacja po korekcie

**Nie uruchamiaj ponownie `--force` z tymi samymi parametrami** — skorygowałby zamówienia drugi raz (−/+
2 h za dużo).

Sprawdź ręcznie kilka zamówień (adm + pnedu summary) — daty powinny być spójne i sensowne.

Opcjonalnie w tinkerze (pneadm):

```bash
sail artisan tinker
$o = \App\Models\FormOrder::find(7415);
$o->formatOrderDateLocal();  // oczekiwany czas polski po korekcie
```

Dry-run z tym samym `--before` nadal pokaże rekordy z kohorty (to filtr dat, nie „pozostałe do poprawy”).

## 8. Rollback (ostrożnie)

Tylko z backupem bazy i akceptacją biznesową:

```sql
-- Cofnięcie korekty +2h dla kohorty pnedu_order_form
UPDATE form_orders
SET order_date = DATE_SUB(order_date, INTERVAL 2 HOUR)
WHERE submission_source = 'pnedu_order_form'
  AND order_date >= '2025-10-18';
```

Preferowany rollback: przywrócenie kopii bazy sprzed kroku 6.

## Powiązane

- [TIMEZONE_POLICY.md](./TIMEZONE_POLICY.md)
- [PHPMYADMIN_EXPORT_IMPORT_TIMEZONE.md](../PHPMYADMIN_EXPORT_IMPORT_TIMEZONE.md)
- [FORM_ORDERS_LEGACY_PRODUCTION.md](./FORM_ORDERS_LEGACY_PRODUCTION.md)
