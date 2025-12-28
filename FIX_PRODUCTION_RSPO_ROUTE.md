# Naprawa błędu RouteNotFoundException dla rspo.search na produkcji

## Problem
Błąd: `Route [rspo.search] not defined` - route jest w kodzie, ale nie jest zarejestrowany w cache Laravel.

## Rozwiązanie - wykonaj na produkcji:

```bash
cd /ścieżka/do/pneadm-bootstrap

# 1. Upewnij się, że masz najnowszy kod (jeśli jeszcze nie zrobiłeś pull):
rm -f resources/views/certificates/default.blade.php \
      resources/views/certificates/landscape.blade.php \
      resources/views/certificates/minimal.blade.php
git pull

# 2. Zaktualizuj autoloader (nowa klasa RSPOController)
composer dump-autoload --optimize --no-dev

# 3. Wyczyść cache routingu
php artisan route:clear

# 4. Zaktualizuj cache routingu
php artisan route:cache

# 5. Wyczyść pozostałe cache (opcjonalnie, ale zalecane)
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 6. Zoptymalizuj dla produkcji
php artisan config:cache
php artisan view:cache
php artisan optimize

# 7. Jeśli używasz queue workers, zrestartuj je
php artisan queue:restart
```

## Szybka wersja (jedna linia):

```bash
cd /ścieżka/do/pneadm-bootstrap && \
composer dump-autoload --optimize --no-dev && \
php artisan route:clear && \
php artisan route:cache && \
php artisan optimize:clear && \
php artisan optimize && \
php artisan config:cache && \
php artisan view:cache && \
php artisan queue:restart
```

## Sprawdzenie:

Po wykonaniu komend sprawdź czy działa:
- Odwiedź `adm.pnedu.pl/dashboard` - menu RSPO powinno działać
- Kliknij w "RSPO" -> "Wyszukaj" - powinno przejść bez błędu

## Uwagi:

- **Ważne:** `composer dump-autoload` jest konieczne, bo dodano nową klasę `RSPOController`
- `php artisan route:clear` i `route:cache` są kluczowe - bez tego route nie będzie widoczny
- Na produkcji zawsze wykonuj te komendy po `git pull` gdy dodano nowe route





