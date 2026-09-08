# Deploy: minimalny legalny checkout (2026-09)

Zakres obejmuje równoczesne wdrożenie `pneadm` i `pnedu`. Nie wdrażać tylko jednego repozytorium.

Na produkcji **nie używamy Sail**. Ścieżki: [`PRODUCTION_PATHS.md`](./PRODUCTION_PATHS.md).

## Kolejność

```bash
# 1) Panel — migracja jest tylko tutaj
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git status
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate:status
# Oczekiwana nowa migracja:
#   2026_09_08_210000_add_legal_checkout_evidence_to_orders
# Jeśli widać też 2026_09_02_000001_add_registration_closure_to_courses_table
# (wcześniejsza funkcja zamykania zapisów) — uruchomi się razem. Sprawdź listę przed --force.
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache

# 2) Front
cd /home/srv66127/domains/pnedu.pl/app
git status
git pull origin main
# Potwierdź, że produkcja NIE ma ANALYTICS_CONSENT_REQUIRED=false
# (domyślnie zgoda analityczna jest wymagana).
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart
```

`npm run build` **nie jest wymagany** — zmiany checkoutu, cookies i dokumentów są w PHP/Blade.

Migracja jest wyłącznie w `pneadm/database/migrations`, ponieważ obie rozszerzane tabele należą do bazy `pneadm`.

## Smoke po wdrożeniu

1. Otwórz wszystkie cztery płatne formularze i sprawdź końcowy przycisk.
2. Osoba prywatna/JDG: kurs za mniej niż 14 dni — checkbox widoczny i wymagany; kurs późniejszy — niewidoczny.
3. Szkoła/firma: checkbox niewidoczny bez względu na termin.
4. Złóż zamówienie testowe i sprawdź kolumny dowodu w obu tabelach.
5. Sprawdź e-mail: dane zamówienia, Regulamin PDF właściwej wersji, wzór odstąpienia.
6. Sprawdź `/regulamin/2026-09-08`, PDF, `/odstapienie-od-umowy`, `/rodo-art-14`.
7. W nowej sesji sprawdź „Tylko niezbędne”, „Akceptuję analityczne” i ponowne otwarcie ustawień ze stopki.
8. Potwierdź, że powiązane i samodzielne zamówienie online trafia na listę kursową Sendy dopiero po statusie `paid`.
9. Dla uczestnika innego niż zamawiający sprawdź informację art. 14 już w pierwszym potwierdzeniu oraz późniejszym e-mailu dostępowym.

## Wycofanie

Kod można cofnąć standardowym wdrożeniem poprzedniej wersji. Migracji nie cofać na produkcji, jeśli nowe zamówienia zapisały dowody; nullable kolumny są zgodne wstecznie.
