# ✅ Migracja szablonów do pakietu - ZAKOŃCZONA

## 🎯 Cel
Wszystkie szablony Blade certyfikatów są teraz przechowywane **TYLKO** w pakiecie `pne-certificate-generator`, nie lokalnie w `pneadm-bootstrap`.

## ✅ Wykonane zmiany

### 1. **TemplateBuilderService::generateBladeFile()**
- ✅ **Zapisuje TYLKO w pakiecie** - nie ma już fallbacku do lokalnego
- ✅ Rzuca wyjątek jeśli pakiet nie jest dostępny
- ✅ Loguje informacje o zapisie do pakietu

### 2. **CertificateTemplate::bladeFileExists()**
- ✅ Sprawdza **TYLKO pakiet** - nie sprawdza już lokalnych plików
- ✅ Używa `View::exists()` z namespace pakietu

### 3. **CertificateTemplate::getBladePathAttribute()**
- ✅ Zawsze zwraca ścieżkę z pakietu: `pne-certificate-generator::certificates.{slug}`
- ✅ Nie ma już fallbacku do lokalnego

### 4. **CertificateController**
- ✅ Używa tylko szablonów z pakietu
- ✅ Loguje ostrzeżenie jeśli szablon nie istnieje w pakiecie

### 5. **Przeniesienie istniejących szablonów**
- ✅ `default-kopia.blade.php` → przeniesiony do pakietu
- ✅ Lokalne szablony → przeniesione do `backup/`

## 📁 Struktura po migracji

### W pakiecie `pne-certificate-generator`:
```
pne-certificate-generator/
└── resources/
    └── views/
        └── certificates/
            ├── default.blade.php          ✅
            ├── landscape.blade.php        ✅
            ├── minimal.blade.php          ✅
            └── default-kopia.blade.php    ✅ (i inne generowane)
```

### W `pneadm-bootstrap` (tylko backup):
```
pneadm-bootstrap/
└── resources/
    └── views/
        └── certificates/
            └── backup/                    ✅ Stare szablony (backup)
                ├── default.blade.php
                ├── landscape.blade.php
                ├── minimal.blade.php
                └── default-kopia.blade.php
```

## 🔄 Jak działa teraz

### Tworzenie/edycja szablonu:
1. Użytkownik edytuje szablon w `pneadm-bootstrap`
2. `TemplateBuilderService::generateBladeFile()` generuje plik Blade
3. **Zapisuje TYLKO w pakiecie:** `pne-certificate-generator/resources/views/certificates/{slug}.blade.php`
4. Jeśli pakiet niedostępny → **błąd** (nie zapisuje lokalnie)

### Sprawdzanie istnienia szablonu:
1. `CertificateTemplate::bladeFileExists()` sprawdza tylko pakiet
2. Używa `View::exists('pne-certificate-generator::certificates.{slug}')`

### Generowanie certyfikatu:
1. `CertificateController` używa szablonu z pakietu
2. Sprawdza czy szablon istnieje w pakiecie
3. Jeśli nie → loguje ostrzeżenie i używa domyślnego

## ⚠️ Wymagania

### Docker volume musi być zamontowany:
```yaml
volumes:
  - '../pne-certificate-generator:/var/www/pne-certificate-generator'
```

### Uprawnienia do zapisu:
```bash
chmod -R 775 ../pne-certificate-generator/resources/views/certificates
chown -R sail:sail ../pne-certificate-generator/resources/views/certificates
```

## 🧪 Testowanie

1. **Utwórz nowy szablon:**
   - Idź do: `http://localhost:8083/admin/certificate-templates/create`
   - Utwórz szablon
   - Sprawdź czy plik jest w pakiecie: `ls ../pne-certificate-generator/resources/views/certificates/`

2. **Sprawdź listę szablonów:**
   - Idź do: `http://localhost:8083/admin/certificate-templates`
   - Powinno pokazywać "Istnieje" dla szablonów w pakiecie

3. **Wygeneruj certyfikat:**
   - Użyj szablonu z pakietu
   - Sprawdź logi czy nie ma błędów

## 📊 Status

- ✅ Szablony zapisywane TYLKO w pakiecie
- ✅ Brak fallbacku do lokalnego
- ✅ Model sprawdza tylko pakiet
- ✅ Controller używa tylko pakietu
- ✅ Istniejące szablony przeniesione do pakietu
- ✅ Lokalne szablony w backupie

## 🔍 Rozwiązywanie problemów

### Błąd: "Nie można znaleźć pakietu"
- Sprawdź czy Docker volume jest zamontowany
- Sprawdź logi: `sail artisan tinker` → `getPackagePath()`
- Sprawdź uprawnienia do katalogu pakietu

### Szablon nie istnieje
- Sprawdź czy plik jest w pakiecie: `ls ../pne-certificate-generator/resources/views/certificates/`
- Sprawdź czy ServiceProvider ładuje widoki: `sail artisan view:clear`
- Sprawdź namespace: `pne-certificate-generator::certificates.{slug}`














