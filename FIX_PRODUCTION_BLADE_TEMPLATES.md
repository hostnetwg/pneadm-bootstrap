# 🔧 Naprawa zapisywania plików Blade szablonów na produkcji

## 🎯 Problem

Pliki Blade szablonów (np. `default.blade.php`) są również zapisywane w pakiecie podczas edycji szablonów. Na produkcji, gdy pakiet jest w `vendor/`, zapis nie zadziała z tych samych powodów co grafiki.

## ✅ Rozwiązanie

Zaktualizuj `TemplateBuilderService::generateBladeFile()` aby zapisywała w aplikacji na produkcji.

## 📝 Kod do wdrożenia

### 1. Zaktualizuj metodę `generateBladeFile()` w `app/Services/TemplateBuilderService.php`:

```php
public function generateBladeFile($config, $slug)
{
    $bladeContent = $this->buildBladeContent($config);
    
    $fileName = Str::slug($slug) . '.blade.php';
    
    // Sprawdź czy pakiet jest zapisywalny
    $packagePath = $this->getPackagePath();
    $isPackageWritable = $this->isPackageWritable();
    
    if ($isPackageWritable && $packagePath) {
        // Dev: zapisz w pakiecie
        $packageFilePath = $packagePath . '/resources/views/certificates/' . $fileName;
        $packageDirectory = dirname($packageFilePath);
        
        if (!File::exists($packageDirectory)) {
            File::makeDirectory($packageDirectory, 0755, true);
        }
        
        try {
            File::put($packageFilePath, $bladeContent);
            \Log::info('Template saved to package', [
                'slug' => $slug,
                'package_path' => $packageFilePath
            ]);
            return $fileName;
        } catch (\Exception $e) {
            \Log::error('Failed to save template to package', [
                'slug' => $slug,
                'package_path' => $packageFilePath,
                'error' => $e->getMessage()
            ]);
            // Fallback do aplikacji
            return $this->saveBladeToApp($bladeContent, $fileName, $slug);
        }
    } else {
        // Produkcja: zapisz w aplikacji
        return $this->saveBladeToApp($bladeContent, $fileName, $slug);
    }
}
```

### 2. Dodaj metodę `saveBladeToApp()`:

```php
/**
 * Zapisuje plik Blade do aplikacji (produkcja)
 */
protected function saveBladeToApp(string $bladeContent, string $fileName, string $slug): string
{
    $appPath = resource_path('views/certificates/' . $fileName);
    $appDirectory = dirname($appPath);
    
    if (!File::exists($appDirectory)) {
        File::makeDirectory($appDirectory, 0755, true);
    }
    
    try {
        File::put($appPath, $bladeContent);
        \Log::info('Template saved to app (production)', [
            'slug' => $slug,
            'app_path' => $appPath
        ]);
        return $fileName;
    } catch (\Exception $e) {
        \Log::error('Failed to save template to app', [
            'slug' => $slug,
            'app_path' => $appPath,
            'error' => $e->getMessage()
        ]);
        throw new \Exception('Nie udało się zapisać szablonu w aplikacji: ' . $e->getMessage());
    }
}
```

### 3. Dodaj metodę `isPackageWritable()`:

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
    
    // Sprawdź czy można zapisać w katalogu resources/views pakietu
    $testPath = $packagePath . '/resources/views';
    if (!File::exists($testPath)) {
        return false;
    }
    
    // Sprawdź uprawnienia - próba utworzenia testowego pliku
    $testFile = $testPath . '/.writable_test_' . time();
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

## 🔍 Dlaczego to działa?

`TemplateRenderer` w pakiecie już sprawdza oba miejsca:
1. Najpierw pakiet: `pne-certificate-generator::certificates.{slug}`
2. Potem aplikacja: `certificates.{slug}`

Więc szablony zapisane w `resources/views/certificates/` aplikacji będą znajdowane przez generator.

## 📋 Lokalizacje zapisu

### Na produkcji:
- **Pliki Blade**: `resources/views/certificates/{slug}.blade.php`
- **Grafiki**: `storage/app/public/certificates/{logos|backgrounds}/`

### Na dev (Docker):
- **Pliki Blade**: `pne-certificate-generator/resources/views/certificates/{slug}.blade.php`
- **Grafiki**: `pne-certificate-generator/storage/certificates/{logos|backgrounds}/`

## ✅ Testy

1. Na dev (Docker) - powinno działać jak dotychczas (zapis w pakiecie)
2. Na produkcji - szablony powinny zapisywać się w aplikacji i być dostępne dla generatora










