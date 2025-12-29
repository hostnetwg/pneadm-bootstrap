# Rozwiązania importu szkół z RSPO do Sendy

## 📋 Przegląd

System umożliwia pobieranie szkół z API RSPO (https://api-rspo.men.gov.pl/), wyciąganie adresów e-mail i nazw szkół, oraz automatyczne tworzenie list w Sendy (Brand ID: 4 - NODN Platforma Nowoczesnej Edukacji).

## 🎯 Utworzone komponenty

### 1. Serwisy

#### `RSPOImportService` (`app/Services/RSPOImportService.php`)
- Pobieranie szkół z RSPO API z filtrowaniem
- Wyciąganie emaili i nazw szkół
- Grupowanie szkół według różnych kryteriów (województwo, typ, miejscowość)
- Walidacja danych

#### `SendyService` (rozszerzony)
- `createList()` - tworzenie nowych list w Sendy
- `bulkSubscribe()` - masowe dodawanie subskrybentów

---

## 🚀 Rozwiązanie 1: Artisan Command (CLI)

### Opis
Najprostsze rozwiązanie - jednorazowy import przez wiersz poleceń. Idealne do:
- Jednorazowych importów
- Automatyzacji przez cron
- Testowania przed pełnym importem

### Użycie

#### Podstawowy import (wszystkie szkoły, grupowanie po województwie):
```bash
sail artisan rspo:import-to-sendy \
  --from-email=noreply@pnedu.pl \
  --from-name="NODN" \
  --reply-to=info@pnedu.pl
```

#### Import z filtrami:
```bash
# Tylko szkoły podstawowe z Mazowsza
sail artisan rspo:import-to-sendy \
  --type-id=90 \
  --wojewodztwo="mazowieckie" \
  --from-email=noreply@pnedu.pl

# Grupowanie po miejscowości
sail artisan rspo:import-to-sendy \
  --group-by=miejscowosc \
  --from-email=noreply@pnedu.pl
```

#### Tryb testowy (dry-run):
```bash
sail artisan rspo:import-to-sendy \
  --dry-run \
  --limit=50 \
  --from-email=noreply@pnedu.pl
```

### Dostępne opcje:

| Opcja | Opis | Domyślna wartość |
|-------|------|------------------|
| `--brand-id` | ID brandu w Sendy | 4 (NODN) |
| `--type-id` | ID typu podmiotu z RSPO | - |
| `--wojewodztwo` | Nazwa województwa | - |
| `--group-by` | Grupowanie (wojewodztwo/typ/miejscowosc) | wojewodztwo |
| `--list-prefix` | Prefiks nazwy listy | "RSPO - " |
| `--from-name` | Nazwa nadawcy | "NODN" |
| `--from-email` | Email nadawcy | **WYMAGANE** |
| `--reply-to` | Email reply-to | = from-email |
| `--dry-run` | Tryb testowy | false |
| `--limit` | Limit szkół (dla testów) | - |

### Przykładowe scenariusze:

#### 1. Import wszystkich szkół podstawowych
```bash
sail artisan rspo:import-to-sendy \
  --type-id=90 \
  --from-email=noreply@pnedu.pl \
  --list-prefix="Szkoły Podstawowe - "
```

#### 2. Import przedszkoli z konkretnego województwa
```bash
sail artisan rspo:import-to-sendy \
  --type-id=91 \
  --wojewodztwo="mazowieckie" \
  --from-email=noreply@pnedu.pl \
  --list-prefix="Przedszkola Mazowieckie - "
```

#### 3. Test z małą próbką
```bash
sail artisan rspo:import-to-sendy \
  --dry-run \
  --limit=10 \
  --from-email=noreply@pnedu.pl
```

---

## 🌐 Rozwiązanie 2: Interfejs Web (Kontroler)

### Opis
Interaktywny interfejs web z formularzem do wyboru kryteriów importu. Idealne do:
- Regularnych importów przez użytkowników
- Wizualnego wyboru kryteriów
- Podglądu wyników przed importem

### Funkcjonalności:
- Formularz wyboru kryteriów (typ szkoły, województwo)
- Wybór grupowania list
- Podgląd statystyk przed importem
- Postęp importu w czasie rzeczywistym
- Historia importów

### Implementacja (do stworzenia):

#### Kontroler: `RSPOImportController`
```php
// Metody:
- index() - formularz importu
- preview() - podgląd wyników przed importem
- import() - wykonanie importu
- history() - historia importów
```

#### Trasy:
```php
Route::prefix('rspo-import')->name('rspo-import.')->group(function () {
    Route::get('/', [RSPOImportController::class, 'index'])->name('index');
    Route::post('/preview', [RSPOImportController::class, 'preview'])->name('preview');
    Route::post('/import', [RSPOImportController::class, 'import'])->name('import');
    Route::get('/history', [RSPOImportController::class, 'history'])->name('history');
});
```

---

## ⚙️ Rozwiązanie 3: Zaplanowane zadanie (Scheduled Task)

### Opis
Automatyczne odświeżanie list w Sendy według harmonogramu. Idealne do:
- Regularnych aktualizacji danych
- Synchronizacji z RSPO
- Automatycznego zarządzania listami

### Implementacja w `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    // Co tydzień w niedzielę o 2:00
    $schedule->command('rspo:import-to-sendy', [
        '--from-email' => config('mail.from.address'),
        '--group-by' => 'wojewodztwo'
    ])->weekly()->sundays()->at('02:00');
}
```

---

## 📊 Rozwiązanie 4: Kombinacja (Rekomendowane)

### Opis
Połączenie wszystkich rozwiązań:
- **Artisan Command** - do automatyzacji i jednorazowych importów
- **Interfejs Web** - do interaktywnych importów przez użytkowników
- **Scheduled Task** - do automatycznych aktualizacji

### Zalety:
- Elastyczność użycia
- Różne scenariusze użycia
- Pełna kontrola nad procesem

---

## 🔧 Konfiguracja

### 1. Zmienne środowiskowe (`.env`):
```env
SENDY_API_KEY=your_api_key
SENDY_BASE_URL=https://sendyhost.net
SENDY_BRAND_ID=4

# Domyślne wartości dla importu
RSPO_IMPORT_FROM_EMAIL=noreply@pnedu.pl
RSPO_IMPORT_FROM_NAME=NODN
RSPO_IMPORT_REPLY_TO=info@pnedu.pl
```

### 2. Konfiguracja Sendy (`config/sendy.php`):
```php
return [
    'api_key' => env('SENDY_API_KEY'),
    'base_url' => env('SENDY_BASE_URL'),
    'brand_id' => env('SENDY_BRAND_ID', 4),
];
```

---

## 📝 Przykłady użycia

### Przykład 1: Import wszystkich szkół podstawowych
```bash
# 1. Sprawdź dostępne typy
curl "https://api-rspo.men.gov.pl/api/typ/" -H "accept: application/json"

# 2. Znajdź ID dla "Szkoła podstawowa" (np. 90)

# 3. Wykonaj import
sail artisan rspo:import-to-sendy \
  --type-id=90 \
  --from-email=noreply@pnedu.pl \
  --list-prefix="SP - "
```

### Przykład 2: Import z podziałem na województwa
```bash
sail artisan rspo:import-to-sendy \
  --group-by=wojewodztwo \
  --from-email=noreply@pnedu.pl \
  --list-prefix="RSPO - "
```

### Przykład 3: Test z małą próbką
```bash
sail artisan rspo:import-to-sendy \
  --dry-run \
  --limit=20 \
  --wojewodztwo="mazowieckie" \
  --from-email=noreply@pnedu.pl
```

---

## 🛠️ Rozwiązywanie problemów

### Problem: Brak emaili w wynikach
**Rozwiązanie:** Sprawdź czy szkoły w RSPO mają wypełnione pole `email`. Nie wszystkie placówki mają adresy email.

### Problem: Timeout przy pobieraniu dużej liczby szkół
**Rozwiązanie:** Użyj filtrów (`--type-id`, `--wojewodztwo`) aby ograniczyć zakres lub zwiększ timeout w `RSPOImportService`.

### Problem: Błąd tworzenia listy w Sendy
**Rozwiązanie:** 
- Sprawdź czy API key jest poprawny
- Sprawdź czy brand ID (4) istnieje
- Sprawdź logi: `storage/logs/laravel.log`

### Problem: Duplikaty subskrybentów
**Rozwiązanie:** Sendy automatycznie obsługuje duplikaty - nie dodaje ponownie istniejących emaili.

---

## 📈 Statystyki i monitoring

### Logi
Wszystkie operacje są logowane do `storage/logs/laravel.log`:
- Pobieranie danych z RSPO
- Tworzenie list w Sendy
- Dodawanie subskrybentów
- Błędy i ostrzeżenia

### Metryki
Command wyświetla podsumowanie:
- Liczba utworzonych list
- Liczba dodanych subskrybentów
- Liczba błędów

---

## 🔐 Bezpieczeństwo

1. **API Keys** - przechowuj w `.env`, nie commituj do repo
2. **Walidacja emaili** - wszystkie emaile są walidowane przed dodaniem
3. **Rate Limiting** - opóźnienie 0.1s między subskrypcjami aby nie przeciążać API
4. **Dry-run mode** - zawsze testuj z `--dry-run` przed rzeczywistym importem

---

## 🚀 Następne kroki

1. **Przetestuj** z `--dry-run` i małą próbką
2. **Sprawdź** utworzone listy w Sendy
3. **Dostosuj** kryteria grupowania do swoich potrzeb
4. **Skonfiguruj** scheduled task dla automatycznych aktualizacji
5. **Stwórz** interfejs web dla użytkowników (Rozwiązanie 2)

---

## 📚 Dokumentacja API

- **RSPO API**: https://api-rspo.men.gov.pl/
- **Sendy API**: https://sendy.co/api

---

## 💡 Wskazówki

1. Zawsze używaj `--dry-run` przed pierwszym importem
2. Zacznij od małej próbki (`--limit=10`)
3. Sprawdź typy podmiotów w RSPO przed importem
4. Monitoruj logi podczas importu
5. Regularnie aktualizuj dane (szkoły mogą się zmieniać)

---

## ✅ Checklist przed importem

- [ ] Sprawdź konfigurację Sendy API
- [ ] Zweryfikuj brand ID (4)
- [ ] Przetestuj z `--dry-run`
- [ ] Sprawdź przykładowe dane z RSPO
- [ ] Ustaw odpowiednie `--from-email`
- [ ] Wybierz strategię grupowania
- [ ] Sprawdź logi po teście
- [ ] Wykonaj pełny import





