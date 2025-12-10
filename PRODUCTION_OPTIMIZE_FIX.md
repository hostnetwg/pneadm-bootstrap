# 🔧 Naprawa błędu "Cannot redeclare package_certificate_file_path()"

## Problem
Błąd występuje podczas `php artisan optimize`:
```
Cannot redeclare Pne\CertificateGenerator\package_certificate_file_path()
```

## Przyczyna
Cache Laravel może powodować wielokrotne ładowanie ServiceProvider, co prowadzi do próby ponownej deklaracji funkcji.

## ✅ Rozwiązanie

### Krok 1: Wyczyść wszystkie cache PRZED optimize

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

### Krok 2: Teraz możesz uruchomić optimize

```bash
php artisan optimize
```

### Krok 3: Jeśli nadal występuje błąd, usuń ręcznie cache

```bash
# Usuń cache bootstrap
rm -rf bootstrap/cache/*.php

# Usuń cache config
rm -rf storage/framework/cache/data/*

# Następnie wyczyść przez artisan
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear

# I spróbuj ponownie
php artisan optimize
```

## 📝 Alternatywa: Pomiń optimize

Jeśli problem nadal występuje, możesz pominąć `php artisan optimize` - aplikacja będzie działać, tylko trochę wolniej (cache nie będzie zoptymalizowany).

## 🔍 Diagnostyka

Sprawdź, czy ServiceProvider nie jest rejestrowany wielokrotnie:

```bash
# Sprawdź config/app.php - czy pakiet nie jest dodany ręcznie?
grep -r "CertificateGeneratorServiceProvider" config/

# Sprawdź composer.json - czy pakiet jest poprawnie zainstalowany?
composer show pne/certificate-generator
```

