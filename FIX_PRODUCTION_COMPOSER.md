# 🔧 Naprawa błędu: "Source path /var/www/pne-certificate-generator is not found"

## Problem

Błąd występuje, ponieważ w `composer.json` pakiet `pne/certificate-generator` jest skonfigurowany jako **path repository**, który wskazuje na lokalny katalog `/var/www/pne-certificate-generator`. Ten katalog nie istnieje na produkcji.

**Na środowisku developerskim (Docker):**
- Pakiet jest w sąsiednim katalogu `../pne-certificate-generator`
- Docker montuje go jako volume do `/var/www/pne-certificate-generator`
- Działa przez path repository

**Na produkcji:**
- Pakiet nie jest w lokalnym katalogu
- Pakiet powinien być pobierany z GitHub jako VCS repository

## ✅ Rozwiązanie na produkcji

### Krok 1: Edytuj `composer.json` na produkcji

```bash
cd /ścieżka/do/adm.pnedu.pl/public_html/pneadm-bootstrap
nano composer.json
```

### Krok 2: Znajdź sekcję `repositories` (około linii 67-72)

**Zmień z:**
```json
"repositories": [
    {
        "type": "path",
        "url": "/var/www/pne-certificate-generator"
    }
]
```

**Na:**
```json
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:hostnetwg/pne-certificate-generator.git"
    }
]
```

Zapisz plik: `Ctrl+O`, `Enter`, `Ctrl+X`

### Krok 3: Zaktualizuj pakiet przez Composer

```bash
composer update pne/certificate-generator --no-dev --optimize-autoloader
```

**Jeśli wystąpi błąd z dostępem do GitHub:**

Sprawdź, czy masz skonfigurowany klucz SSH:

```bash
# Sprawdź czy klucz SSH istnieje
ls -la ~/.ssh/id_rsa.pub

# Jeśli nie ma, wygeneruj (lub użyj istniejącego):
ssh-keygen -t rsa -b 4096 -C "your-email@example.com"

# Wyświetl klucz publiczny i dodaj do GitHub:
cat ~/.ssh/id_rsa.pub
# Wklej do: GitHub → Settings → SSH and GPG keys → New SSH key
```

**Alternatywnie, jeśli nie możesz użyć SSH, użyj HTTPS:**

Zmień w `composer.json`:
```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/hostnetwg/pne-certificate-generator.git"
    }
]
```

### Krok 4: Wyczyść cache Laravel

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

### Krok 5: Sprawdź czy pakiet jest zainstalowany

```bash
ls -la vendor/pne/certificate-generator/
# Powinny być widoczne: src/, resources/, composer.json, etc.
```

## ✅ Wszystko razem (quick fix)

```bash
# 1. Przejdź do katalogu projektu
cd /ścieżka/do/adm.pnedu.pl/public_html/pneadm-bootstrap

# 2. Edytuj composer.json (zmień path na vcs)
sed -i 's|"type": "path"|"type": "vcs"|' composer.json
sed -i 's|"/var/www/pne-certificate-generator"|"git@github.com:hostnetwg/pne-certificate-generator.git"|' composer.json

# 3. Zaktualizuj pakiet
composer update pne/certificate-generator --no-dev --optimize-autoloader

# 4. Wyczyść cache
php artisan config:clear
php artisan cache:clear
```

## 📝 Notatka

Ta zmiana dotyczy **tylko produkcji**. Na środowisku developerskim (Docker) pozostaw konfigurację `path`, ponieważ działa przez Docker volume.

Jeśli w przyszłości chcesz zsynchronizować konfigurację, możesz rozważyć:
- Używanie zmiennych środowiskowych
- Lub zawsze używać VCS (GitHub) i edytować pakiet bezpośrednio w `vendor/` (zmiany będą nadpisywane przy `composer update`)


