# ✅ Naprawa ścieżek do tła i logo w certyfikatach

## ❌ Problemy

1. **Grafika tła nie wyświetla się** w generowanych certyfikatach
2. **Logo pobiera się lokalnie** z `pnedu.pl` zamiast z pakietu `pne-certificate-generator`
3. **Stare ścieżki w bazie**: `certificate-backgrounds/...` zamiast `certificates/backgrounds/...`

## ✅ Rozwiązania

### 1. **Zaktualizowano wszystkie szablony Blade**
Wszystkie szablony (`default.blade.php`, `default-kopia.blade.php`, `landscape.blade.php`, `minimal.blade.php`) zostały zaktualizowane aby:

- **Normalizować ścieżki**: Automatycznie zamieniają `certificate-backgrounds/` na `certificates/backgrounds/`
- **Sprawdzać pakiet (priorytet)**:
  - `/var/www/pne-certificate-generator/storage/certificates/` (Docker volume)
  - `../pne-certificate-generator/storage/certificates/` (relatywna)
  - `__DIR__/../../storage/certificates/` (względna)
- **Fallback do lokalnego**: Jeśli nie znajdzie w pakiecie, sprawdza lokalne storage

### 2. **Edytor zapisuje w pakiecie**
- `uploadLogo()` - zapisuje w `pne-certificate-generator/storage/certificates/logos/`
- `uploadBackground()` - zapisuje w `pne-certificate-generator/storage/certificates/backgrounds/`
- `store()` i `update()` - podczas tworzenia/aktualizacji szablonu zapisują tła w pakiecie
- Również zapisuje lokalnie dla kompatybilności

### 3. **Kopiowanie istniejących plików**
- Skopiowano wszystkie tła z `pneadm-bootstrap` i `pnedu` do pakietu
- Pliki są teraz dostępne dla obu projektów

## 📁 Struktura plików

### W pakiecie `pne-certificate-generator`:
```
pne-certificate-generator/
└── storage/
    └── certificates/
        ├── logos/              ✅ Wspólne logo
        └── backgrounds/        ✅ Wspólne tła (w tym stare pliki)
```

### W projektach (lokalnie - fallback):
```
pneadm-bootstrap/storage/app/public/certificates/  (backup/fallback)
pnedu/storage/app/public/certificates/            (backup/fallback)
```

## 🔄 Jak działa teraz

### Generowanie certyfikatu:
1. Szablon pobiera ścieżkę tła z konfiguracji: `certificate-backgrounds/file.png`
2. **Normalizuje ścieżkę**: `certificates/backgrounds/file.png`
3. **Sprawdza pakiet** (priorytet):
   - `/var/www/pne-certificate-generator/storage/certificates/backgrounds/file.png`
   - `../pne-certificate-generator/storage/certificates/backgrounds/file.png`
4. **Fallback**: Jeśli nie znajdzie, sprawdza lokalne storage
5. **Konwertuje do base64** dla PDF

### Upload plików:
1. Użytkownik wgrywa plik przez edytor
2. **Zapisuje w pakiecie**: `pne-certificate-generator/storage/certificates/{logos|backgrounds}/`
3. **Również lokalnie**: Dla kompatybilności i dostępu przez asset()
4. **Zapisuje ścieżkę w bazie**: `certificates/{logos|backgrounds}/filename.png`

## ⚠️ Uwagi

### Stare ścieżki w bazie
Jeśli w bazie są stare ścieżki (`certificate-backgrounds/...`), szablony automatycznie je normalizują do `certificates/backgrounds/...`.

### Logo w szablonach
Szablony sprawdzają logo w pakiecie (priorytet), potem lokalnie. Logo powinno być w pakiecie.

## 🧪 Testowanie

1. **Wygeneruj certyfikat** na `pnedu.pl`
2. **Sprawdź czy tło się wyświetla**
3. **Sprawdź czy logo jest z pakietu** (nie lokalne)

## ✅ Status

- ✅ Szablony normalizują ścieżki
- ✅ Szablony sprawdzają pakiet (priorytet)
- ✅ Edytor zapisuje w pakiecie (logo i tła)
- ✅ Istniejące pliki skopiowane do pakietu
- ✅ Fallback do lokalnego storage











