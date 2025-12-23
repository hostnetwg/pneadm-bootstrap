# Naprawa błędu pakietu pne-certificate-generator na produkcji

## Status pakietu

Pakiet `pne-certificate-generator` **NIE JEST już używany** do generowania zaświadczeń. Generowanie jest teraz realizowane przez `adm.pnedu.pl` (pneadm-bootstrap) używając lokalnych serwisów:
- `App\Services\Certificate\CertificateGeneratorService`
- `App\Services\Certificate\TemplateRenderer`
- `App\Services\Certificate\PDFGenerator`

## Problem

Błąd "Cannot redeclare Pne\CertificateGenerator\package_certificate_file_path()" występuje podczas `php artisan optimize`, ponieważ:
- Pakiet jest jeszcze zainstalowany w `composer.json`
- ServiceProvider pakietu jest automatycznie wykrywany przez Laravel
- Podczas optimize Laravel próbuje załadować ServiceProvider wielokrotnie

## Rozwiązanie

### Opcja 1: Usuń pakiet (jeśli nie jest potrzebny)

Jeśli pakiet nie jest już używany do niczego, usuń go:

```bash
cd /path/to/pneadm-bootstrap

# Usuń pakiet z composer.json
composer remove pne/certificate-generator

# Zaktualizuj autoloader
composer dump-autoload --optimize

# Wyczyść i zoptymalizuj
php artisan optimize:clear
php artisan optimize
```

### Opcja 2: Zignoruj błąd (jeśli pakiet jest potrzebny tylko do przechowywania plików)

Jeśli pakiet jest jeszcze potrzebny do przechowywania szablonów/plików graficznych, ale nie do generowania:

**Na produkcji wykonaj:**
```bash
cd /path/to/pneadm-bootstrap

# Wyczyść cache (bez optimize)
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache tylko to co potrzebne (bez optimize, które powoduje błąd)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Uwaga:** `php artisan optimize` nie będzie działać, ale aplikacja będzie działać normalnie (tylko trochę wolniej bez cache bootstrap).

### Opcja 3: Wyłącz ServiceProvider pakietu

Jeśli pakiet jest potrzebny tylko do plików, ale ServiceProvider powoduje problemy:

W `composer.json` pakietu `pne-certificate-generator` usuń lub zakomentuj:
```json
"extra": {
    "laravel": {
        "providers": [
            // "Pne\\CertificateGenerator\\CertificateGeneratorServiceProvider"
        ]
    }
}
```

Następnie na produkcji:
```bash
composer update pne/certificate-generator
composer dump-autoload --optimize
php artisan optimize
```

## Sprawdzenie czy pakiet jest używany

Sprawdź czy pakiet jest jeszcze używany:
```bash
# Sprawdź czy są importy z pakietu
grep -r "use Pne\\\\CertificateGenerator" app/

# Sprawdź czy są widoki z pakietu
grep -r "pne-certificate-generator::" app/
```

Jeśli nie ma wyników, pakiet można bezpiecznie usunąć.

## Zalecenie

**Jeśli pakiet nie jest już używany:**
- Usuń go z `composer.json` (Opcja 1)
- To rozwiąże wszystkie problemy z błędami podczas optimize

**Jeśli pakiet jest potrzebny tylko do plików:**
- Użyj Opcji 2 (pomiń `php artisan optimize`)
- Lub Opcji 3 (wyłącz ServiceProvider)









