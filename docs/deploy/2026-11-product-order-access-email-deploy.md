# Deploy: zamówienie kursu nagranego i podgląd maila z dostępem

Data: 2026-11-18  
Projekty: **pneadm** i **pnedu**. Bez migracji.

## Cel

Na zamówieniu FORM da się utworzyć, sklonować i edytować zamówienie kursu online nagranego. **Nadaj dostęp**, **Nadaj dostęp wszystkim** i **Prześlij dostęp ponownie** pokazują podgląd maila. Link „Ustaw hasło” w mailu idzie na adres z `APP_URL` pnedu (na produkcji `https://pnedu.pl`).

## Prod (SeoHost)

PHP: `/opt/alt/php82/usr/bin/php`. Ścieżki: [`PRODUCTION_PATHS.md`](./PRODUCTION_PATHS.md).

```bash
cd ~/domains/pnedu.pl/app
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart

cd ~/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart
```

Najpierw pnedu, potem panel: panel woła nowe trasy podglądu i ponownej wysyłki.

`config:cache` bierze `APP_URL` z `.env`. Na pnedu musi to być `https://pnedu.pl`. Inaczej linki w mailach (i `route()`) pójdą pod zły adres.

## Po deployu

1. Otwórz zamówienie produktowe w ADM.
2. **Prześlij dostęp ponownie** — modal ma pokazać temat i treść, nie zostać na „Przygotowuję podgląd…”.
3. Wyślij jeden mail na skrzynkę testową i sprawdź, że „Ustaw hasło” zaczyna się od `https://pnedu.pl/`.

## Rollback

`git checkout` poprzedniego commita w obu repo i te same komendy cache. Migracji nie ma.
