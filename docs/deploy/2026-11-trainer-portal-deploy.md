# Deploy: panel trenera

Data: 2026-11-25  
Projekty: **pneadm** i **pnedu**. Migracje tylko w pneadm.

## Cel

Trener na pnedu.pl dostaje panel z zapytaniami o szkolenia zamknięte. Administrator włącza go na karcie instruktora. Sprawa pojawia się u trenera dopiero po **Wyślij**. Termin zapisuje się w ustaleniach. Test oznacza wpis w historii i dopisek DATA TESTOWA, ale zapisuje datę tak samo.

Sam deploy nikogo nie powiadamia. Mail i SMS wychodzą dopiero, gdy administrator wyśle zapytanie albo włączy panel na karcie instruktora.

## Prod (SeoHost)

PHP: `/opt/alt/php82/usr/bin/php`. Ścieżki: [`PRODUCTION_PATHS.md`](./PRODUCTION_PATHS.md).

Najpierw panel, potem front. Front czyta nowe kolumny w bazie pneadm. Gdy kod frontu wejdzie wcześniej, strona zalogowanego trenera zapyta o kolumnę, której jeszcze nie ma.

```bash
cd ~/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart

cd ~/domains/pnedu.pl/app
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart
```

Migracje dopisują kolumny i tabelę powiadomień. Nic nie kasują.

Na produkcji adresy mają być `https://pnedu.pl` i `https://adm.pnedu.pl`. Inaczej link w mailu do trenera albo do opiekuna pójdzie na zły host.

## Po deployu

1. Otwórz kartę instruktora i sprawdź, że są przełączniki panelu i SMS. Nie włączaj ich na prawdziwej karcie, jeśli nie chcesz wysłać maila z dostępem.
2. Na sprawie szkolenia zamkniętego zaznacz **Pokaż w panelu trenera**, dopisz wiadomość i kliknij **Wyślij** tylko na sprawie testowej.
3. **Wyłącz w panelu trenera** chowa zapytanie. Termin zostaje.

## Rollback

W obu katalogach `git checkout` poprzedniego commita i te same komendy cache. `migrate:rollback` nie jest potrzebny: stare strony ignorują nowe kolumny. Nie rób rollbacku migracji, gdy trenerzy mają już zapisane terminy.
