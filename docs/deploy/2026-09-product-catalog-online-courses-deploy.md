# Deploy: sprzedaż nagranych kursów online

Data: 2026-09-12
Projekty: `pneadm` + `pnedu`
Status: lokalnie gotowe; produkcja zablokowana do przeglądu prawnego

## Zakres

- tabele katalogu oraz generycznych pozycji zamówienia,
- zakładka **Sprzedaż** przy `/online-courses/{id}`,
- menu **Kursy**, katalog `/kursy`, oferta i checkout,
- PayU, PayNow i faktura odroczona,
- automatyczny dostęp po `paid`,
- ręczne **Nadaj dostęp** w zamówieniu odroczonym,
- bezpieczne przedłużenia i idempotencja.

Obecna sprzedaż `courses` pozostaje bez zmiany. `form_orders.product_id` nadal oznacza tylko `courses.id`.

## Migracja

Wszystkie w `pneadm/database/migrations/` (baza `pneadm`):

```text
2026_09_11_214500_create_product_catalog_tables.php
2026_09_12_050500_create_product_order_tables.php
2026_09_12_104200_add_presale_and_satisfaction_guarantee.php
2026_09_13_160000_add_show_promotion_countdown_to_product_prices.php
2026_09_13_163000_create_price_offer_histories_table.php
2026_09_13_220000_add_product_legal_snapshot_fields.php
2026_09_13_234500_add_is_complimentary_to_product_prices.php
2026_09_14_090000_add_catalog_sort_order_to_online_courses.php
```

## Lokalnie

```bash
sail artisan migrate
# pneadm
sail artisan optimize:clear
sail artisan view:cache

# pnedu
sail artisan optimize:clear
sail artisan route:cache
sail artisan view:cache
sail artisan queue:restart
```

## Produkcja SeoHost

Na produkcji nie używamy Sail. Najpierw wdrażamy schemat i kod `pneadm`, potem `pnedu`.

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate:status
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
```

```bash
cd /home/srv66127/domains/pnedu.pl/app
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart
```

Sprawdź przed GO:

- `PNEDU_INTERNAL_URL` i wspólny `PNEDU_INTERNAL_API_TOKEN` w obu aplikacjach,
- produkcyjne dane PayU/PayNow i poprawne URL webhooków,
- połączenie `pnedu` → baza `pneadm`,
- worker kolejki dla maili,
- zatwierdzone prawnie zapisy treści cyfrowych.

## Smoke po deployu

1. W ADM skonfiguruj kurs testowy, aktywną publiczną ofertę i wariant `ZW`.
2. Na pnedu sprawdź menu **Kursy**, `/kursy` i stronę oferty; kurs nie może pojawić się na homepage.
3. Złóż zamówienie odroczone dla dwóch uczestników.
4. W ADM sprawdź snapshot ceny × 2, wystawienie faktury i osobną akcję **Nadaj dostęp**.
5. Potwierdź dwa konta/enrollmenty oraz maile; ponów akcję i sprawdź brak ponownego przedłużenia.
6. W sandboxie PayU wykonaj płatność i potwierdź automatyczny dostęp po webhooku.
7. Powtórz dla PayNow.
8. Kup ten sam wariant dla aktywnego dostępu i sprawdź przedłużenie od wygaśnięcia.
9. Sprawdź, że bezterminowy dostęp nie został skrócony.
10. Sprawdź `/sitemap.xml`, meta i canonical katalogu/oferty.

## Rollback

Najbezpieczniejszy rollback po rozpoczęciu sprzedaży to wyłączenie `products.is_active` / `product_offers.is_public` i rollback kodu obu aplikacji. Nie cofaj migracji po utworzeniu zamówień: usunęłaby historię pozycji i fulfillmentu, a `course_id` znów stałoby się wymagane.

## Dokumentacja

- [PRODUCT_COMMERCE.md](../PRODUCT_COMMERCE.md)
- [ONLINE-COURSES.md](../ONLINE-COURSES.md)
- [PRODUCTION_PATHS.md](./PRODUCTION_PATHS.md)
