# Migracja plików CSV ankiet na produkcji

## Problem
Na serwerze produkcyjnym pliki CSV ankiet mogą być zapisane w starym katalogu `survey_files` zamiast w nowym ujednoliconym katalogu `surveys/imports`.

## Przyczyna
- Stary kod używał katalogu `survey_files`
- Nowy kod używa `surveys/imports` dla ujednolicenia
- Po aktualizacji kodu nowe pliki trafiają do właściwego katalogu, ale stare pozostają w starym miejscu

## Rozwiązanie

### 1. Sprawdzenie obecnego stanu

Najpierw sprawdź stan plików na serwerze:

```bash
php artisan survey:check-files
```

Aby zobaczyć szczegóły:

```bash
# Pokaż ankiety ze starymi ścieżkami
php artisan survey:check-files --show-old-paths

# Pokaż ankiety z brakującymi plikami
php artisan survey:check-files --show-missing
```

### 2. Test migracji (dry-run)

Przed faktyczną migracją wykonaj test:

```bash
php artisan survey:migrate-files --dry-run
```

Ta komenda pokaże co zostanie zrobione bez wykonywania rzeczywistych zmian.

### 3. Wykonanie migracji

Gdy jesteś gotowy na migrację:

```bash
php artisan survey:migrate-files
```

Jeśli pliki docelowe już istnieją i chcesz je nadpisać:

```bash
php artisan survey:migrate-files --force
```

### 4. Weryfikacja po migracji

Po migracji sprawdź ponownie stan:

```bash
php artisan survey:check-files
```

## Co robi migracja?

1. **Przenosi pliki fizycznie**:
   - Z: `storage/app/private/survey_files/*`
   - Do: `storage/app/private/surveys/imports/*`

2. **Aktualizuje bazę danych**:
   - Zmienia ścieżki w kolumnie `original_file_path` tabeli `surveys`
   - `survey_files/123_file.csv` → `surveys/imports/123_file.csv`

3. **Usuwa pusty katalog**:
   - Usuwa katalog `survey_files` jeśli jest pusty i nie ma błędów

## Bezpieczeństwo

- **Backup**: Przed migracją zrób backup bazy danych i plików
- **Dry-run**: Zawsze najpierw uruchom z `--dry-run`
- **Logi**: Wszystkie błędy są logowane w Laravel logs
- **Rollback**: W razie problemów można przywrócić backup

## Monitorowanie

### Sprawdzenie struktury katalogów:
```bash
ls -la storage/app/private/
ls -la storage/app/private/surveys/
ls -la storage/app/private/surveys/imports/
```

### Sprawdzenie w bazie danych:
```sql
-- Zlicz ankiety według typu ścieżki
SELECT 
    CASE 
        WHEN original_file_path LIKE 'surveys/imports/%' THEN 'surveys/imports'
        WHEN original_file_path LIKE 'survey_files/%' THEN 'survey_files'
        ELSE 'other'
    END as path_type,
    COUNT(*) as count
FROM surveys 
WHERE original_file_path IS NOT NULL 
GROUP BY path_type;
```

## Rozwiązywanie problemów

### Błąd: "Target file already exists"
```bash
php artisan survey:migrate-files --force
```

### Sprawdzenie logów Laravel:
```bash
tail -f storage/logs/laravel.log
```

### Ręczne przeniesienie pojedynczego pliku:
```bash
# Na serwerze
mv storage/app/private/survey_files/123_file.csv storage/app/private/surveys/imports/123_file.csv
```

Następnie aktualizuj bazę danych:
```sql
UPDATE surveys 
SET original_file_path = 'surveys/imports/123_file.csv' 
WHERE original_file_path = 'survey_files/123_file.csv';
```

## Po migracji

1. Sprawdź czy wszystkie pliki są dostępne do pobrania w interfejsie
2. Przetestuj tworzenie nowych ankiet z plikami CSV
3. Sprawdź czy import przez `/courses/{id}/surveys/import` nadal działa
4. Zweryfikuj czy generowanie raportów PDF nadal działa

## Komendy dostępne

```bash
# Sprawdzenie stanu plików
php artisan survey:check-files [--show-missing] [--show-old-paths]

# Migracja plików
php artisan survey:migrate-files [--dry-run] [--force]

# Lista wszystkich komend survey
php artisan list | grep survey
```
