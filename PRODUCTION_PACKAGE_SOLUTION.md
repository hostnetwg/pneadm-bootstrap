# 🎯 Rozwiązanie problemu z pakietem pne-certificate-generator na produkcji

## 📊 Analiza sytuacji

### Obecna konfiguracja:

**Na dev (Docker):**
- Pakiet jako **path repository** w katalogu `../pne-certificate-generator`
- Pliki zapisują się w pakiecie ✅
- Wszystko działa poprawnie ✅

**Na produkcji:**
- Pakiet z **GitHub (VCS repository)** w `vendor/pne/certificate-generator`
- Pliki **NIE mogą się zapisywać** w `vendor/` (tylko do odczytu) ❌
- Zaimplementowane rozwiązanie zapisuje w storage aplikacji, ale:
  - Metody `getAvailableLogos()` i `getAvailableBackgrounds()` sprawdzają **TYLKO pakiet**
  - Więc nie widzą plików zapisanych w storage aplikacji ❌

## ✅ Rekomendowane rozwiązanie: Katalog na serwerze (Opcja 2)

**Zalecam zmianę na path repository na produkcji** - podobnie jak na dev. To najprostsze i najbardziej spójne rozwiązanie.

### Dlaczego to najlepsze rozwiązanie:

1. ✅ **Spójność z dev** - działa tak samo jak na środowisku developerskim
2. ✅ **Pliki zapisują się w pakiecie** - jak było zaplanowane
3. ✅ **Wspólny katalog dla obu projektów** - `adm.pnedu.pl` i `pnedu.pl` używają tego samego pakietu
4. ✅ **Brak problemów z uprawnieniami** - katalog jest zapisywalny
5. ✅ **Proste wdrożenie** - nie wymaga Git na serwerze
6. ✅ **Łatwe aktualizacje** - wystarczy zaktualizować pliki w katalogu

### Wady:
- ⚠️ Brak automatycznego wersjonowania (ale można użyć Git lokalnie)
- ⚠️ Wymaga ręcznego kopiowania plików przy aktualizacji (ale można zautomatyzować)

## 🚀 Wdrożenie - Krok po kroku

### Krok 1: Przygotuj pakiet na serwerze

```bash
# Zaloguj się na serwer seohost.pl
ssh user@seohost.pl

# Utwórz katalog dla wspólnych pakietów
mkdir -p /var/www/shared-packages

# Skopiuj pakiet z lokalnego komputera (lub z GitHub)
# Opcja A: Z GitHub (jeśli masz dostęp)
cd /var/www/shared-packages
git clone git@github.com:hostnetwg/pne-certificate-generator.git

# Opcja B: Z lokalnego komputera przez SCP
# Na lokalnym komputerze:
scp -r /home/hostnet/WEB-APP/pne-certificate-generator user@seohost.pl:/var/www/shared-packages/
```

### Krok 2: Ustaw uprawnienia

```bash
# Na serwerze
chmod -R 775 /var/www/shared-packages/pne-certificate-generator
chown -R www-data:www-data /var/www/shared-packages/pne-certificate-generator

# Upewnij się, że storage jest zapisywalny
chmod -R 775 /var/www/shared-packages/pne-certificate-generator/storage
chown -R www-data:www-data /var/www/shared-packages/pne-certificate-generator/storage
```

### Krok 3: Zaktualizuj composer.json w obu projektach

**W `pneadm-bootstrap/composer.json`:**

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/var/www/shared-packages/pne-certificate-generator"
        }
    ],
    "require": {
        "pne/certificate-generator": "@dev"
    }
}
```

**W `pnedu/composer.json` (analogicznie):**

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/var/www/shared-packages/pne-certificate-generator"
        }
    ],
    "require": {
        "pne/certificate-generator": "@dev"
    }
}
```

### Krok 4: Zaktualizuj pakiet na produkcji

```bash
# W katalogu adm.pnedu.pl
cd /var/www/adm.pnedu.pl  # lub właściwa ścieżka
composer update pne/certificate-generator --no-interaction

# W katalogu pnedu.pl
cd /var/www/pnedu.pl  # lub właściwa ścieżka
composer update pne/certificate-generator --no-interaction
```

### Krok 5: Wyczyść cache

```bash
# W obu projektach
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear
```

### Krok 6: Sprawdź czy działa

```bash
# Sprawdź czy pakiet jest zainstalowany
ls -la vendor/pne/certificate-generator/

# Sprawdź czy storage jest dostępny
ls -la /var/www/shared-packages/pne-certificate-generator/storage/certificates/
```

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
```

## 🔧 Alternatywne rozwiązanie (jeśli nie możesz użyć wspólnego katalogu)

Jeśli z jakiegoś powodu nie możesz użyć wspólnego katalogu, możesz poprawić obecne rozwiązanie:

### Zmień metody `getAvailableLogos()` i `getAvailableBackgrounds()` aby sprawdzały oba miejsca:

```php
protected function getAvailableLogos()
{
    $logos = [];
    
    // 1. Sprawdź w pakiecie
    $packagePath = $this->getPackagePath();
    if ($packagePath) {
        $packageLogosPath = $packagePath . '/storage/certificates/logos';
        if (File::exists($packageLogosPath)) {
            $packageFiles = File::files($packageLogosPath);
            foreach ($packageFiles as $file) {
                $filename = $file->getFilename();
                $logos[] = [
                    'path' => 'certificates/logos/' . $filename,
                    'url' => asset('storage/certificates/logos/' . $filename),
                    'name' => $filename,
                    'size' => $file->getSize(),
                    'source' => 'package'
                ];
            }
        }
    }
    
    // 2. Sprawdź w storage aplikacji (produkcja)
    $appLogosPath = storage_path('app/public/certificates/logos');
    if (File::exists($appLogosPath)) {
        $appFiles = File::files($appLogosPath);
        foreach ($appFiles as $file) {
            $filename = $file->getFilename();
            // Sprawdź czy już nie ma w liście (unikaj duplikatów)
            $exists = false;
            foreach ($logos as $existing) {
                if ($existing['name'] === $filename) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $logos[] = [
                    'path' => 'certificates/logos/' . $filename,
                    'url' => asset('storage/certificates/logos/' . $filename),
                    'name' => $filename,
                    'size' => $file->getSize(),
                    'source' => 'app'
                ];
            }
        }
    }
    
    return $logos;
}
```

Analogicznie dla `getAvailableBackgrounds()`.

## 💡 Moja rekomendacja

**Zdecydowanie polecam Opcję 1 (wspólny katalog)** - jest najprostsza, najbardziej spójna z dev i nie wymaga zmian w kodzie. To rozwiązanie, które już działa na dev i będzie działać identycznie na produkcji.

## 📋 Checklist wdrożenia

- [ ] Utworzono katalog `/var/www/shared-packages/pne-certificate-generator` na serwerze
- [ ] Skopiowano pakiet do wspólnego katalogu
- [ ] Ustawiono uprawnienia (775, www-data:www-data)
- [ ] Zaktualizowano `composer.json` w `pneadm-bootstrap`
- [ ] Zaktualizowano `composer.json` w `pnedu`
- [ ] Wykonano `composer update` w obu projektach
- [ ] Wyczyszczono cache w obu projektach
- [ ] Przetestowano zapisywanie grafiki w edytorze
- [ ] Przetestowano generowanie certyfikatu w `adm.pnedu.pl`
- [ ] Przetestowano generowanie certyfikatu w `pnedu.pl`

