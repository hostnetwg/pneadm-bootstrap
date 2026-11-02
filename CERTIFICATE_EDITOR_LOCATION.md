# 📍 Lokalizacja edytora szablonów certyfikatów

> **NIEAKTUALNE (historyczne).** PDF nie korzysta już z pakietu `pne-certificate-generator` ani z plików Blade. Aktualny opis: [docs/CERTIFICATE_TEMPLATES.md](docs/CERTIFICATE_TEMPLATES.md).

## ✅ Odpowiedź na pytanie

**Edytor szablonów jest w `pneadm-bootstrap` (adm.pnedu.pl), NIE w pakiecie `pne-certificate-generator`.**

## 📁 Struktura kodu

### W `pneadm-bootstrap` (adm.pnedu.pl):

1. **Kontroler edytora:**
   - `app/Http/Controllers/CertificateTemplateController.php`
   - Zawiera metody: `index()`, `create()`, `store()`, `edit()`, `update()`, `destroy()`, `preview()`, `clone()`

2. **Serwis budowania szablonów:**
   - `app/Services/TemplateBuilderService.php`
   - Generuje pliki Blade z konfiguracji JSON
   - Metody: `generateBladeFile()`, `buildBladeContent()`, `buildStyles()`, `buildBlock()`

3. **Model:**
   - `app/Models/CertificateTemplate.php`
   - Zawiera logikę sprawdzania istnienia plików (`bladeFileExists()`)

4. **Widoki administracyjne:**
   - `resources/views/admin/certificate-templates/index.blade.php` - lista szablonów
   - `resources/views/admin/certificate-templates/create.blade.php` - tworzenie
   - `resources/views/admin/certificate-templates/edit.blade.php` - edycja

### W pakiecie `pne-certificate-generator`:

1. **Szablony Blade (tylko pliki):**
   - `resources/views/certificates/default.blade.php`
   - `resources/views/certificates/landscape.blade.php`
   - `resources/views/certificates/minimal.blade.php`

2. **Serwisy generowania PDF:**
   - `src/Services/CertificateGeneratorService.php` - główna logika generowania
   - `src/Services/TemplateRenderer.php` - renderowanie widoków
   - `src/Services/PDFGenerator.php` - generowanie PDF

## 🔄 Jak to działa

1. **Tworzenie/edycja szablonu:**
   - Użytkownik edytuje szablon w `pneadm-bootstrap` przez `CertificateTemplateController`
   - `TemplateBuilderService` generuje plik Blade z konfiguracji JSON
   - Plik jest zapisywany lokalnie w `resources/views/certificates/{slug}.blade.php`

2. **Generowanie certyfikatu:**
   - `CertificateController` w `pneadm-bootstrap` używa konfiguracji szablonu z bazy
   - Sprawdza czy plik Blade istnieje (pakiet lub lokalnie)
   - Przekazuje konfigurację do widoku
   - Generuje PDF używając DomPDF

## ⚠️ Uwagi

- **Edytor pozostaje w `pneadm-bootstrap`** - to jest specyficzne dla tego projektu
- **Szablony mogą być w pakiecie lub lokalnie** - system sprawdza oba miejsca
- **Pakiet zawiera tylko podstawowe szablony** - niestandardowe szablony są generowane lokalnie

## 🎯 Dlaczego tak?

- Edytor szablonów jest specyficzny dla `pneadm-bootstrap` (panel administracyjny)
- Pakiet `pne-certificate-generator` jest wspólny dla obu projektów i zawiera tylko logikę generowania
- Niestandardowe szablony są generowane dynamicznie i zapisywane lokalnie w każdym projekcie















