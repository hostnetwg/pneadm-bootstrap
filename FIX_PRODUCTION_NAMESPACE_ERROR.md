# Naprawa błędu "Unable to detect application namespace" na produkcji

## Problem
Błąd występuje po `git pull` na produkcji, gdy cache Laravel lub autoloader Composer są nieaktualne.

## Rozwiązanie

Wykonaj następujące komendy na serwerze produkcyjnym w katalogu projektu:

```bash
cd /path/to/pneadm-bootstrap

# 1. Najpierw rozwiąż konflikty git (jeśli występują)
git stash
git pull
git stash pop  # Jeśli chcesz zachować lokalne zmiany

# 2. Zaktualizuj autoloader Composer
composer dump-autoload --optimize

# 3. Wyczyść wszystkie cache Laravel
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 4. Zoptymalizuj aplikację dla produkcji
php artisan optimize

# 5. Upewnij się, że uprawnienia są poprawne
chmod -R 775 storage bootstrap/cache
```

## Alternatywne rozwiązanie (jeśli powyższe nie pomaga)

Jeśli problem nadal występuje, wykonaj pełną optymalizację:

```bash
cd /path/to/pneadm-bootstrap

# Wyczyść wszystko
php artisan optimize:clear

# Zaktualizuj autoloader
composer dump-autoload --optimize --classmap-authoritative

# Zoptymalizuj ponownie
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Sprawdzenie

Po wykonaniu komend sprawdź czy aplikacja działa:
- Odwiedź `adm.pnedu.pl/accounting/reports`
- Sprawdź logi: `tail -f storage/logs/laravel.log`

## Uwagi

- Na produkcji zawsze wykonuj `composer dump-autoload` i `php artisan optimize` po `git pull`
- Upewnij się, że `APP_ENV=production` w pliku `.env`
- Sprawdź czy `composer.json` i `composer.lock` są zsynchronizowane z repozytorium



