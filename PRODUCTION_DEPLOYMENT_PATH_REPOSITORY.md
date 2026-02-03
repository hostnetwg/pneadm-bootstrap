# 🚀 Wdrożenie opcji B: Path Repository na produkcji

## 📋 Instrukcja wdrożenia krok po kroku

### Krok 1: Przygotuj pakiet na serwerze seohost.pl

#### Opcja A: Sklonuj z GitHub (jeśli masz dostęp SSH)

```bash
# Zaloguj się na serwer
ssh user@seohost.pl

# Utwórz katalog dla wspólnych pakietów
mkdir -p /var/www/shared-packages

# Sklonuj pakiet z GitHub
cd /var/www/shared-packages
git clone git@github.com:hostnetwg/pne-certificate-generator.git

# Sprawdź czy się sklonował
ls -la /var/www/shared-packages/pne-certificate-generator/
```

#### Opcja B: Skopiuj z lokalnego komputera przez SCP

```bash
# Na lokalnym komputerze
scp -r /home/hostnet/WEB-APP/pne-certificate-generator user@seohost.pl:/var/www/shared-packages/

# Na serwerze sprawdź
ssh user@seohost.pl
ls -la /var/www/shared-packages/pne-certificate-generator/
```

### Krok 2: Ustaw uprawnienia na serwerze

```bash
# Zaloguj się na serwer
ssh user@seohost.pl

# Ustaw uprawnienia dla całego pakietu
chmod -R 775 /var/www/shared-packages/pne-certificate-generator
chown -R www-data:www-data /var/www/shared-packages/pne-certificate-generator

# Upewnij się, że storage jest zapisywalny
chmod -R 775 /var/www/shared-packages/pne-certificate-generator/storage
chown -R www-data:www-data /var/www/shared-packages/pne-certificate-generator/storage

# Utwórz katalogi jeśli nie istnieją
mkdir -p /var/www/shared-packages/pne-certificate-generator/storage/certificates/logos
mkdir -p /var/www/shared-packages/pne-certificate-generator/storage/certificates/backgrounds
mkdir -p /var/www/shared-packages/pne-certificate-generator/storage/instructors
chmod -R 775 /var/www/shared-packages/pne-certificate-generator/storage
```

### Krok 3: Zaktualizuj composer.json na produkcji

**W projekcie `adm.pnedu.pl` (pneadm-bootstrap):**

```bash
# Zaloguj się na serwer
ssh user@seohost.pl

# Przejdź do katalogu projektu
cd /var/www/adm.pnedu.pl  # lub właściwa ścieżka

# Edytuj composer.json
nano composer.json
```

**Znajdź sekcję `repositories` i zmień na:**

```json
"repositories": [
    {
        "type": "path",
        "url": "/var/www/shared-packages/pne-certificate-generator"
    }
],
```

**Zapisz:** `Ctrl+O`, `Enter`, `Ctrl+X`

**W projekcie `pnedu.pl` (pnedu):**

```bash
# W tym samym terminalu lub nowym
cd /var/www/pnedu.pl  # lub właściwa ścieżka

# Edytuj composer.json
nano composer.json
```

**Zmień tak samo jak wyżej.**

### Krok 4: Zaktualizuj pakiet w obu projektach

```bash
# W projekcie adm.pnedu.pl
cd /var/www/adm.pnedu.pl
composer update pne/certificate-generator --no-interaction

# W projekcie pnedu.pl
cd /var/www/pnedu.pl
composer update pne/certificate-generator --no-interaction
```

**Jeśli wystąpi błąd z uprawnieniami:**
```bash
# Sprawdź czy katalog istnieje
ls -la /var/www/shared-packages/pne-certificate-generator/

# Jeśli nie istnieje, wróć do Kroku 1
```

### Krok 5: Wyczyść cache w obu projektach

```bash
# W projekcie adm.pnedu.pl
cd /var/www/adm.pnedu.pl
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear

# W projekcie pnedu.pl
cd /var/www/pnedu.pl
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear
```

### Krok 6: Sprawdź czy wszystko działa

```bash
# Sprawdź czy pakiet jest zainstalowany
ls -la /var/www/adm.pnedu.pl/vendor/pne/certificate-generator/
ls -la /var/www/pnedu.pl/vendor/pne/certificate-generator/

# Sprawdź czy storage jest dostępny
ls -la /var/www/shared-packages/pne-certificate-generator/storage/certificates/

# Sprawdź uprawnienia
ls -la /var/www/shared-packages/pne-certificate-generator/storage/
```

### Krok 7: Przetestuj

1. **Zaloguj się do `adm.pnedu.pl`**
2. **Przejdź do edytora szablonów:** `/admin/certificate-templates/5/edit`
3. **Spróbuj wgrać nowe tło lub logo**
4. **Sprawdź czy plik się zapisał:**
   ```bash
   ls -la /var/www/shared-packages/pne-certificate-generator/storage/certificates/backgrounds/
   ls -la /var/www/shared-packages/pne-certificate-generator/storage/certificates/logos/
   ```
5. **Spróbuj wygenerować certyfikat PDF**

## 🔄 Aktualizacja pakietu w przyszłości

### Metoda 1: Przez Git (jeśli sklonowałeś z GitHub)

```bash
# Na serwerze
cd /var/www/shared-packages/pne-certificate-generator
git pull origin main

# W obu projektach
cd /var/www/adm.pnedu.pl
composer dump-autoload
php artisan config:clear
php artisan view:clear

cd /var/www/pnedu.pl
composer dump-autoload
php artisan config:clear
php artisan view:clear
```

### Metoda 2: Przez SCP (kopiowanie z lokalnego komputera)

```bash
# Na lokalnym komputerze (po zmianach w pakiecie)
scp -r /home/hostnet/WEB-APP/pne-certificate-generator/* user@seohost.pl:/var/www/shared-packages/pne-certificate-generator/

# Na serwerze (w obu projektach)
cd /var/www/adm.pnedu.pl
composer dump-autoload
php artisan config:clear
php artisan view:clear

cd /var/www/pnedu.pl
composer dump-autoload
php artisan config:clear
php artisan view:clear
```

## 🔧 Rozwiązywanie problemów

### Problem 1: Composer nie znajduje pakietu

**Objawy:**
```
[InvalidArgumentException]
Source path /var/www/shared-packages/pne-certificate-generator is not found
```

**Rozwiązanie:**
```bash
# Sprawdź czy katalog istnieje
ls -la /var/www/shared-packages/pne-certificate-generator/

# Jeśli nie istnieje, wykonaj Krok 1 ponownie
# Sprawdź uprawnienia
chmod -R 775 /var/www/shared-packages/pne-certificate-generator
```

### Problem 2: Brak uprawnień do zapisu

**Objawy:**
```
Failed to save logo/background: Permission denied
```

**Rozwiązanie:**
```bash
chmod -R 775 /var/www/shared-packages/pne-certificate-generator/storage
chown -R www-data:www-data /var/www/shared-packages/pne-certificate-generator/storage
```

### Problem 3: Pakiet nie jest wykryty przez Laravel

**Objawy:**
```
Target class [Pne\CertificateGenerator\Services\CertificateGeneratorService] does not exist
```

**Rozwiązanie:**
```bash
# W obu projektach
composer dump-autoload
php artisan package:discover
php artisan config:clear
php artisan cache:clear
```

## 📋 Checklist wdrożenia

- [ ] Utworzono katalog `/var/www/shared-packages/pne-certificate-generator` na serwerze
- [ ] Skopiowano pakiet do wspólnego katalogu (z GitHub lub lokalnie)
- [ ] Ustawiono uprawnienia (775, www-data:www-data)
- [ ] Zaktualizowano `composer.json` w `adm.pnedu.pl` (zmieniono ścieżkę na `/var/www/shared-packages/pne-certificate-generator`)
- [ ] Zaktualizowano `composer.json` w `pnedu.pl` (zmieniono ścieżkę na `/var/www/shared-packages/pne-certificate-generator`)
- [ ] Wykonano `composer update pne/certificate-generator` w obu projektach
- [ ] Wyczyszczono cache w obu projektach
- [ ] Przetestowano zapisywanie grafiki w edytorze na `adm.pnedu.pl`
- [ ] Sprawdzono czy pliki zapisują się w `/var/www/shared-packages/pne-certificate-generator/storage/`
- [ ] Przetestowano generowanie certyfikatu w `adm.pnedu.pl`
- [ ] Przetestowano generowanie certyfikatu w `pnedu.pl`

## 🎯 Różnice między dev a produkcja

### Na dev (Docker):
- Ścieżka: `../pne-certificate-generator` (relatywna)
- Pakiet w katalogu obok projektu
- Docker montuje jako volume do `/var/www/pne-certificate-generator`

### Na produkcji (seohost.pl):
- Ścieżka: `/var/www/shared-packages/pne-certificate-generator` (absolutna)
- Pakiet w wspólnym katalogu dla obu projektów
- Wszystko działa identycznie jak na dev

## ✅ Po wdrożeniu

Po wdrożeniu wszystko powinno działać:
- ✅ Grafiki zapisują się w pakiecie
- ✅ Pliki Blade zapisują się w pakiecie
- ✅ Oba projekty (`adm.pnedu.pl` i `pnedu.pl`) używają tego samego pakietu
- ✅ Generator znajduje wszystkie pliki
- ✅ Galeria pokazuje wszystkie grafiki











