# 📊 Plan wdrożenia systemu przychodów księgowych

## 🎯 Cel
Uproszczony system wprowadzania przychodów na dany miesiąc z możliwością wyświetlania danych w postaci wykresu porównawczego.

## 📋 Faza 1: Struktura bazy danych

### 1.1 Migracja tabeli `revenue_records`
**Plik:** `database/migrations/YYYY_MM_DD_HHMMSS_create_revenue_records_table.php`

**Struktura:**
```php
Schema::create('revenue_records', function (Blueprint $table) {
    $table->id();
    $table->year('year');                    // Rok (np. 2024)
    $table->tinyInteger('month');            // Miesiąc (1-12)
    $table->decimal('amount', 15, 2);       // Kwota przychodu
    $table->text('notes')->nullable();      // Opcjonalne notatki
    $table->string('source')->nullable();    // Źródło (np. "manual", "ifirma" - dla przyszłości)
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Kto wprowadził
    $table->timestamps();
    $table->softDeletes();
    
    // Indeksy dla szybkiego wyszukiwania
    $table->index(['year', 'month']);
    $table->unique(['year', 'month']); // Jeden rekord na miesiąc
});
```

**Uwagi:**
- Unikalność `year + month` zapobiega duplikatom
- `softDeletes` pozwala na przywracanie usuniętych rekordów
- `source` przygotowane na przyszłą integrację z iFirma

---

## 📋 Faza 2: Model Eloquent

### 2.1 Model `RevenueRecord`
**Plik:** `app/Models/RevenueRecord.php`

**Funkcje:**
- Casty dla `amount` (decimal), `year` (integer), `month` (integer)
- Accessory:
  - `formatted_amount` - formatowanie kwoty (np. "12 345,67 zł")
  - `month_name` - nazwa miesiąca po polsku
  - `period_label` - etykieta okresu (np. "Styczeń 2024")
- Scopes:
  - `forYear($year)` - filtrowanie po roku
  - `forMonth($year, $month)` - filtrowanie po konkretnym miesiącu
  - `recent($limit)` - ostatnie N rekordów
- Metody statyczne:
  - `getTotalForYear($year)` - suma przychodów za rok
  - `getTotalForMonth($year, $month)` - suma za miesiąc
  - `getMonthlyData($year)` - dane miesięczne dla roku (dla wykresu)

---

## 📋 Faza 3: Formularz wprowadzania danych

### 3.1 Kontroler - rozszerzenie `AccountingController`
**Metody:**
- `dataEntryIndex()` - wyświetlenie formularza + lista istniejących rekordów
- `dataEntryStore(Request $request)` - zapisanie nowego rekordu
- `dataEntryUpdate(Request $request, $id)` - aktualizacja istniejącego rekordu
- `dataEntryDestroy($id)` - usunięcie rekordu (soft delete)

### 3.2 Request Validation
**Plik:** `app/Http/Requests/StoreRevenueRecordRequest.php`
**Plik:** `app/Http/Requests/UpdateRevenueRecordRequest.php`

**Walidacja:**
- `year`: required|integer|min:2000|max:2100
- `month`: required|integer|min:1|max:12
- `amount`: required|numeric|min:0|max:999999999.99
- `notes`: nullable|string|max:1000
- Unikalność: `unique:revenue_records,year,month` (z wykluczeniem aktualnego rekordu przy edycji)

### 3.3 Widok formularza
**Plik:** `resources/views/accounting/data-entry/index.blade.php`

**Elementy:**
1. **Formularz wprowadzania:**
   - Select: Rok (ostatnie 5 lat + możliwość wpisania)
   - Select: Miesiąc (1-12 z nazwami po polsku)
   - Input: Kwota (z walidacją i formatowaniem)
   - Textarea: Notatki (opcjonalne)
   - Przycisk: Zapisz

2. **Tabela istniejących rekordów:**
   - Kolumny: Rok, Miesiąc, Kwota, Notatki, Data wprowadzenia, Akcje
   - Możliwość edycji i usuwania
   - Sortowanie po dacie (najnowsze na górze)
   - Filtrowanie po roku

3. **Komunikaty:**
   - Sukces po zapisaniu
   - Błędy walidacji
   - Potwierdzenie przed usunięciem

---

## 📋 Faza 4: Wykres na stronie raportów

### 4.1 Kontroler - rozszerzenie `AccountingController`
**Metoda:** `reportsIndex()`

**Logika:**
- Pobranie danych przychodów z ostatnich 12 miesięcy (lub wybranego zakresu)
- Przygotowanie danych dla Chart.js:
  - Etykiety: nazwy miesięcy (np. "Styczeń 2024")
  - Wartości: kwoty przychodów
  - Opcjonalnie: suma roczna, średnia miesięczna

### 4.2 Widok raportów
**Plik:** `resources/views/accounting/reports/index.blade.php`

**Elementy:**
1. **Filtry:**
   - Select: Zakres lat (np. ostatnie 2-3 lata)
   - Przycisk: Odśwież wykres

2. **Wykres Chart.js:**
   - Typ: `line` (linia) lub `bar` (słupkowy)
   - Oś X: Miesiące
   - Oś Y: Kwoty w PLN
   - Tooltips z dokładnymi wartościami
   - Responsywny design

3. **Statystyki:**
   - Suma za wybrany okres
   - Średnia miesięczna
   - Najlepszy miesiąc
   - Najsłabszy miesiąc
   - Trend (wzrost/spadek względem poprzedniego okresu)

4. **Tabela szczegółowa:**
   - Wszystkie miesiące z kwotami
   - Możliwość eksportu do CSV/Excel (opcjonalnie)

---

## 📋 Faza 5: Routing

### 5.1 Routes w `web.php`
```php
Route::prefix('accounting')->name('accounting.')->group(function () {
    // Raporty
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [AccountingController::class, 'reportsIndex'])->name('index');
    });
    
    // Wprowadź dane
    Route::prefix('data-entry')->name('data-entry.')->group(function () {
        Route::get('/', [AccountingController::class, 'dataEntryIndex'])->name('index');
        Route::post('/', [AccountingController::class, 'dataEntryStore'])->name('store');
        Route::put('/{id}', [AccountingController::class, 'dataEntryUpdate'])->name('update');
        Route::delete('/{id}', [AccountingController::class, 'dataEntryDestroy'])->name('destroy');
    });
});
```

---

## 📋 Kolejność implementacji

### Krok 1: Baza danych
1. ✅ Utworzenie migracji `create_revenue_records_table`
2. ✅ Uruchomienie migracji: `sail artisan migrate`

### Krok 2: Model
1. ✅ Utworzenie modelu `RevenueRecord`
2. ✅ Dodanie castów, accessorów, scope'ów
3. ✅ Test w Tinker

### Krok 3: Formularz wprowadzania
1. ✅ Rozszerzenie `AccountingController` o metody CRUD
2. ✅ Utworzenie Request classes dla walidacji
3. ✅ Aktualizacja widoku `data-entry/index.blade.php`
4. ✅ Dodanie routingu

### Krok 4: Wykres raportów
1. ✅ Rozszerzenie metody `reportsIndex()` w kontrolerze
2. ✅ Aktualizacja widoku `reports/index.blade.php`
3. ✅ Implementacja wykresu Chart.js
4. ✅ Dodanie statystyk

### Krok 5: Testy i optymalizacja
1. ✅ Test wprowadzania danych
2. ✅ Test wyświetlania wykresu
3. ✅ Test walidacji
4. ✅ Test edge cases (brak danych, jeden rekord, itp.)

---

## 🔮 Przyszłość: Integracja z iFirma API

### Przygotowanie struktury:
- Pole `source` w tabeli już przygotowane
- Możliwość rozszerzenia o:
  - `external_id` - ID faktury w iFirma
  - `synced_at` - data synchronizacji
  - `sync_status` - status synchronizacji

### Planowane funkcje:
- Automatyczna synchronizacja faktur z iFirma
- Ręczne wprowadzanie jako backup/uzupełnienie
- Oznaczenie źródła danych na wykresie (różne kolory)

---

## 📝 Uwagi techniczne

### Formatowanie kwot:
- W bazie: `decimal(15, 2)` - dokładność do groszy
- W formularzu: input type="number" step="0.01"
- W widoku: formatowanie polskie (spacja jako separator tysięcy, przecinek jako separator dziesiętny)

### Walidacja:
- Unikalność `year + month` zapobiega duplikatom
- Sprawdzanie czy miesiąc nie jest w przyszłości (opcjonalnie)
- Walidacja kwoty (nie może być ujemna)

### Bezpieczeństwo:
- Wszystkie operacje wymagają autoryzacji (`auth` middleware)
- CSRF protection dla formularzy
- Soft delete dla możliwości przywrócenia

### Wydajność:
- Indeksy na `year` i `month` dla szybkich zapytań
- Cache dla statystyk (opcjonalnie)
- Lazy loading dla dużych zbiorów danych

---

## ✅ Checklist wdrożenia

- [ ] Migracja bazy danych
- [ ] Model RevenueRecord
- [ ] Request classes (Store/Update)
- [ ] Kontroler - metody CRUD
- [ ] Widok formularza wprowadzania
- [ ] Widok raportów z wykresem
- [ ] Routing
- [ ] Testy funkcjonalne
- [ ] Dokumentacja użytkownika (opcjonalnie)

---

**Status:** 📝 Plan gotowy do implementacji
**Data utworzenia:** {{ date('Y-m-d') }}

