# 🚀 Instrukcja wdrożenia na produkcję

## Problem
Błąd: `Source path "../pne-certificate-generator" is not found for package pne/certificate-generator`

## Rozwiązanie

### Krok 1: Zmień konfigurację repository w composer.json

Na produkcji wykonaj:

```bash
cd /ścieżka/do/adm.pnedu.pl/public_html/pneadm-bootstrap

# Edytuj composer.json
nano composer.json
```

Znajdź sekcję `repositories` (około linii 67-72) i zmień:

**Z:**
```json
"repositories": [
    {
        "type": "path",
        "url": "../pne-certificate-generator"
    }
]
```

**Na (użyj HTTPS, jeśli nie masz SSH):**
```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/hostnetwg/pne-certificate-generator.git"
    }
]
```

Zapisz: `Ctrl+O`, `Enter`, `Ctrl+X`

### Krok 2: Zaktualizuj pakiet

```bash
composer update pne/certificate-generator --no-dev --optimize-autoloader
```

### Krok 3: Wyczyść cache Laravel

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

### Krok 4: Zoptymalizuj (opcjonalnie)

```bash
php artisan optimize
```

## ✅ Szybkie rozwiązanie (jedna komenda)

Jeśli masz dostęp do edycji plików, możesz użyć:

```bash
cd /ścieżka/do/adm.pnedu.pl/public_html/pneadm-bootstrap

# Zmień path na vcs w composer.json
sed -i 's|"type": "path"|"type": "vcs"|' composer.json
sed -i 's|"../pne-certificate-generator"|"https://github.com/hostnetwg/pne-certificate-generator.git"|' composer.json

# Zaktualizuj pakiet
composer update pne/certificate-generator --no-dev --optimize-autoloader

# Wyczyść cache
php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
```

## 📝 Uwaga

- Na produkcji używamy **VCS repository** (GitHub)
- Na środowisku developerskim pozostaw **path repository** (działa przez Docker volume)
- Pakiet `pne/certificate-generator` jest jeszcze używany w starym `CertificateController` (do czasu pełnej migracji)

