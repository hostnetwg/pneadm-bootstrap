# 🔄 Migracja szablonów certyfikatów do pakietu pne-certificate-generator

## ✅ Co zostało zrobione

### 1. Dodano pakiet do `composer.json`
- Dodano zależność: `"pne/certificate-generator": "dev-main"`
- Dodano repository path: `/var/www/pne-certificate-generator`
- Zmieniono `minimum-stability` na `dev`

### 2. Dodano volume w `docker-compose.yml`
- Dodano mapowanie: `../pne-certificate-generator:/var/www/pne-certificate-generator`

### 3. Zaktualizowano `CertificateTemplate` model
- `bladeFileExists()` - sprawdza najpierw pakiet, potem lokalne
- `getBladePathAttribute()` - zwraca ścieżkę z pakietu jeśli istnieje, w przeciwnym razie lokalną

### 4. Zaktualizowano `CertificateController`
- Domyślny szablon używa teraz pakietu: `pne-certificate-generator::certificates.default`
- Fallback do lokalnego dla kompatybilności wstecznej

### 5. Przeniesiono lokalne szablony do backupu
- Lokalne szablony przeniesione do: `resources/views/certificates/backup/`
- Szablony w pakiecie są teraz używane jako główne

## 📋 Co należy zrobić teraz

### 1. Zainstaluj pakiet
```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail composer require pne/certificate-generator
```

### 2. Zrestartuj kontenery
```bash
sail down
sail up -d
```

### 3. Sprawdź czy pakiet jest zainstalowany
```bash
sail composer show | grep certificate
```

### 4. Sprawdź czy ServiceProvider jest zarejestrowany
```bash
sail artisan package:discover
```

### 5. Przetestuj generowanie certyfikatu
- Spróbuj wygenerować certyfikat dla dowolnego uczestnika
- Sprawdź czy używa szablonów z pakietu

## 📁 Struktura po migracji

**Szablony w pakiecie (używane):**
- `pne-certificate-generator/resources/views/certificates/default.blade.php`
- `pne-certificate-generator/resources/views/certificates/landscape.blade.php`
- `pne-certificate-generator/resources/views/certificates/minimal.blade.php`

**Lokalne szablony (backup):**
- `pneadm-bootstrap/resources/views/certificates/backup/default.blade.php`
- `pneadm-bootstrap/resources/views/certificates/backup/landscape.blade.php`
- `pneadm-bootstrap/resources/views/certificates/backup/minimal.blade.php`
- `pneadm-bootstrap/resources/views/certificates/backup/default-kopia.blade.php`
- `pneadm-bootstrap/resources/views/certificates/backup/landscape-kopia.blade.php`

## 🔄 Jak działa teraz

1. **Priorytet pakietu:**
   - System najpierw sprawdza czy szablon istnieje w pakiecie
   - Jeśli tak, używa go

2. **Fallback do lokalnych:**
   - Jeśli szablon nie istnieje w pakiecie, sprawdza lokalne
   - Dla kompatybilności wstecznej z istniejącymi szablonami

3. **Domyślny szablon:**
   - `pne-certificate-generator::certificates.default` (z pakietu)
   - Fallback: `certificates.default` (lokalny)

## ⚠️ Uwagi

- Lokalne szablony są w backupie - można je usunąć po weryfikacji
- Jeśli masz niestandardowe szablony, które nie są w pakiecie, pozostaną w lokalnym katalogu
- Szablony w pakiecie są wspólne dla `pneadm-bootstrap` i `pnedu`

## 🧪 Testowanie

1. Wygeneruj certyfikat dla uczestnika
2. Sprawdź logi: `sail artisan pail`
3. Sprawdź czy używa szablonu z pakietu (w logach lub w kodzie źródłowym PDF)

## ✅ Status

- ✅ Pakiet dodany do `composer.json`
- ✅ Volume dodany do `docker-compose.yml`
- ✅ `CertificateTemplate` model zaktualizowany
- ✅ `CertificateController` zaktualizowany
- ✅ Lokalne szablony przeniesione do backupu
- ⏳ **Wymagane:** Instalacja pakietu przez Composer
- ⏳ **Wymagane:** Restart kontenerów














