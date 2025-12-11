# 📍 Analiza lokalizacji plików certyfikatów

## ❌ Obecna sytuacja

### 1. **Szablony Blade zapisywane lokalnie**
- **Gdzie:** `pneadm-bootstrap/resources/views/certificates/`
- **Kod:** `TemplateBuilderService::generateBladeFile()` zapisuje w `resource_path("views/certificates/{$fileName}")`
- **Problem:** Szablony są tylko w `pneadm-bootstrap`, nie w pakiecie

### 2. **Pliki logo i tła przechowywane lokalnie**
- **Logo:** `storage/app/public/certificates/logos/` w każdym projekcie osobno
- **Tła:** `storage/app/public/certificates/backgrounds/` w każdym projekcie osobno
- **Problem:** Pliki są duplikowane między projektami

## ✅ Co powinno być w pakiecie `pne-certificate-generator`

### 1. **Szablony Blade**
- Podstawowe szablony (default, landscape, minimal) - ✅ już są
- Niestandardowe szablony generowane przez edytor - ❌ obecnie lokalnie

### 2. **Pliki zasobów**
- Logo - ❌ obecnie lokalnie w każdym projekcie
- Tła (gilosze) - ❌ obecnie lokalnie w każdym projekcie

## 🎯 Proponowane rozwiązanie

### Opcja 1: Wszystko w pakiecie (zalecane)

**Zmiany w `TemplateBuilderService`:**
```php
public function generateBladeFile($config, $slug)
{
    $bladeContent = $this->buildBladeContent($config);
    $fileName = Str::slug($slug) . '.blade.php';
    
    // Zapis w pakiecie zamiast lokalnie
    $packagePath = base_path('../pne-certificate-generator/resources/views/certificates/');
    // LUB przez ServiceProvider w pakiecie
    $path = $packagePath . $fileName;
    
    File::put($path, $bladeContent);
    return $fileName;
}
```

**Zmiany w uploadach:**
- Logo i tła zapisywane w pakiecie: `pne-certificate-generator/storage/certificates/logos/`
- Symlink lub publikacja do publicznego katalogu

**Korzyści:**
- ✅ Wspólne szablony dla obu projektów
- ✅ Wspólne pliki zasobów
- ✅ Jedna wersja prawdy

**Wady:**
- ⚠️ Wymaga dostępu do pakietu z poziomu edytora
- ⚠️ Wymaga synchronizacji plików między projektami

### Opcja 2: Hybrid (szablony w pakiecie, pliki lokalnie)

**Zmiany:**
- Szablony generowane przez edytor → pakiet
- Logo i tła → pozostają lokalnie (każdy projekt ma swoje)

**Korzyści:**
- ✅ Szablony wspólne
- ✅ Pliki mogą być różne w każdym projekcie

**Wady:**
- ⚠️ Pliki nadal duplikowane

### Opcja 3: Wszystko lokalnie (obecna sytuacja)

**Status quo:**
- Szablony lokalnie w każdym projekcie
- Pliki lokalnie w każdym projekcie

**Korzyści:**
- ✅ Proste w implementacji
- ✅ Każdy projekt niezależny

**Wady:**
- ❌ Duplikacja kodu i plików
- ❌ Trudność w synchronizacji zmian

## 🔄 Rekomendacja

**Zalecam Opcję 1** - wszystko w pakiecie, ponieważ:
1. Szablony powinny być wspólne (to jest logika biznesowa)
2. Logo i tła powinny być wspólne (to są zasoby marki)
3. Ułatwia utrzymanie i aktualizacje

## 📝 Co należy zmienić

### 1. `TemplateBuilderService::generateBladeFile()`
- Zmienić ścieżkę zapisu na pakiet
- Dodać sprawdzenie czy pakiet jest dostępny

### 2. `CertificateTemplateController::uploadLogo()` i `uploadBackground()`
- Zmienić ścieżkę zapisu na pakiet
- Dodać publikację do publicznego katalogu

### 3. Szablony Blade
- Zmienić ścieżki do logo/tła na pakiet
- Użyć helpera do ścieżek pakietu

### 4. Docker volumes
- Upewnić się że pakiet jest zamontowany w obu projektach
- Dodać volume dla storage pakietu

## ⚠️ Uwagi

- Pakiet musi mieć katalog `storage/` lub publikować pliki do publicznego katalogu
- Wymagane uprawnienia do zapisu w pakiecie
- Synchronizacja między projektami (git lub shared volume)








