# 🔄 Instrukcja zmiany slug szablonu ID=5 z 'default-kopia' na 'default'

## ✅ Co zostało zrobione automatycznie

1. **Backup pliku blade:**
   - Utworzono backup: `default.blade.php.backup.{timestamp}`
   - Lokalizacja: `pne-certificate-generator/resources/views/certificates/`

2. **Aktualizacja pliku blade:**
   - Zawartość `default-kopia.blade.php` została skopiowana do `default.blade.php`
   - Stary plik `default-kopia.blade.php` pozostaje (można go usunąć po weryfikacji)

## 📋 Co trzeba zrobić ręcznie

### 1. Zmiana slug w bazie danych

**Opcja A: Przez komendę Artisan (gdy Docker działa):**
```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail artisan template:change-slug 5 default
```

**Opcja B: Przez SQL (bezpośrednio w bazie):**
```sql
-- Sprawdź obecny stan
SELECT id, name, slug, is_active, is_default 
FROM certificate_templates 
WHERE id = 5;

-- Sprawdź czy istnieje już szablon z slug 'default'
SELECT id, name, slug 
FROM certificate_templates 
WHERE slug = 'default' AND id != 5;

-- Jeśli istnieje inny szablon z slug 'default', najpierw zmień jego slug:
-- UPDATE certificate_templates SET slug = 'default-old' WHERE slug = 'default' AND id != 5;

-- Zmień slug szablonu ID=5
UPDATE certificate_templates 
SET slug = 'default' 
WHERE id = 5;

-- Weryfikacja
SELECT id, name, slug, is_active, is_default 
FROM certificate_templates 
WHERE id = 5;
```

### 2. Sprawdź kursy używające szablonu

```sql
SELECT id, title, certificate_template_id 
FROM courses 
WHERE certificate_template_id = 5;
```

Kursy automatycznie będą używać nowego slug (ponieważ używają `certificate_template_id`, nie slug).

### 3. Opcjonalnie: Usuń stary plik blade

Po weryfikacji, że wszystko działa, możesz usunąć:
```bash
rm /home/hostnet/WEB-APP/pne-certificate-generator/resources/views/certificates/default-kopia.blade.php
```

## 🔍 Weryfikacja

1. **Sprawdź w bazie:**
   ```sql
   SELECT * FROM certificate_templates WHERE id = 5;
   ```
   Powinno pokazać: `slug = 'default'`

2. **Sprawdź plik blade:**
   ```bash
   ls -la ../pne-certificate-generator/resources/views/certificates/default.blade.php
   ```

3. **Przetestuj generowanie certyfikatu:**
   - Przejdź do szkolenia używającego szablonu ID=5
   - Wygeneruj certyfikat
   - Sprawdź czy używa poprawnego szablonu

## ⚠️ Uwagi

- Jeśli istnieje już szablon z slug `default`, najpierw zmień jego slug na coś innego
- Kursy używają `certificate_template_id`, więc nie wymagają aktualizacji
- Plik blade został już zaktualizowany automatycznie
- Backup starego pliku `default.blade.php` został utworzony

## 📝 Pliki SQL

Gotowy skrypt SQL znajduje się w: `CHANGE_TEMPLATE_SLUG_SQL.sql`










