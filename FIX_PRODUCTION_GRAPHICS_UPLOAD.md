# 🔧 Naprawa zapisywania grafiki tła i logo na produkcji

## 🎯 Problem

Na produkcji nie działa zapisywanie grafiki tła, logo oraz plików Blade szablonów w edytorze szablonów zaświadczeń. Problem wynika z faktu, że:

1. **Pakiet jest zainstalowany z GitHub** jako VCS repository → znajduje się w `vendor/pne/certificate-generator`
2. **Katalog `vendor/` jest tylko do odczytu** - nie można tam zapisywać plików:
   - To jest kod źródłowy z repozytorium Git
   - Zmiany zostaną nadpisane przy `composer update`
   - Brak uprawnień do zapisu
3. **Na dev (Docker)** pakiet jest jako path repository z volume → można zapisywać w pakiecie

### Dotyczy:
- ❌ **Grafiki tła** (`certificates/backgrounds/`)
- ❌ **Grafiki logo** (`certificates/logos/`)
- ❌ **Pliki Blade szablonów** (`resources/views/certificates/*.blade.php`)

## ✅ Rozwiązanie

Zmień logikę zapisu, aby:
- **Na produkcji** (pakiet w vendor) → zapisuj w **storage aplikacji** (grafiki) i **resources/views aplikacji** (Blade)
- **Na dev** (pakiet jako path repository) → zapisuj w **pakiecie** (jak dotychczas)
- **Generator już sprawdza oba miejsca**, więc będzie działać poprawnie

### Lokalizacje zapisu na produkcji:
- **Grafiki**: `storage/app/public/certificates/{logos|backgrounds}/`
- **Pliki Blade**: `resources/views/certificates/{slug}.blade.php`

## 📝 Implementacja

### Krok 1: Dodaj metodę sprawdzającą czy pakiet jest zapisywalny

W `CertificateTemplateController` dodaj metodę:

```php
/**
 * Sprawdza czy pakiet jest zapisywalny (path repository) czy tylko do odczytu (vendor)
 */
protected function isPackageWritable(): bool
{
    $packagePath = $this->getPackagePath();
    
    if (!$packagePath) {
        return false;
    }
    
    // Jeśli pakiet jest w vendor - nie jest zapisywalny
    if (strpos($packagePath, 'vendor/') !== false) {
        return false;
    }
    
    // Sprawdź czy można zapisać w katalogu storage pakietu
    $testPath = $packagePath . '/storage';
    if (!File::exists($testPath)) {
        return false;
    }
    
    // Sprawdź uprawnienia - próba utworzenia testowego pliku
    $testFile = $testPath . '/.writable_test';
    try {
        @File::put($testFile, 'test');
        if (File::exists($testFile)) {
            File::delete($testFile);
            return true;
        }
    } catch (\Exception $e) {
        return false;
    }
    
    return false;
}
```

### Krok 2: Zmień metodę `uploadLogo()` aby używała storage aplikacji na produkcji

```php
public function uploadLogo(Request $request)
{
    $request->validate([
        'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
    ]);

    if ($request->hasFile('logo')) {
        $file = $request->file('logo');
        $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
        
        $relativePath = 'certificates/logos/' . $filename;
        $savedPath = null;
        
        // Sprawdź czy pakiet jest zapisywalny
        if ($this->isPackageWritable()) {
            // Dev: zapisz w pakiecie
            $packagePath = $this->getPackagePath();
            $packageStoragePath = $packagePath . '/storage/certificates/logos';
            $packageFilePath = $packageStoragePath . '/' . $filename;
            
            if (!File::exists($packageStoragePath)) {
                File::makeDirectory($packageStoragePath, 0755, true);
            }
            
            try {
                File::put($packageFilePath, file_get_contents($file->getRealPath()));
                $savedPath = $packageFilePath;
                
                \Log::info('Logo saved to package', [
                    'package_path' => $packageFilePath,
                    'filename' => $filename
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to save logo to package', [
                    'package_path' => $packageFilePath,
                    'error' => $e->getMessage()
                ]);
                // Fallback do storage aplikacji
                $this->saveToAppStorage($file, $relativePath);
                $savedPath = storage_path('app/public/' . $relativePath);
            }
        } else {
            // Produkcja: zapisz w storage aplikacji
            $this->saveToAppStorage($file, $relativePath);
            $savedPath = storage_path('app/public/' . $relativePath);
            
            \Log::info('Logo saved to app storage (production)', [
                'path' => $savedPath,
                'filename' => $filename
            ]);
        }
        
        if ($savedPath && File::exists($savedPath)) {
            $url = asset('storage/' . $relativePath);
            
            return response()->json([
                'success' => true,
                'path' => $relativePath,
                'url' => $url,
                'name' => $filename
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Nie udało się zapisać logo'
        ], 500);
    }

    return response()->json([
        'success' => false,
        'message' => 'Nie przesłano pliku'
    ], 400);
}

/**
 * Zapisuje plik do storage aplikacji
 */
protected function saveToAppStorage($file, string $relativePath): void
{
    $fullPath = storage_path('app/public/' . $relativePath);
    $directory = dirname($fullPath);
    
    if (!File::exists($directory)) {
        File::makeDirectory($directory, 0755, true);
    }
    
    Storage::disk('public')->put($relativePath, file_get_contents($file->getRealPath()));
}
```

### Krok 3: Zmień metodę `uploadBackground()` analogicznie

```php
public function uploadBackground(Request $request)
{
    $request->validate([
        'background' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120'
    ]);

    if ($request->hasFile('background')) {
        $file = $request->file('background');
        $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
        
        $relativePath = 'certificates/backgrounds/' . $filename;
        $savedPath = null;
        
        // Sprawdź czy pakiet jest zapisywalny
        if ($this->isPackageWritable()) {
            // Dev: zapisz w pakiecie
            $packagePath = $this->getPackagePath();
            $packageStoragePath = $packagePath . '/storage/certificates/backgrounds';
            $packageFilePath = $packageStoragePath . '/' . $filename;
            
            if (!File::exists($packageStoragePath)) {
                File::makeDirectory($packageStoragePath, 0755, true);
            }
            
            try {
                File::put($packageFilePath, file_get_contents($file->getRealPath()));
                $savedPath = $packageFilePath;
                
                \Log::info('Background saved to package', [
                    'package_path' => $packageFilePath,
                    'filename' => $filename
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to save background to package', [
                    'package_path' => $packageFilePath,
                    'error' => $e->getMessage()
                ]);
                // Fallback do storage aplikacji
                $this->saveToAppStorage($file, $relativePath);
                $savedPath = storage_path('app/public/' . $relativePath);
            }
        } else {
            // Produkcja: zapisz w storage aplikacji
            $this->saveToAppStorage($file, $relativePath);
            $savedPath = storage_path('app/public/' . $relativePath);
            
            \Log::info('Background saved to app storage (production)', [
                'path' => $savedPath,
                'filename' => $filename
            ]);
        }
        
        if ($savedPath && File::exists($savedPath)) {
            $url = asset('storage/' . $relativePath);
            
            return response()->json([
                'success' => true,
                'path' => $relativePath,
                'url' => $url,
                'name' => $filename
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Nie udało się zapisać tła'
        ], 500);
    }

    return response()->json([
        'success' => false,
        'message' => 'Nie przesłano pliku'
    ], 400);
}
```

### Krok 4: Zaktualizuj metody `getAvailableLogos()` i `getAvailableBackgrounds()`

Te metody powinny sprawdzać oba miejsca (pakiet i storage aplikacji):

```php
protected function getAvailableLogos(): array
{
    $logos = [];
    
    // 1. Sprawdź w pakiecie (jeśli istnieje)
    $packagePath = $this->getPackagePath();
    if ($packagePath) {
        $packageLogosPath = $packagePath . '/storage/certificates/logos';
        if (File::exists($packageLogosPath)) {
            $packageLogos = File::files($packageLogosPath);
            foreach ($packageLogos as $logo) {
                $relativePath = 'certificates/logos/' . $logo->getFilename();
                $logos[] = [
                    'path' => $relativePath,
                    'name' => $logo->getFilename(),
                    'url' => asset('storage/' . $relativePath),
                    'source' => 'package'
                ];
            }
        }
    }
    
    // 2. Sprawdź w storage aplikacji
    $appLogosPath = storage_path('app/public/certificates/logos');
    if (File::exists($appLogosPath)) {
        $appLogos = File::files($appLogosPath);
        foreach ($appLogos as $logo) {
            $relativePath = 'certificates/logos/' . $logo->getFilename();
            // Sprawdź czy już nie ma w liście (unikaj duplikatów)
            $exists = false;
            foreach ($logos as $existing) {
                if ($existing['name'] === $logo->getFilename()) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $logos[] = [
                    'path' => $relativePath,
                    'name' => $logo->getFilename(),
                    'url' => asset('storage/' . $relativePath),
                    'source' => 'app'
                ];
            }
        }
    }
    
    return $logos;
}
```

Analogicznie dla `getAvailableBackgrounds()`.

## 🔍 Dlaczego to działa?

1. **Generator już sprawdza oba miejsca** - szablony Blade w pakiecie mają logikę sprawdzającą:
   - Najpierw pakiet (`/var/www/pne-certificate-generator/storage/`)
   - Potem storage aplikacji (`storage/app/public/`)

2. **Na dev** - pakiet jest zapisywalny → grafiki w pakiecie (wspólne dla obu projektów)

3. **Na produkcji** - pakiet w vendor (tylko do odczytu) → grafiki w storage aplikacji

## 📋 Checklist wdrożenia

### Grafiki (logo i tła):
- [ ] Dodać metodę `isPackageWritable()`
- [ ] Dodać metodę `saveToAppStorage()`
- [ ] Zaktualizować `uploadLogo()`
- [ ] Zaktualizować `uploadBackground()`
- [ ] Zaktualizować `getAvailableLogos()`
- [ ] Zaktualizować `getAvailableBackgrounds()`
- [ ] Zaktualizować `deleteLogo()` i `deleteBackground()` aby usuwały z obu miejsc

### Pliki Blade szablonów:
- [ ] Zaktualizować `TemplateBuilderService::generateBladeFile()` aby zapisywała w aplikacji na produkcji
- [ ] Zaktualizować `CertificateTemplate::bladeFileExists()` aby sprawdzała oba miejsca
- [ ] Przetestować generowanie szablonów na produkcji

### Testy:
- [ ] Przetestować na dev (Docker) - powinno działać jak dotychczas
- [ ] Przetestować na produkcji - grafiki i szablony powinny zapisywać się lokalnie

## 🚀 Szybka naprawa (gotowy kod)

Zobacz plik `FIX_PRODUCTION_GRAPHICS_UPLOAD_CODE.php` z gotowym kodem do skopiowania.

