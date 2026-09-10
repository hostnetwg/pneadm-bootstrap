# Deploy: Import CSV + e-mail o przeniesieniu kursu online na pnedu.pl

Data: 2026-09-10  
Projekty: **`pneadm`** (migracje w bazie `pneadm`) oraz **`pnedu`** (tylko `fillable` modelu — bez migracji).

Ten pull obejmuje też import CSV Publigo, wyszukiwarkę/filtry listy dostępów i żywy pasek postępu wysyłki zbiorczej.

## Cel

Z listy dostępów kursu online (`/online-courses/{id}/enrollments`):

- import CSV z eksportu nowoczesna-edukacja.pl / Publigo,
- wyszukiwarka, filtry, sortowanie,
- e-mail (pojedynczo i zbiorczo): dostęp jest już na pnedu.pl,
- podczas wysyłki zbiorczej — pasek postępu (batch) i możliwość przerwania.

## Migracje (`pneadm`)

```text
2026_09_10_113500_add_phone_and_legacy_publigo_user_id_to_online_course_enrollments_table.php
2026_09_10_140000_create_online_course_enrollment_email_logs_table.php
```

## Prod (SeoHost)

PHP: `/opt/alt/php82/usr/bin/php` — **nigdy `sail`**.

```bash
# 1) Panel
cd ~/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart

# 2) Front (fillable phone + legacy_publigo_user_id; bez migracji)
cd ~/domains/pnedu.pl/app
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
```

Kolejka na `pneadm` musi działać (cron `queue:work` + flock) — zbiorcza wysyłka idzie w tle. Pojedyncza jest synchroniczna.

Kanon ścieżek: [`PRODUCTION_PATHS.md`](./PRODUCTION_PATHS.md), kolejka: [`PRODUCTION_QUEUE_OPS.md`](./PRODUCTION_QUEUE_OPS.md).

## Po deployu (smoke)

1. Otwórz `/online-courses/{id}/enrollments`.
2. Import CSV — mały plik testowy; telefon, ID Publigo, „Bez limitu”.
3. Filtry / wyszukiwarka.
4. Wyślij **jeden** mail testowy (skrzynka testowa) — przycisk „Wyślij e-mail”. Sprawdź kolumnę daty i kartę „Wysłano X/Y”.
5. Wysyłka zbiorcza na małej grupie: pasek postępu, po zakończeniu odświeżenie listy.
6. Nie wysyłaj od razu hurtowo do całej listy produkcyjnej bez przeglądu treści i filtra „ważny dostęp”.

## Rollback

```bash
cd ~/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan migrate:rollback --step=1
```

Rollback o 1 krok usuwa tabelę logów e-mail. Drugi krok usuwa kolumny `phone` / `legacy_publigo_user_id` — tylko jeśli na produkcji nie ma już zaimportowanych danych, których chcesz zachować.
