<?php
/**
 * Gotowy kod do naprawy zapisywania grafiki na produkcji
 * Skopiuj te metody do CertificateTemplateController
 */

namespace App\Http\Controllers;

// Dodaj te metody do klasy CertificateTemplateController

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

/**
 * Zapisuje plik do storage aplikacji
 */
protected function saveToAppStorage($file, string $relativePath): void
{
    $directory = dirname(storage_path('app/public/' . $relativePath));
    
    if (!File::exists($directory)) {
        File::makeDirectory($directory, 0755, true);
    }
    
    Storage::disk('public')->put($relativePath, file_get_contents($file->getRealPath()));
}

/**
 * Zaktualizowana metoda uploadLogo() - zapisuje w pakiecie (dev) lub storage aplikacji (produkcja)
 */
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
 * Zaktualizowana metoda uploadBackground() - zapisuje w pakiecie (dev) lub storage aplikacji (produkcja)
 */
public function uploadBackground(Request $request)
{
    $request->validate([
        'background' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120' // 5MB
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

/**
 * Zaktualizowana metoda deleteLogo() - usuwa z pakietu lub storage aplikacji
 */
public function deleteLogo(Request $request)
{
    $request->validate([
        'path' => 'required|string'
    ]);

    $path = $request->input('path');
    
    // Normalizuj ścieżkę (usuń ewentualne prefiksy)
    $normalizedPath = ltrim($path, '/');
    if (strpos($normalizedPath, 'storage/') === 0) {
        $normalizedPath = substr($normalizedPath, 8); // Usuń 'storage/'
    }
    
    $deleted = false;
    
    // 1. Spróbuj usunąć z pakietu
    $packagePath = $this->getPackagePath();
    if ($packagePath) {
        $packagePaths = [
            $packagePath . '/storage/' . $normalizedPath,
            $packagePath . '/storage/certificates/logos/' . basename($normalizedPath),
        ];
        
        foreach ($packagePaths as $packageFilePath) {
            if (File::exists($packageFilePath)) {
                try {
                    File::delete($packageFilePath);
                    \Log::info('Logo deleted from package', ['path' => $packageFilePath]);
                    $deleted = true;
                    break;
                } catch (\Exception $e) {
                    \Log::error('Failed to delete logo from package', [
                        'path' => $packageFilePath,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
    
    // 2. Spróbuj usunąć z storage aplikacji
    $appPath = storage_path('app/public/' . $normalizedPath);
    if (File::exists($appPath)) {
        try {
            File::delete($appPath);
            \Log::info('Logo deleted from app storage', ['path' => $appPath]);
            $deleted = true;
        } catch (\Exception $e) {
            \Log::error('Failed to delete logo from app storage', [
                'path' => $appPath,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    if ($deleted) {
        return response()->json([
            'success' => true,
            'message' => 'Logo zostało usunięte'
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'Plik nie istnieje.'
    ], 404);
}

/**
 * Zaktualizowana metoda deleteBackground() - usuwa z pakietu lub storage aplikacji
 */
public function deleteBackground(Request $request)
{
    $request->validate([
        'path' => 'required|string'
    ]);

    $path = $request->input('path');
    
    // Normalizuj ścieżkę (usuń ewentualne prefiksy)
    $normalizedPath = ltrim($path, '/');
    if (strpos($normalizedPath, 'storage/') === 0) {
        $normalizedPath = substr($normalizedPath, 8); // Usuń 'storage/'
    }
    
    $deleted = false;
    
    // 1. Spróbuj usunąć z pakietu
    $packagePath = $this->getPackagePath();
    if ($packagePath) {
        $packagePaths = [
            $packagePath . '/storage/' . $normalizedPath,
            $packagePath . '/storage/certificates/backgrounds/' . basename($normalizedPath),
        ];
        
        foreach ($packagePaths as $packageFilePath) {
            if (File::exists($packageFilePath)) {
                try {
                    File::delete($packageFilePath);
                    \Log::info('Background deleted from package', ['path' => $packageFilePath]);
                    $deleted = true;
                    break;
                } catch (\Exception $e) {
                    \Log::error('Failed to delete background from package', [
                        'path' => $packageFilePath,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
    
    // 2. Spróbuj usunąć z storage aplikacji
    $appPath = storage_path('app/public/' . $normalizedPath);
    if (File::exists($appPath)) {
        try {
            File::delete($appPath);
            \Log::info('Background deleted from app storage', ['path' => $appPath]);
            $deleted = true;
        } catch (\Exception $e) {
            \Log::error('Failed to delete background from app storage', [
                'path' => $appPath,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    if ($deleted) {
        return response()->json([
            'success' => true,
            'message' => 'Tło zostało usunięte'
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'Plik nie istnieje.'
    ], 404);
}




