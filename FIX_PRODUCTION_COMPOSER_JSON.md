# Naprawa błędu composer.json na produkcji

## Problem
Plik `composer.json` ma konflikt merge i nieprawidłową składnię JSON (błąd na linii 69).

## Rozwiązanie - wykonaj na produkcji

### Krok 1: Sprawdź status git
```bash
cd /path/to/pneadm-bootstrap
git status
```

### Krok 2: Przywróć composer.json z repozytorium (zalecane)
```bash
# Odrzuć lokalne zmiany w composer.json i użyj wersji z repozytorium
git checkout --theirs composer.json
git add composer.json
git commit -m "Rozwiązano konflikt w composer.json"
```

### Alternatywa: Przywróć z origin/main
```bash
# Pobierz najnowszą wersję z repozytorium
git fetch origin
git checkout origin/main -- composer.json
git add composer.json
git commit -m "Przywrócono composer.json z repozytorium"
```

### Krok 3: Rozwiąż konflikt w composer.lock (jeśli występuje)
```bash
# Usuń composer.lock i wygeneruj nowy
rm composer.lock
composer install --no-dev --optimize-autoloader
```

### Krok 4: Wyczyść cache i zoptymalizuj
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

## Jeśli nadal masz problemy

### Pełna naprawa:
```bash
cd /path/to/pneadm-bootstrap

# 1. Przywróć composer.json z repozytorium
git checkout origin/main -- composer.json
git add composer.json

# 2. Usuń composer.lock i vendor (jeśli potrzeba)
rm -f composer.lock
rm -rf vendor

# 3. Zainstaluj zależności na nowo
composer install --no-dev --optimize-autoloader

# 4. Wyczyść wszystko
php artisan optimize:clear

# 5. Zoptymalizuj
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Sprawdzenie poprawności composer.json

Po naprawie sprawdź czy plik jest poprawny:
```bash
# Sprawdź składnię JSON
php -r "json_decode(file_get_contents('composer.json')); echo 'JSON is valid';"

# Sprawdź czy Composer może go odczytać
composer validate
```

## Poprawna struktura repositories w composer.json

Sekcja `repositories` powinna wyglądać tak:
```json
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:hostnetwg/pne-certificate-generator.git"
    }
],
```

**UWAGA:** Po `}` nie powinno być przecinka przed `]` - to jest częsty błąd powodujący problemy.






