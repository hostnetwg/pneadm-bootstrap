# ✅ Naprawa ścieżek do grafik w szablonach Blade

## 🐛 Problem
Błąd podczas generowania certyfikatów:
```
file_get_contents(/var/www/html/storage/app/public/certificates/logos/1759876024_logo-pne-czarne.png): Failed to open stream: No such file or directory
```

Szablony próbowały załadować grafiki z lokalnego storage zamiast z pakietu `pne-certificate-generator`.

## ✅ Rozwiązanie

### 1. Normalizacja ścieżek
Problem: Szablony dodawały `certificates/` do ścieżek, które już go zawierały, tworząc podwójne ścieżki:
- `certificates/logos/...` → `/var/www/pne-certificate-generator/storage/certificates/certificates/logos/...` ❌

Rozwiązanie: Sprawdzanie, czy ścieżka już zawiera `certificates/`:
```php
$normalizedPath = ltrim($logoPath, '/');
if (strpos($normalizedPath, 'certificates/') === 0) {
    // Ścieżka już zawiera certificates/, użyj bezpośrednio
    $relativePath = $normalizedPath;
} else {
    // Ścieżka nie zawiera certificates/, dodaj
    $relativePath = 'certificates/' . $normalizedPath;
}
```

### 2. Zaktualizowane szablony
- ✅ `default.blade.php` - logo w headerze, logo w stopce, tło
- ✅ `landscape.blade.php` - logo w headerze, logo w stopce, tło
- ✅ `minimal.blade.php` - logo w headerze, logo w stopce, tło
- ✅ `default-kopia.blade.php` - logo w stopce, tło (był używany przez szablon ID=5)

### 3. Logika ładowania grafik
1. **Pobierz ścieżkę z konfiguracji**: `certificates/logos/1764537392_1759876024-logo-pne-czarne.png`
2. **Normalizuj ścieżkę**: usuń duplikaty, sprawdź czy zawiera `certificates/`
3. **Sprawdź pakiet (priorytet)**:
   - `/var/www/pne-certificate-generator/storage/certificates/logos/...`
   - Różne warianty ścieżek (Docker volume, relatywna, przez __DIR__)
4. **Fallback do lokalnego storage**: tylko jeśli plik nie istnieje w pakiecie
5. **Obsługa błędów**: `try-catch` przy `file_get_contents`, nie wyświetlaj logo jeśli plik nie istnieje

### 4. Logo w stopce
Szablony używają konfiguracji z bazy danych:
- `$footerConfig['logo_path']` (priorytet)
- `$headerConfig['logo_path']` (fallback)
- Sprawdzają pakiet przed lokalnym storage
- Nie wyświetlają logo, jeśli plik nie istnieje (zamiast błędu)

### 5. Tło
Szablony normalizują ścieżki tła:
- Zamieniają stare `certificate-backgrounds/` na `certificates/backgrounds/`
- Sprawdzają pakiet przed lokalnym storage
- Obsługują różne warianty ścieżek

## 🔍 Weryfikacja

### Test normalizacji ścieżek:
```php
$logoPath = 'certificates/logos/1764537392_1759876024-logo-pne-czarne.png';
$normalizedPath = ltrim($logoPath, '/');
if (strpos($normalizedPath, 'certificates/') === 0) {
    $relativePath = $normalizedPath; // certificates/logos/...
}
$packagePath = '/var/www/pne-certificate-generator/storage/' . $relativePath;
// Wynik: /var/www/pne-certificate-generator/storage/certificates/logos/... ✅
```

## ✅ Status
- ✅ Wszystkie szablony zaktualizowane
- ✅ Normalizacja ścieżek działa poprawnie
- ✅ Sprawdzanie pakietu przed lokalnym storage
- ✅ Obsługa błędów (try-catch)
- ✅ Logo i tła powinny się teraz poprawnie ładować z pakietu











