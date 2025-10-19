# 📚 Migracja uczestników do form_order_participants

## 🎯 Cel

Przejście z systemu jednego uczestnika na zamówienie do systemu wielu uczestników na zamówienie.

**Strategia:** Zachowanie pełnej kompatybilności wstecznej - stara tabela `form_orders` nadal działa, ale równolegle tworzymy nową strukturę w `form_order_participants`.

---

## 📋 Kroki migracji

### Krok 1: Uruchomienie migracji bazy danych

```bash
# Utwórz tabelę form_order_participants
sail artisan migrate
```

Ta komenda utworzy nową tabelę:
- `form_order_participants` - tabela uczestników z rozdzielonymi polami imię/nazwisko

---

### Krok 2: Jednorazowa migracja istniejących danych

#### Symulacja (test bez zapisywania):
```bash
sail artisan formorders:migrate-participants --dry-run
```

#### Migracja z limitem (testowanie):
```bash
sail artisan formorders:migrate-participants --limit=10 --dry-run
```

#### Pełna migracja (zapisuje do bazy):
```bash
sail artisan formorders:migrate-participants
```

#### Opcje:
- `--dry-run` - Symulacja bez zapisywania do bazy
- `--limit=N` - Przetwórz tylko N rekordów (przydatne do testów)
- `-v` - Tryb verbose (wyświetla przykłady co 50 rekordów)

---

### Krok 3: Automatyczny zapis dla nowych zamówień

Od teraz każde **NOWE** zamówienie będzie automatycznie zapisywane w obu miejscach:
1. `form_orders` - stary format (dla kompatybilności)
2. `form_order_participants` - nowy format (rozdzielone imię/nazwisko)

**Mechanizm:** `FormOrderObserver` automatycznie tworzy rekord w `form_order_participants` przy każdym nowym zamówieniu.

---

## 🔍 Jak działa normalizacja?

### Zasady przetwarzania:

1. **Rozbicie na imię i nazwisko:**
   ```
   "Jan Kowalski" → firstname: "Jan", lastname: "Kowalski"
   "Jan Maria Kowalski" → firstname: "Jan Maria", lastname: "Kowalski"
   "Jan" → firstname: "Jan", lastname: "Jan"
   ```

2. **Normalizacja wielkości liter:**
   ```
   "ADAM KOWALSKI" → "Adam Kowalski"
   "adam kowalski" → "Adam Kowalski"
   "jAn KoWaLsKi" → "Jan Kowalski"
   ```

3. **Nazwiska dwuczłonowe:**
   ```
   "Anna KOWALSKA-NOWAK" → firstname: "Anna", lastname: "Kowalska-Nowak"
   "JAN MARIA NOWAK-KOWALSKI" → firstname: "Jan Maria", lastname: "Nowak-Kowalski"
   ```

4. **Usuwanie zbędnych spacji:**
   ```
   "Jan    Kowalski" → "Jan Kowalski"
   "  Jan Kowalski  " → "Jan Kowalski"
   ```

---

## 📊 Struktura bazy danych

### Tabela: `form_order_participants`

```sql
CREATE TABLE form_order_participants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    form_order_id BIGINT NOT NULL,
    
    participant_firstname VARCHAR(100) NOT NULL,
    participant_lastname VARCHAR(100) NOT NULL,
    participant_email VARCHAR(255) NOT NULL,
    
    is_primary BOOLEAN DEFAULT 0,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (form_order_id) REFERENCES form_orders(id) ON DELETE CASCADE,
    INDEX idx_form_order (form_order_id),
    INDEX idx_lastname (participant_lastname),
    INDEX idx_email (participant_email)
);
```

### Pola:
- `form_order_id` - Klucz obcy do `form_orders`
- `participant_firstname` - Imię uczestnika (znormalizowane)
- `participant_lastname` - Nazwisko uczestnika (znormalizowane)
- `participant_email` - Email uczestnika
- `is_primary` - Czy to główny uczestnik (1 = tak, 0 = nie)
- `created_at`, `updated_at` - Timestampy

---

## 🔗 Relacje w modelach

### Model FormOrder:

```php
// Pobranie wszystkich uczestników
$order->participants; // Collection<FormOrderParticipant>

// Pobranie głównego uczestnika
$order->primaryParticipant; // FormOrderParticipant

// Liczba uczestników
$order->participants_count; // int
```

### Model FormOrderParticipant:

```php
// Pobranie zamówienia
$participant->formOrder; // FormOrder

// Pełne imię i nazwisko
$participant->full_name; // "Jan Kowalski"

// Format formalny (nazwisko, imię)
$participant->formal_name; // "Kowalski Jan"

// Inicjały
$participant->initials; // "J.K."
```

---

## ✅ Weryfikacja migracji

### 1. Sprawdź liczbę zmigrowanych rekordów:

```sql
-- Liczba zamówień z uczestnikiem w starej tabeli
SELECT COUNT(*) FROM form_orders 
WHERE participant_name IS NOT NULL AND participant_name != '';

-- Liczba uczestników w nowej tabeli
SELECT COUNT(*) FROM form_order_participants WHERE is_primary = 1;

-- Powinny być identyczne!
```

### 2. Sprawdź przykładowe rekordy:

```sql
SELECT 
    fo.id,
    fo.participant_name AS 'Stary format',
    CONCAT(fop.participant_firstname, ' ', fop.participant_lastname) AS 'Nowy format',
    fop.participant_firstname AS 'Imię',
    fop.participant_lastname AS 'Nazwisko'
FROM form_orders fo
LEFT JOIN form_order_participants fop ON fo.id = fop.form_order_id AND fop.is_primary = 1
WHERE fo.participant_name IS NOT NULL
LIMIT 20;
```

### 3. Sprawdź normalizację:

```sql
-- Przykłady normalizacji
SELECT 
    fo.participant_name AS 'Oryginał',
    fop.participant_firstname AS 'Imię',
    fop.participant_lastname AS 'Nazwisko',
    CONCAT(fop.participant_firstname, ' ', fop.participant_lastname) AS 'Znormalizowane'
FROM form_orders fo
JOIN form_order_participants fop ON fo.id = fop.form_order_id AND fop.is_primary = 1
WHERE fo.participant_name REGEXP '[A-Z]{2,}|  +' -- Znajdź te, które miały duże litery lub podwójne spacje
LIMIT 20;
```

---

## 🚀 Co dalej?

### Przyszłe kroki (po testach):

1. **Formularz z wieloma uczestnikami** - dodanie możliwości zgłaszania wielu osób
2. **Automatyczne przeliczanie ceny** - cena × liczba uczestników
3. **Raporty uczestników** - lista wszystkich uczestników szkoleń
4. **Certyfikaty dla uczestników** - generowanie dla każdego uczestnika z osobna
5. **Usunięcie starych pól** - po 100% migracji można usunąć `participant_name` z `form_orders`

---

## 🐛 Troubleshooting

### Problem: "Duplicate entry for key 'idx_form_order'"
**Rozwiązanie:** Uczestnik już istnieje w nowej tabeli. Pomiń lub usuń duplikat:
```sql
DELETE FROM form_order_participants WHERE form_order_id = <ID> AND is_primary = 1;
```

### Problem: "Nie udało się przetworzyć nazwy"
**Rozwiązanie:** Sprawdź dane w starym rekordzie:
```sql
SELECT id, participant_name FROM form_orders WHERE id = <ID>;
```
Możliwe przyczyny:
- Puste pole
- Niezwykłe znaki
- Bardzo długie imię/nazwisko

### Problem: Observer nie działa dla nowych zamówień
**Rozwiązanie:** Sprawdź czy Observer jest zarejestrowany:
```php
// app/Providers/AppServiceProvider.php
FormOrder::observe(FormOrderObserver::class);
```
Wyczyść cache:
```bash
sail artisan optimize:clear
```

---

## 📞 Pytania?

Jeśli masz pytania lub napotkasz problemy, sprawdź logi:
```bash
sail artisan pail
# lub
tail -f storage/logs/laravel.log
```

---

**Data utworzenia:** 2025-10-19  
**Wersja:** 1.0  
**Status:** ✅ Gotowe do użycia

