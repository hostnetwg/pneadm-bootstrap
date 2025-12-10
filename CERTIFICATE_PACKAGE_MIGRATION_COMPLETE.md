# ✅ Migracja szablonów i plików do pakietu pne-certificate-generator

## 📋 Odpowiedzi na pytania

### 1. Czy edytor zapisuje w pakiecie?
**Teraz TAK!** ✅

**Przed:** Edytor zapisywał szablony lokalnie w `pneadm-bootstrap/resources/views/certificates/`

**Po:** Edytor zapisuje szablony w pakiecie `pne-certificate-generator/resources/views/certificates/` (z fallbackiem do lokalnego)

### 2. Czy pliki (logo, tła) są w pakiecie?
**Teraz TAK!** ✅

**Przed:** Pliki były przechowywane lokalnie w każdym projekcie osobno:
- `pneadm-bootstrap/storage/app/public/certificates/logos/`
- `pnedu/storage/app/public/certificates/logos/`

**Po:** Pliki są zapisywane w pakiecie:
- `pne-certificate-generator/storage/certificates/logos/`
- `pne-certificate-generator/storage/certificates/backgrounds/`

## ✅ Wykonane zmiany

### 1. `TemplateBuilderService::generateBladeFile()`
- ✅ Zapisuje szablony w pakiecie (priorytet)
- ✅ Fallback do lokalnego jeśli pakiet niedostępny
- ✅ Metoda `getPackagePath()` sprawdza różne lokalizacje pakietu

### 2. `CertificateTemplateController::uploadLogo()`
- ✅ Zapisuje logo w pakiecie
- ✅ Również zapisuje lokalnie dla kompatybilności
- ✅ Zwraca względną ścieżkę `certificates/logos/filename.png`

### 3. `CertificateTemplateController::uploadBackground()`
- ✅ Zapisuje tła w pakiecie
- ✅ Również zapisuje lokalnie dla kompatybilności
- ✅ Zwraca względną ścieżkę `certificates/backgrounds/filename.png`

### 4. `CertificateTemplateController::getAvailableLogos()`
- ✅ Sprawdza najpierw pakiet, potem lokalne
- ✅ Unika duplikatów (priorytet pakietu)

### 5. `CertificateTemplateController::getAvailableBackgrounds()`
- ✅ Sprawdza najpierw pakiet, potem lokalne
- ✅ Unika duplikatów (priorytet pakietu)

### 6. `CertificateTemplateController::deleteLogo()` i `deleteBackground()`
- ✅ Usuwa z pakietu i lokalnie

### 7. Struktura pakietu
- ✅ Utworzono katalogi: `pne-certificate-generator/storage/certificates/logos/`
- ✅ Utworzono katalogi: `pne-certificate-generator/storage/certificates/backgrounds/`
- ✅ Skopiowano istniejące logo i tła do pakietu

## 📁 Nowa struktura

### W pakiecie `pne-certificate-generator`:
```
pne-certificate-generator/
├── resources/
│   └── views/
│       └── certificates/
│           ├── default.blade.php          ✅ Podstawowe szablony
│           ├── landscape.blade.php       ✅
│           ├── minimal.blade.php         ✅
│           └── {slug}.blade.php          ✅ Generowane przez edytor
├── storage/
│   └── certificates/
│       ├── logos/                        ✅ Wspólne logo
│       └── backgrounds/                  ✅ Wspólne tła
└── src/
    └── Services/                         ✅ Logika generowania
```

### W projektach (lokalnie - fallback):
```
pneadm-bootstrap/resources/views/certificates/  (backup/fallback)
pneadm-bootstrap/storage/app/public/certificates/  (backup/fallback)
```

## 🔄 Jak działa teraz

### Tworzenie/edycja szablonu:
1. Użytkownik edytuje szablon w `pneadm-bootstrap`
2. `TemplateBuilderService` generuje plik Blade
3. **Zapisuje w pakiecie:** `pne-certificate-generator/resources/views/certificates/{slug}.blade.php`
4. Fallback: jeśli pakiet niedostępny, zapisuje lokalnie

### Upload logo/tła:
1. Użytkownik wgrywa plik przez edytor
2. **Zapisuje w pakiecie:** `pne-certificate-generator/storage/certificates/logos/` lub `backgrounds/`
3. Również zapisuje lokalnie dla kompatybilności
4. Oba projekty mają dostęp do tych samych plików

### Generowanie certyfikatu:
1. `CertificateController` używa konfiguracji szablonu z bazy
2. Sprawdza szablon w pakiecie (priorytet)
3. Jeśli nie ma w pakiecie, używa lokalnego
4. Pliki (logo, tła) są dostępne z pakietu

## ⚠️ Uwagi

### Dostęp do plików z pakietu

Pliki w pakiecie muszą być dostępne publicznie. Możliwe rozwiązania:

**Opcja 1: Symlink (zalecane)**
```bash
# W każdym projekcie
ln -s ../pne-certificate-generator/storage/certificates storage/app/public/certificates-package
```

**Opcja 2: Publikacja przez ServiceProvider**
- Dodać publikację storage w `CertificateGeneratorServiceProvider`
- Uruchomić `sail artisan vendor:publish --tag=pne-certificate-generator-storage`

**Opcja 3: Shared volume w Docker**
- Dodać volume w `docker-compose.yml` obu projektów
- Mapować pakiet storage do publicznego katalogu

### Uprawnienia

Upewnij się, że pakiet ma uprawnienia do zapisu:
```bash
chmod -R 775 ../pne-certificate-generator/storage
chown -R sail:sail ../pne-certificate-generator/storage
```

## 🧪 Testowanie

1. **Utwórz nowy szablon:**
   - Idź do: `http://localhost:8083/admin/certificate-templates/create`
   - Utwórz szablon
   - Sprawdź czy plik jest w pakiecie: `ls ../pne-certificate-generator/resources/views/certificates/`

2. **Wgraj logo:**
   - W edytorze szablonu kliknij "Wybierz logo"
   - Wgraj nowe logo
   - Sprawdź czy plik jest w pakiecie: `ls ../pne-certificate-generator/storage/certificates/logos/`

3. **Wygeneruj certyfikat:**
   - Użyj szablonu z logo/tłem z pakietu
   - Sprawdź czy certyfikat używa plików z pakietu

## ✅ Status

- ✅ Szablony zapisywane w pakiecie
- ✅ Logo zapisywane w pakiecie
- ✅ Tła zapisywane w pakiecie
- ✅ Metody pobierania sprawdzają pakiet
- ✅ Metody usuwania usuwają z pakietu
- ⏳ **Wymagane:** Konfiguracja dostępu publicznego do plików z pakietu
- ⏳ **Wymagane:** Symlink lub publikacja storage






