# System Zarządzania Logo dla Certyfikatów

## 📁 Lokalizacja plików

### Pliki logo przechowywane są w:
```
storage/app/public/certificates/logos/
```

### Dostęp przez przeglądarkę:
```
http://localhost:8083/storage/certificates/logos/nazwa-pliku.png
```

## 🔧 Funkcjonalność

### 1. **Upload Logo**
- Endpoint: `POST /admin/certificate-templates/upload-logo`
- Dozwolone formaty: JPG, PNG, GIF, SVG
- Maksymalny rozmiar: 2MB
- Automatyczna nazwa: `timestamp_slug.ext`

### 2. **Usuwanie Logo**
- Endpoint: `DELETE /admin/certificate-templates/delete-logo`
- Wymaga parametru: `path` (ścieżka do pliku)

### 3. **Galeria Logo**
- Modal wyświetlający wszystkie dostępne logo
- Możliwość:
  - Wyboru istniejącego logo
  - Uploadu nowego logo
  - Usuwania nieużywanych logo
  - Podglądu miniatur

## 🎨 Używanie w szablonach

### W kreatorze szablonów:
1. Dodaj blok "Nagłówek"
2. Zaznacz checkbox "Pokaż logo"
3. Kliknij przycisk "Wybierz logo"
4. W modalu możesz:
   - Wybrać istniejące logo
   - Wgrać nowe logo
5. Logo automatycznie pojawi się w podglądzie

### W pliku blade (generowanym automatycznie):
```blade
@if (!empty($config['show_logo']) && !empty($config['logo_path']))
    <img src="{{ storage_path('app/public/{$config['logo_path']}') }}" 
         alt="Logo" 
         style="max-width: {$logoSize}px; height: auto;">
@endif
```

## ⚙️ Konfiguracja w TemplateBuilderService

Blok header obsługuje:
- `show_logo` (boolean) - czy pokazywać logo
- `logo_path` (string) - ścieżka do pliku logo
- `logo_size` (int) - maksymalna szerokość w pikselach (domyślnie 150)

## 📝 Najlepsze praktyki

### Zalecane wymiary logo:
- **Orientacja pionowa (portrait)**: 800x200px lub 600x150px
- **Orientacja pozioma (landscape)**: 1200x200px lub 900x150px
- Format: PNG z przezroczystym tłem

### Nazewnictwo plików:
- Używaj opisowych nazw: `logo-firma-2025.png`
- Unikaj polskich znaków
- System automatycznie dodaje timestamp

## 🔐 Bezpieczeństwo

- Walidacja typu MIME pliku
- Maksymalny rozmiar 2MB
- Tylko obrazy (jpg, png, gif, svg)
- CSRF protection na wszystkich endpointach
- Storage symlink musi być aktywny: `php artisan storage:link`

## 🚀 Pierwsze uruchomienie

1. Upewnij się że folder istnieje:
```bash
./vendor/bin/sail artisan storage:link
mkdir -p storage/app/public/certificates/logos
chmod -R 775 storage/app/public/certificates
```

2. Wgraj pierwsze logo przez panel admin:
   - Idź do: http://localhost:8083/admin/certificate-templates/create
   - Dodaj blok "Nagłówek"
   - Zaznacz "Pokaż logo"
   - Kliknij "Wybierz logo"
   - Wgraj plik

3. Logo będzie dostępne dla wszystkich szablonów!

## 📊 Struktura bazy danych

Logo nie są przechowywane w bazie - tylko ścieżki w `config` szablonu:

```json
{
  "blocks": {
    "header": {
      "type": "header",
      "config": {
        "show_logo": true,
        "logo_path": "certificates/logos/1696790400_logo-firma.png",
        "logo_size": 150
      }
    }
  }
}
```

## 🐛 Rozwiązywanie problemów

### Logo nie wyświetla się w PDF:
- Sprawdź czy ścieżka istnieje: `storage/app/public/certificates/logos/...`
- Upewnij się że używasz `storage_path()` w blade, nie `public_path()`

### Błąd uploadu:
- Sprawdź uprawnienia: `chmod 775 storage/app/public/certificates/logos`
- Sprawdź rozmiar pliku (max 2MB)
- Sprawdź czy storage link działa

### Logo nie ma w galerii:
- Wgraj nowe logo
- Odśwież stronę
- Sprawdź czy pliki są w `storage/app/public/certificates/logos/`

## 📞 API Endpoints

```php
// Upload logo
POST /admin/certificate-templates/upload-logo
Parameters: logo (file)
Response: { success: true, path: "...", url: "...", name: "..." }

// Delete logo
DELETE /admin/certificate-templates/delete-logo  
Parameters: path (string)
Response: { success: true, message: "..." }

// Get available logos (w kontrolerze)
CertificateTemplateController::getAvailableLogos()
Returns: Array of logo objects
```

## ✅ Status Implementacji

- [x] Upload logo
- [x] Galeria logo
- [x] Usuwanie logo
- [x] Wybór logo w kreatorze
- [x] Automatyczne generowanie blade z logo
- [x] Podgląd logo przed wyborem
- [ ] Edycja logo (crop, resize) - TODO
- [ ] Masowe usuwanie - TODO
- [ ] Wersjonowanie logo - TODO

