# ⚡ Szybki przewodnik wdrożenia Path Repository na produkcję

## 🎯 Cel

Zmiana pakietu `pne-certificate-generator` z GitHub (VCS) na Path Repository na serwerze seohost.pl.

## 📋 Szybkie kroki

### 1. Przygotuj pakiet na serwerze

```bash
# Zaloguj się na serwer
ssh user@seohost.pl

# Utwórz katalog i sklonuj pakiet
mkdir -p /var/www/shared-packages
cd /var/www/shared-packages
git clone git@github.com:hostnetwg/pne-certificate-generator.git

# Ustaw uprawnienia
chmod -R 775 /var/www/shared-packages/pne-certificate-generator
chown -R www-data:www-data /var/www/shared-packages/pne-certificate-generator
```

### 2. Zmień composer.json w obu projektach

**W `adm.pnedu.pl`:**
```bash
cd /var/www/adm.pnedu.pl
nano composer.json
```

**Zmień:**
```json
"repositories": [
    {
        "type": "path",
        "url": "/var/www/shared-packages/pne-certificate-generator"
    }
]
```

**W `pnedu.pl`:** (analogicznie)

### 3. Zaktualizuj pakiet

```bash
# W obu projektach
composer update pne/certificate-generator --no-interaction
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### 4. Przetestuj

1. Zaloguj się do `adm.pnedu.pl`
2. Przejdź do edytora szablonów
3. Wgraj nowe tło/logo
4. Sprawdź czy się zapisało:
   ```bash
   ls -la /var/www/shared-packages/pne-certificate-generator/storage/certificates/
   ```

## 🔄 Alternatywnie: Użyj skryptu

```bash
# Na serwerze, w katalogu projektu
cd /var/www/adm.pnedu.pl
./switch-to-path-repository.sh

# W drugim projekcie
cd /var/www/pnedu.pl
./switch-to-path-repository.sh
```

## ✅ Po wdrożeniu

- ✅ Grafiki zapisują się w `/var/www/shared-packages/pne-certificate-generator/storage/`
- ✅ Pliki Blade zapisują się w `/var/www/shared-packages/pne-certificate-generator/resources/views/`
- ✅ Oba projekty używają tego samego pakietu
- ✅ Wszystko działa jak na dev

## 📖 Pełna dokumentacja

Zobacz: `PRODUCTION_DEPLOYMENT_PATH_REPOSITORY.md`


