# PNE Growth OS — deploy Etapu 0.1

Data: 2026-09-29
Status: runbook przygotowany; wdrożenie produkcyjne nie zostało wykonane

## Zakres

Etap 0.1 dodaje wyłącznie:

- fail-closed feature flag,
- trasę `/growth`,
- dostęp dla `super_admin`,
- warunkową pozycję menu,
- demonstracyjny pulpit read-only.

Nie ma migracji, zmian danych, kolejek, cronów, integracji, AI ani zmian w `pnedu.pl`.

## Backup

Zmiana nie dotyka bazy danych. Standardowy backup wdrożeniowy pozostaje zalecany, ale nie ma backfillu ani migracji do odtworzenia.

## Konfiguracja

Przed pierwszym deployem dodać do `.env` produkcji ADM:

```text
PNE_GROWTH_OS_ENABLED=false
```

Brak zmiennej, `false` albo wartość nieprawidłowa wyłącza moduł.

## Deploy ADM

Na produkcji nie używać Sail.

```bash
cd ~/domains/adm.pnedu.pl/pneadm
git pull origin main

/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
```

Nie uruchamiać migracji dla tego etapu — nie istnieją.

## Smoke przy fladze wyłączonej

1. Zalogować się jako `super_admin`.
2. Potwierdzić brak pozycji „PNE Growth OS” w menu.
3. Wejść ręcznie na `/growth` i potwierdzić HTTP 404.
4. Sprawdzić dashboard, szkolenia i zamówienia bez zmian.

## Opcjonalne kontrolowane włączenie

Tylko po decyzji Waldemara:

```text
PNE_GROWTH_OS_ENABLED=true
```

Następnie:

```bash
cd ~/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan config:cache
```

Smoke:

1. `super_admin` widzi pozycję menu i otwiera `/growth`.
2. Zwykły `admin` nie widzi pozycji menu i otrzymuje HTTP 403 przy ręcznym URL.
3. Ekran pokazuje oznaczenie wersji przygotowawczej i dane demonstracyjne.
4. Nie są wykonywane żadne zapisy ani wywołania zewnętrzne.

## Rollback

Najszybszy kill switch:

```text
PNE_GROWTH_OS_ENABLED=false
```

Potem:

```bash
cd ~/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan config:cache
```

Pełny rollback kodu jest możliwy standardową procedurą Git. Nie usuwać danych — Etap 0.1 żadnych nie tworzy.

## Weryfikacja techniczna

Lokalnie:

```bash
sail artisan test tests/Feature/GrowthOS/GrowthOsAccessTest.php
sail pint config/growth_os.php app/Http/Middleware/GrowthOS/EnsureGrowthOsAccess.php app/Http/Controllers/GrowthOS/DashboardController.php tests/Feature/GrowthOS/GrowthOsAccessTest.php
```
