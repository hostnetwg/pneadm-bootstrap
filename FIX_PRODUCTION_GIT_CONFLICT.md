# Naprawa konfliktu Git na produkcji

## Problem
```
error: Your local changes to the following files would be overwritten by merge:
        composer.lock
        composer.json
```

## Rozwiązanie

Na produkcji wykonaj następujące kroki:

### Krok 1: Sprawdź lokalne zmiany
```bash
cd /path/to/pneadm-bootstrap
git diff composer.json
git diff composer.lock
```

### Krok 2: Zastąp lokalne pliki wersją z repozytorium
Ponieważ zmiany (usunięcie pakietu pne-certificate-generator) są już w repozytorium, zastąp lokalne pliki:

```bash
# Zastąp composer.json wersją z repozytorium
git checkout origin/main -- composer.json

# Zastąp composer.lock wersją z repozytorium
git checkout origin/main -- composer.lock
```

### Krok 3: Wykonaj git pull
```bash
git pull
```

### Krok 4: Zaktualizuj zależności Composer
```bash
composer install --no-dev --optimize-autoloader
```

### Krok 5: Wyczyść i zoptymalizuj Laravel
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Alternatywne rozwiązanie (jeśli chcesz zachować lokalne zmiany)

Jeśli chcesz najpierw zobaczyć co się zmieniło lokalnie:

```bash
# Zapisz lokalne zmiany
git stash

# Pobierz zmiany z repozytorium
git pull

# Sprawdź co było w stash (opcjonalnie)
git stash show -p

# Jeśli nie potrzebujesz lokalnych zmian, usuń stash
git stash drop
```

## Uwaga

Jeśli lokalne zmiany były ważne (np. różne wersje pakietów), sprawdź je przed zastąpieniem:
```bash
git diff composer.json
git diff composer.lock
```

Jeśli zmiany są ważne, możesz je scalić ręcznie lub użyć `git merge` zamiast `git pull`.







