# 💰 System Wariantów Cenowych Kursów - Dokumentacja

## 📋 **Podsumowanie**

System wariantów cenowych pozwala na zarządzanie wieloma opcjami cenowymi dla pojedynczego kursu. Każdy kurs może mieć wiele wariantów cenowych, każdy z własną ceną, opcją promocyjną oraz typem dostępu.

**Data utworzenia:** 2025-11-20  
**Wersja:** 1.0  
**Projekt:** pneadm-bootstrap

### Główne cechy:
- ✅ Wielość wariantów cenowych dla jednego kursu
- ✅ System promocji z różnymi typami (wyłączona, bez ram czasowych, ograniczona czasowo)
- ✅ Różne typy dostępu do kursu (bezterminowy, ograniczony czasowo, od określonej daty)
- ✅ Soft delete z możliwością przywracania
- ✅ Automatyczne logowanie aktywności (Activity Log)
- ✅ Dynamiczne wyświetlanie cen (z uwzględnieniem aktywnych promocji)

---

## 🗄️ **Struktura bazy danych**

### **Tabela: `course_price_variants`**

#### **Podstawowe pola Laravel**

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | BIGINT UNSIGNED | Klucz główny, AUTO_INCREMENT |
| `course_id` | BIGINT UNSIGNED | Foreign Key → `courses(id)`, ON DELETE CASCADE |
| `created_at` | TIMESTAMP | Data i godzina utworzenia rekordu |
| `updated_at` | TIMESTAMP | Data i godzina ostatniej aktualizacji |
| `deleted_at` | TIMESTAMP NULLABLE | Data i godzina usunięcia (soft delete) |

#### **Pola wariantu cenowego**

| Kolumna | Typ | Opis |
|---------|-----|------|
| `name` | VARCHAR(255) | Nazwa wariantu cenowego (np. "Standard", "Early Bird", "VIP") |
| `description` | TEXT NULLABLE | Szczegółowy opis wariantu cenowego |
| `is_active` | BOOLEAN | Czy wariant jest aktywny i widoczny (default: TRUE) |
| `price` | DECIMAL(10,2) | Cena podstawowa wariantu w PLN (min: 0.00) |

#### **Pola promocji**

| Kolumna | Typ | Opis |
|---------|-----|------|
| `is_promotion` | BOOLEAN | Czy promocja jest włączona (default: FALSE) |
| `promotion_price` | DECIMAL(10,2) NULLABLE | Cena promocyjna w PLN (wymagane gdy is_promotion = TRUE) |
| `promotion_type` | ENUM | Typ promocji: 'disabled', 'unlimited', 'time_limited' (default: 'disabled') |
| `promotion_start` | DATETIME NULLABLE | Data i godzina rozpoczęcia promocji (wymagane dla 'time_limited') |
| `promotion_end` | DATETIME NULLABLE | Data i godzina zakończenia promocji (wymagane dla 'time_limited') |

#### **Pola typu dostępu**

| Kolumna | Typ | Opis |
|---------|-----|------|
| `access_type` | ENUM | Typ dostępu: '1', '2', '3', '4', '5' (default: '1') |
| `access_start_datetime` | DATETIME NULLABLE | Data i godzina startu dostępu (wymagane dla typów 2, 4, 5) |
| `access_end_datetime` | DATETIME NULLABLE | Data i godzina końca dostępu (wymagane dla typów 2, 4) |
| `access_duration_value` | INTEGER NULLABLE | Wartość czasu dostępu (liczba, wymagane dla typów 3, 5) |
| `access_duration_unit` | ENUM NULLABLE | Jednostka czasu: 'hours', 'days', 'months', 'years' (wymagane dla typów 3, 5) |

#### **Indeksy**

| Nazwa indeksu | Kolumny | Cel |
|---------------|---------|-----|
| PRIMARY KEY | `id` | Klucz główny |
| INDEX | `course_id` | Szybkie wyszukiwanie wariantów danego kursu |
| INDEX | `is_active` | Filtrowanie aktywnych wariantów |
| `idx_promotion_dates` | `promotion_type`, `promotion_start`, `promotion_end` | Optymalizacja zapytań o promocje czasowe |
| INDEX | `access_type` | Filtrowanie według typu dostępu |

---

## 🎯 **Typy promocji - Szczegółowy opis**

### **1. Wyłączona (disabled)**

- **promotion_type:** `'disabled'`
- **is_promotion:** może być TRUE lub FALSE
- **promotion_price:** może być ustawiona, ale nie będzie używana
- **promotion_start, promotion_end:** ignorowane

**Logika:**
- Promocja nigdy nie jest aktywna
- Zawsze używana jest cena podstawowa (`price`)
- Metoda `isPromotionActive()` zwraca `FALSE`

**Przykład użycia:**
```
Wariant z ceną podstawową 1000 PLN, gdzie promocja jest wyłączona.
Użytkownicy zawsze płacą 1000 PLN.
```

### **2. Bez ram czasowych (unlimited)**

- **promotion_type:** `'unlimited'`
- **is_promotion:** MUSI być TRUE
- **promotion_price:** MUSI być ustawiona
- **promotion_start, promotion_end:** ignorowane

**Logika:**
- Promocja jest zawsze aktywna (jeśli `is_promotion = TRUE`)
- Zawsze używana jest cena promocyjna (`promotion_price`)
- Metoda `isPromotionActive()` zwraca `TRUE` (jeśli `is_promotion = TRUE`)

**Przykład użycia:**
```
Wariant z ceną podstawową 1000 PLN, ceną promocyjną 800 PLN.
Użytkownicy zawsze płacą 800 PLN (cena promocyjna).
```

### **3. Ograniczona czasowo (time_limited)**

- **promotion_type:** `'time_limited'`
- **is_promotion:** MUSI być TRUE
- **promotion_price:** MUSI być ustawiona
- **promotion_start:** MUSI być ustawiona
- **promotion_end:** MUSI być ustawiona
- **Warunek:** `promotion_end > promotion_start`

**Logika:**
- Promocja jest aktywna tylko w określonym przedziale czasowym
- Sprawdzanie: aktualna data/czas jest między `promotion_start` a `promotion_end`
- Jeśli aktualna data/czas < `promotion_start`: promocja nieaktywna (cena podstawowa)
- Jeśli `promotion_start` <= aktualna data/czas <= `promotion_end`: promocja aktywna (cena promocyjna)
- Jeśli aktualna data/czas > `promotion_end`: promocja nieaktywna (cena podstawowa)
- Metoda `isPromotionActive()` zwraca `TRUE` tylko w przedziale czasowym

**Przykład użycia:**
```
Wariant z ceną podstawową 1000 PLN, ceną promocyjną 800 PLN.
Promocja aktywna od 2025-11-20 00:00:00 do 2025-12-31 23:59:59.

- Przed 20.11.2025: użytkownicy płacą 1000 PLN
- Od 20.11.2025 do 31.12.2025: użytkownicy płacą 800 PLN
- Po 31.12.2025: użytkownicy płacą 1000 PLN
```

---

## 🔐 **Typy dostępu do kursu - Szczegółowy opis**

### **Typ 1: Bezterminowy, z natychmiastowym dostępem**

- **access_type:** `'1'`
- **access_start_datetime:** NULL (ignorowane)
- **access_end_datetime:** NULL (ignorowane)
- **access_duration_value:** NULL (ignorowane)
- **access_duration_unit:** NULL (ignorowane)

**Logika:**
- Dostęp do kursu jest natychmiastowy (po zakupie)
- Dostęp jest bezterminowy (nigdy nie wygasa)
- Metoda `isAccessAvailable()` zawsze zwraca `TRUE`

**Przykład użycia:**
```
Uczestnik kupuje kurs i od razu otrzymuje do niego dostęp.
Dostęp nigdy nie wygasa - uczestnik może korzystać z kursu w dowolnym czasie.
```

### **Typ 2: Bezterminowy, od określonej daty**

- **access_type:** `'2'`
- **access_start_datetime:** MUSI być ustawiona
- **access_end_datetime:** MUSI być ustawiona (koniec dostępu bezterminowego)
- **access_duration_value:** NULL (ignorowane)
- **access_duration_unit:** NULL (ignorowane)
- **Warunek:** `access_end_datetime > access_start_datetime`

**Logika:**
- Dostęp do kursu rozpoczyna się w `access_start_datetime`
- Dostęp trwa do `access_end_datetime` (bezterminowy w tym przedziale)
- Metoda `isAccessAvailable()` zwraca `TRUE` jeśli aktualna data/czas jest między `access_start_datetime` a `access_end_datetime`

**Przykład użycia:**
```
Dostęp od 2025-12-01 00:00:00 do 2026-12-31 23:59:59.
Uczestnik kupuje kurs, ale może korzystać z niego dopiero od 1 grudnia 2025.
Dostęp trwa przez cały rok 2026, a następnie kończy się 31 grudnia 2026.
```

### **Typ 3: Przez określony czas, z natychmiastowym dostępem**

- **access_type:** `'3'`
- **access_start_datetime:** NULL (ignorowane)
- **access_end_datetime:** NULL (ignorowane)
- **access_duration_value:** MUSI być ustawiona (liczba > 0)
- **access_duration_unit:** MUSI być ustawiona ('hours', 'days', 'months', 'years')

**Logika:**
- Dostęp do kursu jest natychmiastowy (po zakupie)
- Dostęp trwa przez określony czas (`access_duration_value` + `access_duration_unit`)
- Czas dostępu liczony jest od momentu zakupu/aktywacji kursu dla użytkownika
- Metoda `isAccessAvailable()` zawsze zwraca `TRUE`
- Uwaga: Obliczenie daty końca dostępu odbywa się po stronie aplikacji podczas aktywacji kursu dla konkretnego użytkownika

**Przykład użycia:**
```
access_duration_value = 90, access_duration_unit = 'days'
Uczestnik kupuje kurs 2025-11-20 i od razu otrzymuje dostęp.
Dostęp trwa 90 dni, więc wygasa 2026-02-18 (90 dni od daty zakupu).
```

### **Typ 4: Od określonej daty, z ustaloną datą końca**

- **access_type:** `'4'`
- **access_start_datetime:** MUSI być ustawiona
- **access_end_datetime:** MUSI być ustawiona
- **access_duration_value:** NULL (ignorowane)
- **access_duration_unit:** NULL (ignorowane)
- **Warunek:** `access_end_datetime > access_start_datetime`

**Logika:**
- Dostęp do kursu rozpoczyna się w `access_start_datetime`
- Dostęp kończy się w `access_end_datetime`
- Metoda `isAccessAvailable()` zwraca `TRUE` jeśli aktualna data/czas jest między `access_start_datetime` a `access_end_datetime`

**Przykład użycia:**
```
access_start_datetime = 2025-12-01 00:00:00
access_end_datetime = 2025-12-31 23:59:59
Uczestnik kupuje kurs, ale może korzystać z niego tylko w grudniu 2025.
Dostęp rozpoczyna się 1 grudnia i kończy 31 grudnia.
```

### **Typ 5: Przez określony czas, od określonej daty**

- **access_type:** `'5'`
- **access_start_datetime:** MUSI być ustawiona
- **access_end_datetime:** NULL (obliczane automatycznie)
- **access_duration_value:** MUSI być ustawiona (liczba > 0)
- **access_duration_unit:** MUSI być ustawiona ('hours', 'days', 'months', 'years')

**Logika:**
- Dostęp do kursu rozpoczyna się w `access_start_datetime`
- Dostęp trwa przez określony czas (`access_duration_value` + `access_duration_unit`)
- Data końca dostępu jest obliczana automatycznie przez metodę `calculateAccessEndDate()` poprzez dodanie `access_duration_value` i `access_duration_unit` do `access_start_datetime`
- Metoda `isAccessAvailable()` zwraca `TRUE` jeśli aktualna data/czas jest między `access_start_datetime` a obliczoną datą końca

**Przykład użycia:**
```
access_start_datetime = 2025-12-01 00:00:00
access_duration_value = 30
access_duration_unit = 'days'

Obliczona data końca: 2025-12-31 00:00:00 (30 dni od 1 grudnia)

Uczestnik kupuje kurs, ale może korzystać z niego dopiero od 1 grudnia 2025.
Dostęp trwa 30 dni, więc kończy się 31 grudnia 2025.
```

---

## 🏗️ **Model Eloquent - CoursePriceVariant**

### **Relacje**

```php
public function course()
{
    return $this->belongsTo(Course::class);
}
```

**Opis:** Każdy wariant cenowy należy do jednego kursu  
**Przykład użycia:** `$variant->course`

### **Traity**

- `HasFactory` - Generowanie fabryk i seedów dla testów
- `SoftDeletes` - Obsługa soft delete (`deleted_at`)
- `LogsActivity` - Automatyczne logowanie zmian w Activity Log

### **Metody pomocnicze**

#### **isPromotionActive(): bool**

Sprawdza czy promocja jest aktualnie aktywna.

**Zwraca:** `TRUE` jeśli promocja aktywna, `FALSE` w przeciwnym razie

**Logika:**
- Jeśli `is_promotion = FALSE` → zwraca `FALSE`
- Jeśli `promotion_type = 'disabled'` → zwraca `FALSE`
- Jeśli `promotion_type = 'unlimited'` → zwraca `TRUE` (jeśli `is_promotion = TRUE`)
- Jeśli `promotion_type = 'time_limited'` → sprawdza czy aktualna data/czas jest między `promotion_start` a `promotion_end`

**Przykład użycia:**
```php
if ($variant->isPromotionActive()) {
    $price = $variant->promotion_price;
} else {
    $price = $variant->price;
}
```

#### **getCurrentPrice(): float**

Zwraca aktualną cenę (promocyjną jeśli aktywna, w przeciwnym razie podstawową).

**Zwraca:** Liczba zmiennoprzecinkowa (float)

**Logika:**
- Jeśli promocja jest aktywna (`isPromotionActive() = TRUE`) i `promotion_price` jest ustawiona → zwraca `promotion_price`
- W przeciwnym razie → zwraca `price`

**Przykład użycia:**
```php
$currentPrice = $variant->getCurrentPrice();
echo number_format($currentPrice, 2, ',', ' ') . ' PLN';
```

#### **calculateAccessEndDate(): ?Carbon**

Oblicza datę końca dostępu dla typu 5.

**Zwraca:** Obiekt Carbon z datą końca lub `NULL`

**Logika:**
- Działa tylko dla `access_type = '5'`
- Jeśli brak wymaganych danych → zwraca `NULL`
- Oblicza datę końca dodając `access_duration_value` i `access_duration_unit` do `access_start_datetime`
- Dla innych typów dostępu zwraca `NULL` (lub `access_end_datetime` jeśli ustawione)

**Jednostki czasu:**
- `'hours'` → `addHours($value)`
- `'days'` → `addDays($value)`
- `'months'` → `addMonths($value)`
- `'years'` → `addYears($value)`

**Przykład użycia:**
```php
$endDate = $variant->calculateAccessEndDate();
if ($endDate) {
    echo 'Dostęp kończy się: ' . $endDate->format('d.m.Y H:i');
}
```

#### **isAccessAvailable(): bool**

Sprawdza czy dostęp do kursu jest aktualnie dostępny.

**Zwraca:** `TRUE` jeśli dostęp dostępny, `FALSE` w przeciwnym razie

**Logika według typu dostępu:**
- Typ `'1'`: zawsze `TRUE` (bezterminowy, natychmiastowy)
- Typ `'2'`: sprawdza czy aktualna data/czas jest między `access_start_datetime` a `access_end_datetime`
- Typ `'3'`: zawsze `TRUE` (czas liczony od momentu zakupu/aktywacji)
- Typ `'4'`: sprawdza czy aktualna data/czas jest między `access_start_datetime` a `access_end_datetime`
- Typ `'5'`: sprawdza czy aktualna data/czas jest między `access_start_datetime` a obliczoną datą końca (`calculateAccessEndDate()`)

**Przykład użycia:**
```php
if ($variant->isAccessAvailable()) {
    echo 'Dostęp do kursu jest dostępny';
} else {
    echo 'Dostęp do kursu jeszcze nie rozpoczął się lub już się zakończył';
}
```

#### **getAccessTypeName(): string**

Zwraca czytelną nazwę typu dostępu.

**Zwraca:** String z nazwą typu dostępu

**Mapowanie:**
- `'1'` → `'Bezterminowy, z natychmiastowym dostępem'`
- `'2'` → `'Bezterminowy, od określonej daty'`
- `'3'` → `'Przez określony czas, z natychmiastowym dostępem'`
- `'4'` → `'Od określonej daty, z ustaloną datą końca'`
- `'5'` → `'Przez określony czas, od określonej daty'`
- inne → `'Nieznany typ'`

**Przykład użycia:**
```php
echo $variant->getAccessTypeName();
```

#### **getPromotionTypeName(): string**

Zwraca czytelną nazwę typu promocji.

**Zwraca:** String z nazwą typu promocji

**Mapowanie:**
- `'disabled'` → `'Wyłączona'`
- `'unlimited'` → `'Bez ram czasowych'`
- `'time_limited'` → `'Ograniczona czasowo'`
- inne → `'Nieznany typ promocji'`

**Przykład użycia:**
```php
echo $variant->getPromotionTypeName();
```

---

## 🎮 **Kontroler - CoursePriceVariantController**

### **Endpointy (Routy)**

Wszystkie routy są prefiksowane: `/courses/{courseId}/price-variants`

| Metoda | Endpoint | Nazwa routy | Opis |
|--------|----------|-------------|------|
| GET | `/courses/{courseId}/price-variants/create` | `courses.price-variants.create` | Wyświetla formularz tworzenia nowego wariantu cenowego |
| POST | `/courses/{courseId}/price-variants` | `courses.price-variants.store` | Zapisuje nowy wariant cenowy |
| GET | `/courses/{courseId}/price-variants/{id}/edit` | `courses.price-variants.edit` | Wyświetla formularz edycji wariantu cenowego |
| PUT | `/courses/{courseId}/price-variants/{id}` | `courses.price-variants.update` | Aktualizuje wariant cenowy |
| DELETE | `/courses/{courseId}/price-variants/{id}` | `courses.price-variants.destroy` | Usuwa wariant cenowy (soft delete), zwraca JSON |
| POST | `/courses/{courseId}/price-variants/{id}/restore` | `courses.price-variants.restore` | Przywraca wariant cenowy z kosza, zwraca JSON |

### **Metody kontrolera**

#### **create($courseId)**

Wyświetla formularz tworzenia nowego wariantu cenowego.

**Parametry:**
- `$courseId`: ID kursu (z routy)

**Zwraca:** View `'course-price-variants.create'`

**Dane przekazane do widoku:**
- `$course`: Obiekt Course

#### **store(Request $request, $courseId)**

Zapisuje nowy wariant cenowy w bazie danych.

**Parametry:**
- `$request`: Request z danymi formularza
- `$courseId`: ID kursu (z routy)

**Walidacja:** Zobacz sekcję [Walidacja](#-walidacja)  
**Transakcje:** Używa `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`

**Zwraca:**
- Success: Redirect do `courses.show` z komunikatem sukcesu
- Error: Redirect back z błędem walidacji lub wyjątkiem

**Logika:**
1. Sprawdza czy kurs istnieje (`findOrFail`)
2. Waliduje dane z formularza
3. Rozpoczyna transakcję
4. Tworzy nowy obiekt `CoursePriceVariant`
5. Przypisuje `course_id`
6. Zapisuje do bazy danych
7. Zatwierdza transakcję
8. Przekierowuje z komunikatem sukcesu

#### **edit($courseId, $id)**

Wyświetla formularz edycji wariantu cenowego.

**Parametry:**
- `$courseId`: ID kursu (z routy)
- `$id`: ID wariantu cenowego (z routy)

**Zwraca:** View `'course-price-variants.edit'`

**Dane przekazane do widoku:**
- `$course`: Obiekt Course
- `$variant`: Obiekt CoursePriceVariant

#### **update(Request $request, $courseId, $id)**

Aktualizuje istniejący wariant cenowy.

**Parametry:**
- `$request`: Request z danymi formularza
- `$courseId`: ID kursu (z routy)
- `$id`: ID wariantu cenowego (z routy)

**Walidacja:** Zobacz sekcję [Walidacja](#-walidacja)  
**Transakcje:** Używa `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`

**Zwraca:**
- Success: Redirect do `courses.show` z komunikatem sukcesu
- Error: Redirect back z błędem walidacji lub wyjątkiem

#### **destroy($courseId, $id)**

Usuwa wariant cenowy (soft delete).

**Parametry:**
- `$courseId`: ID kursu (z routy)
- `$id`: ID wariantu cenowego (z routy)

**Zwraca:** JSON response

**Format odpowiedzi:**
```json
{
    "success": true,
    "message": "Wariant cenowy został usunięty."
}
```

**Statusy HTTP:**
- `200`: Sukces
- `400`: Kurs nie istnieje lub został usunięty
- `404`: Wariant nie znaleziony
- `500`: Błąd serwera

#### **restore($courseId, $id)**

Przywraca wariant cenowy z kosza (soft delete).

**Parametry:**
- `$courseId`: ID kursu (z routy)
- `$id`: ID wariantu cenowego (z routy)

**Zwraca:** JSON response

**Format odpowiedzi:**
```json
{
    "success": true,
    "message": "Wariant cenowy został przywrócony."
}
```

---

## ✅ **Walidacja**

Walidacja jest wykonywana w metodach `store()` i `update()` kontrolera.

### **Podstawowe pola wariantu**

| Pole | Reguły | Opis |
|------|--------|------|
| `name` | `required\|string\|max:255` | Nazwa jest wymagana, maksymalnie 255 znaków |
| `description` | `nullable\|string` | Opis jest opcjonalny |
| `is_active` | `boolean` | Musi być wartością boolean (true/false) |
| `price` | `required\|numeric\|min:0` | Cena jest wymagana, musi być liczbą, minimalna wartość: 0 |

### **Pola promocji**

| Pole | Reguły | Opis |
|------|--------|------|
| `is_promotion` | `boolean` | Musi być wartością boolean (true/false) |
| `promotion_price` | `nullable\|numeric\|min:0\|required_if:is_promotion,1` | Opcjonalne, ale wymagane jeśli is_promotion = 1 |
| `promotion_type` | `required\|in:disabled,unlimited,time_limited` | Wymagane, musi być jedną z wartości enum |
| `promotion_start` | `nullable\|date\|required_if:promotion_type,time_limited` | Opcjonalne, ale wymagane jeśli promotion_type = 'time_limited' |
| `promotion_end` | `nullable\|date\|after:promotion_start\|required_if:promotion_type,time_limited` | Opcjonalne, ale wymagane jeśli promotion_type = 'time_limited', musi być późniejsza niż promotion_start |

### **Pola typu dostępu**

| Pole | Reguły | Opis |
|------|--------|------|
| `access_type` | `required\|in:1,2,3,4,5` | Wymagane, musi być jedną z wartości enum |
| `access_start_datetime` | `nullable\|date\|required_if:access_type,2,4,5` | Opcjonalne, ale wymagane jeśli access_type IN ('2', '4', '5') |
| `access_end_datetime` | `nullable\|date\|after:access_start_datetime\|required_if:access_type,2,4` | Opcjonalne, ale wymagane jeśli access_type IN ('2', '4'), musi być późniejsza niż access_start_datetime |
| `access_duration_value` | `nullable\|integer\|min:1\|required_if:access_type,3,5` | Opcjonalne, ale wymagane jeśli access_type IN ('3', '5'), musi być liczbą całkowitą, minimalna wartość: 1 |
| `access_duration_unit` | `nullable\|in:hours,days,months,years\|required_if:access_type,3,5` | Opcjonalne, ale wymagane jeśli access_type IN ('3', '5'), musi być jedną z wartości: 'hours', 'days', 'months', 'years' |

### **Przykłady błędów walidacji**

Jeśli walidacja się nie powiedzie, użytkownik zostanie przekierowany z powrotem do formularza z błędami walidacji dostępnymi w sesji.

**Przykładowe błędy:**
- `"Pole nazwa jest wymagane."` (jeśli name jest puste)
- `"Pole cena musi być liczbą."` (jeśli price nie jest liczbą)
- `"Pole cena promocyjna jest wymagane gdy promocja jest włączona."` (jeśli is_promotion = 1, ale promotion_price jest puste)
- `"Pole data rozpoczęcia promocji jest wymagane gdy typ promocji to ograniczona czasowo."` (jeśli promotion_type = 'time_limited', ale promotion_start jest puste)
- `"Pole data zakończenia dostępu musi być późniejsza niż data rozpoczęcia dostępu."` (jeśli access_end_datetime <= access_start_datetime)

---

## 🗑️ **Soft Delete i Przywracanie**

### **Soft Delete**

System używa soft delete (`SoftDeletes` trait), co oznacza, że rekordy nie są fizycznie usuwane z bazy danych, tylko oznaczane jako usunięte (`deleted_at`).

**Operacja delete():**
- Ustawia pole `deleted_at` na aktualną datę/czas
- Rekord pozostaje w bazie danych
- Rekord nie jest widoczny w standardowych zapytaniach
- Możliwe jest przywrócenie rekordu

**Sprawdzanie czy rekord jest usunięty:**
```php
$variant->trashed(); // zwraca TRUE jeśli deleted_at != NULL
$variant->deleted_at; // zwraca wartość deleted_at lub NULL
```

**Pobieranie usuniętych rekordów:**
```php
CoursePriceVariant::withTrashed()->find($id); // zwraca również usunięte
CoursePriceVariant::onlyTrashed()->get(); // zwraca tylko usunięte
```

### **Przywracanie (Restore)**

Usunięte warianty można przywrócić metodą `restore()`.

**Warunki przywrócenia:**
- Kurs (course) musi istnieć i nie być usunięty
- Wariant musi istnieć (również w koszu)

**Operacja restore():**
- Ustawia pole `deleted_at` na `NULL`
- Rekord staje się znowu widoczny w standardowych zapytaniach
- Wszystkie dane wariantu pozostają niezmienione

### **Fizyczne usunięcie (Force Delete)**

Jeśli potrzebne jest fizyczne usunięcie rekordu z bazy danych:

```php
$variant->forceDelete();
```

**Uwaga:** Operacja jest nieodwracalna - rekord zostaje trwale usunięty.

---

## 📝 **Logowanie aktywności (Activity Log)**

Model `CoursePriceVariant` używa traitu `LogsActivity`, który automatycznie loguje wszystkie operacje CRUD w tabeli `activity_logs`.

### **Automatyczne logowanie**

Automatycznie są logowane:
- ✅ Tworzenie nowego wariantu (create)
- ✅ Aktualizacja wariantu (update)
- ✅ Usunięcie wariantu (delete) - soft delete
- ✅ Przywrócenie wariantu (restore)

### **Informacje zapisywane w logu**

Dla każdej operacji zapisywane są:
- Model: `'App\Models\CoursePriceVariant'`
- Model ID: ID wariantu cenowego
- Model Name: Nazwa wariantu (`name`)
- Action: Typ operacji ('created', 'updated', 'deleted', 'restored')
- Old Values: Poprzednie wartości pól (dla update i delete)
- New Values: Nowe wartości pól (dla create i update)
- User ID: ID użytkownika wykonującego operację
- Timestamp: Data i godzina operacji

---

## 🔗 **Relacje w modelu Course**

Model `Course` został rozszerzony o relacje do wariantów cenowych.

### **Relacja priceVariants()**

```php
public function priceVariants()
{
    return $this->hasMany(CoursePriceVariant::class);
}
```

**Opis:** Zwraca wszystkie warianty cenowe (również nieaktywne i usunięte)

**Przykład użycia:**
```php
$course = Course::find(409);
$allVariants = $course->priceVariants; // Wszystkie warianty
```

### **Relacja activePriceVariants()**

```php
public function activePriceVariants()
{
    return $this->hasMany(CoursePriceVariant::class)->where('is_active', true);
}
```

**Opis:** Zwraca tylko aktywne warianty cenowe

**Przykład użycia:**
```php
$course = Course::find(409);
$activeVariants = $course->activePriceVariants; // Tylko aktywne
```

### **Eager Loading**

Aby uniknąć problemu N+1, należy użyć eager loading:

```php
// W kontrolerze CoursesController
$courses = Course::with('priceVariants')->get();

// Tylko aktywne warianty
$courses = Course::with(['priceVariants' => function($query) {
    $query->where('is_active', true);
}])->get();
```

---

## 💻 **Widoki Blade**

### **courses/show.blade.php**

Sekcja "Warianty cenowe" dodana do widoku szczegółów kursu.

**Funkcjonalności:**
- Wyświetla listę aktywnych wariantów cenowych w tabeli
- Dla każdego wariantu pokazuje:
  - Nazwę
  - Opis
  - Cenę podstawową i aktualną cenę (z uwzględnieniem promocji)
  - Status promocji (badge "PROM" jeśli aktywna)
  - Typ dostępu
  - Status aktywności
- Przyciski akcji: "Edytuj", "Usuń"
- Przycisk "Dodaj wariant" prowadzący do formularza tworzenia
- Sekcja usuniętych wariantów (soft delete) z przyciskiem "Przywróć"
- Modal potwierdzenia usunięcia

**Format wyświetlania ceny:**
- Cena podstawowa: `"999,99 PLN"`
- Cena promocyjna (jeśli aktywna): `"899,99 PLN"` z badge "PROM"
- Formatowanie: `number_format($price, 2, ',', ' ')`

### **course-price-variants/create.blade.php**

Formularz tworzenia nowego wariantu cenowego.

**Pola formularza:**
- Nazwa (name) - pole tekstowe, wymagane
- Opis (description) - pole textarea, opcjonalne
- Czy aktywny (is_active) - checkbox, domyślnie zaznaczony
- Cena (price) - pole numeryczne, wymagane
- Promocja (is_promotion) - checkbox
- Cena promocyjna (promotion_price) - wyświetlane gdy is_promotion zaznaczone
- Typ promocji (promotion_type) - select, wyświetlane gdy is_promotion zaznaczone
- Data rozpoczęcia promocji (promotion_start) - wyświetlane gdy promotion_type = 'time_limited'
- Data zakończenia promocji (promotion_end) - wyświetlane gdy promotion_type = 'time_limited'
- Typ dostępu (access_type) - select, wymagane
- Data rozpoczęcia dostępu (access_start_datetime) - wyświetlane dla typów 2, 4, 5
- Data zakończenia dostępu (access_end_datetime) - wyświetlane dla typów 2, 4
- Czas trwania dostępu - wartość (access_duration_value) - wyświetlane dla typów 3, 5
- Jednostka czasu dostępu (access_duration_unit) - wyświetlane dla typów 3, 5

**JavaScript:**
- Dynamiczne pokazywanie/ukrywanie pól w zależności od wybranych opcji
- Walidacja po stronie klienta (HTML5)
- Obsługa zdarzeń onChange dla checkboxów i selectów

### **course-price-variants/edit.blade.php**

Formularz edycji istniejącego wariantu cenowego.

Podobny do formularza tworzenia, ale:
- Wszystkie pola są wstępnie wypełnione wartościami z bazy danych
- Używa metody PUT zamiast POST
- Zawiera hidden input z `_method('PUT')`

### **courses/index.blade.php**

Lista kursów z wariantami cenowymi w kolumnie "Data".

**Wyświetlanie wariantów:**
- W kolumnie "Data" (obok daty rozpoczęcia kursu) wyświetlane są aktywne warianty
- Każdy wariant w osobnym wierszu
- Format: `"1 999,99 PLN"` (z separatorem tysięcy)
- Badge "PROM" jeśli promocja jest aktywna
- Nazwa wariantu pod ceną (max 25 znaków)

**Przykładowy widok w kolumnie:**
```
Data
──────────────
15.12.2025 10:00
120 min

1 999,99 PLN PROM
Standard

1 299,99 PLN
Early Bird
```

**Eager Loading:**
- W kontrolerze CoursesController dodano eager loading dla priceVariants
- Filtrowanie tylko aktywnych wariantów (where('is_active', true))
- Unikanie problemu N+1 zapytań

---

## 💡 **Przykłady użycia w kodzie**

### **Pobieranie wariantów cenowych**

```php
// Pobierz wszystkie warianty kursu
$course = Course::find(409);
$variants = $course->priceVariants;

// Pobierz tylko aktywne warianty
$activeVariants = $course->activePriceVariants;

// Pobierz wariant z obliczoną ceną
$variant = CoursePriceVariant::find(1);
$currentPrice = $variant->getCurrentPrice(); // Zwraca cenę promocyjną jeśli aktywna
```

### **Sprawdzanie promocji**

```php
$variant = CoursePriceVariant::find(1);

if ($variant->isPromotionActive()) {
    echo "Promocja aktywna! Cena: " . $variant->promotion_price . " PLN";
} else {
    echo "Cena standardowa: " . $variant->price . " PLN";
}

// Sprawdź typ promocji
echo $variant->getPromotionTypeName(); // "Bez ram czasowych", "Ograniczona czasowo", etc.
```

### **Sprawdzanie dostępu**

```php
$variant = CoursePriceVariant::find(1);

if ($variant->isAccessAvailable()) {
    echo "Dostęp do kursu jest dostępny";
} else {
    echo "Dostęp jeszcze nie rozpoczął się lub już się zakończył";
}

// Oblicz datę końca dostępu (dla typu 5)
$endDate = $variant->calculateAccessEndDate();
if ($endDate) {
    echo "Dostęp kończy się: " . $endDate->format('d.m.Y H:i');
}

// Pobierz nazwę typu dostępu
echo $variant->getAccessTypeName(); // "Bezterminowy, z natychmiastowym dostępem", etc.
```

### **Tworzenie wariantu programowo**

```php
$course = Course::find(409);

$variant = new CoursePriceVariant([
    'name' => 'Early Bird',
    'description' => 'Promocyjna cena dla uczestników zapisujących się przed 30.11.2025',
    'is_active' => true,
    'price' => 999.99,
    'is_promotion' => true,
    'promotion_price' => 799.99,
    'promotion_type' => 'time_limited',
    'promotion_start' => '2025-11-20 00:00:00',
    'promotion_end' => '2025-11-30 23:59:59',
    'access_type' => '1',
]);

$variant->course_id = $course->id;
$variant->save();
```

### **Aktualizacja wariantu**

```php
$variant = CoursePriceVariant::find(1);
$variant->price = 899.99;
$variant->is_active = false;
$variant->save();

// Lub za pomocą fill()
$variant->fill([
    'price' => 899.99,
    'is_active' => false,
]);
$variant->save();
```

### **Soft Delete i Restore**

```php
$variant = CoursePriceVariant::find(1);

// Usuń (soft delete)
$variant->delete(); // Ustawia deleted_at

// Sprawdź czy usunięty
if ($variant->trashed()) {
    echo "Wariant jest usunięty";
}

// Przywróć
$variant->restore(); // Usuwa deleted_at

// Pobierz z kosza
$deletedVariant = CoursePriceVariant::withTrashed()->find(1);

// Fizyczne usunięcie (nieodwracalne)
$variant->forceDelete();
```

### **Zapytania z warunkami**

```php
// Warianty z aktywną promocją
$variantsWithPromotion = CoursePriceVariant::where('is_promotion', true)
    ->where('promotion_type', 'time_limited')
    ->where('promotion_start', '<=', now())
    ->where('promotion_end', '>=', now())
    ->get();

// Warianty z określonym typem dostępu
$variantsType3 = CoursePriceVariant::where('access_type', '3')->get();

// Aktywne warianty kursu z ceną niższą niż 1000
$cheapVariants = $course->priceVariants()
    ->where('is_active', true)
    ->where('price', '<', 1000)
    ->get();
```

---

## 📚 **Scenariusze użycia**

### **Scenariusz 1: Kurs z jedną ceną standardową**

**Cel:** Utworzenie kursu z jedną ceną standardową bez promocji.

**Kroki:**
1. Utwórz kurs
2. Dodaj wariant cenowy:
   - Nazwa: "Standard"
   - Cena: 999.99 PLN
   - Promocja: wyłączona
   - Typ dostępu: Bezterminowy, z natychmiastowym dostępem
   - Czy aktywny: Tak

**Rezultat:**
- Kurs ma jedną opcję cenową 999.99 PLN
- Użytkownicy zawsze płacą 999.99 PLN
- Dostęp do kursu jest natychmiastowy i bezterminowy

### **Scenariusz 2: Kurs z promocją czasową**

**Cel:** Utworzenie kursu z promocją Early Bird ograniczoną czasowo.

**Kroki:**
1. Utwórz kurs
2. Dodaj wariant cenowy:
   - Nazwa: "Early Bird"
   - Cena podstawowa: 999.99 PLN
   - Promocja: włączona
   - Cena promocyjna: 799.99 PLN
   - Typ promocji: Ograniczona czasowo
   - Data rozpoczęcia promocji: 2025-11-20 00:00:00
   - Data zakończenia promocji: 2025-11-30 23:59:59
   - Typ dostępu: Bezterminowy, z natychmiastowym dostępem
   - Czy aktywny: Tak

**Rezultat:**
- Przed 20.11.2025: cena 999.99 PLN
- Od 20.11.2025 do 30.11.2025: cena promocyjna 799.99 PLN
- Po 30.11.2025: cena 999.99 PLN
- Badge "PROM" widoczny w okresie promocji

### **Scenariusz 3: Kurs z wieloma wariantami cenowymi**

**Cel:** Utworzenie kursu z różnymi cenami dla różnych grup odbiorców.

**Kroki:**
1. Utwórz kurs
2. Dodaj wariant "Standard":
   - Cena: 999.99 PLN
   - Typ dostępu: Bezterminowy, z natychmiastowym dostępem
3. Dodaj wariant "Student":
   - Cena: 699.99 PLN (promocja bez ram czasowych)
   - Typ dostępu: Bezterminowy, z natychmiastowym dostępem
4. Dodaj wariant "Korporacyjny":
   - Cena: 1299.99 PLN
   - Typ dostępu: Bezterminowy, od określonej daty
   - Data rozpoczęcia: 2025-12-01 00:00:00
   - Data zakończenia: 2026-12-31 23:59:59

**Rezultat:**
- Kurs ma 3 różne opcje cenowe
- Każda grupa odbiorców może wybrać odpowiedni wariant
- Wariant korporacyjny dostępny tylko w określonym przedziale czasowym

---

## ⚠️ **Obsługa błędów i wyjątków**

### **Walidacja**

Błędy walidacji są wyświetlane w formularzu:
- Każde pole z błędem jest wyróżnione (czerwone obramowanie)
- Pod każdym polem wyświetlany jest komunikat błędu
- Użytkownik może poprawić błędy i ponownie wysłać formularz

### **Transakcje bazy danych**

Wszystkie operacje zapisu (create, update) są wykonywane w transakcjach:
- Jeśli wystąpi błąd, wszystkie zmiany są wycofywane (rollback)
- Gwarancja spójności danych
- Komunikat błędu wyświetlany użytkownikowi

### **Sprawdzanie istnienia kursu**

Przed każdą operacją na wariancie sprawdzane jest:
- Czy kurs istnieje (`findOrFail`)
- Czy kurs nie jest usunięty (soft delete)
- Czy wariant należy do kursu

Jeśli kurs nie istnieje:
- Zwracany jest błąd 404 (Not Found)
- Komunikat: "Nie można usunąć/przywrócić wariantu - kurs nie istnieje"

---

## 🚀 **Optymalizacja i wydajność**

### **Indeksy bazy danych**

Utworzone indeksy dla optymalizacji zapytań:
- INDEX (`course_id`) - szybkie wyszukiwanie wariantów kursu
- INDEX (`is_active`) - filtrowanie aktywnych wariantów
- INDEX (`promotion_type`, `promotion_start`, `promotion_end`) - zapytania o promocje czasowe
- INDEX (`access_type`) - filtrowanie według typu dostępu

### **Eager Loading**

Unikanie problemu N+1 zapytań:
- W CoursesController użyto eager loading dla priceVariants
- Filtrowanie tylko aktywnych wariantów w zapytaniu
- Przykład: `Course::with('priceVariants')->get()`

### **Filtrowanie w zapytaniach**

Filtrowanie wariantów na poziomie bazy danych:
- Aktywne warianty: `->where('is_active', true)`
- Nieusunięte warianty: SoftDeletes automatycznie filtruje
- Promocje aktywne: Sprawdzanie dat w zapytaniu lub w kodzie PHP

---

## 🧪 **Testowanie**

### **Testy jednostkowe**

Zalecane testy dla modelu `CoursePriceVariant`:
- Test tworzenia wariantu
- Test walidacji pól
- Test metody `isPromotionActive()` dla różnych typów promocji
- Test metody `getCurrentPrice()` z aktywną/nieaktywną promocją
- Test metody `calculateAccessEndDate()` dla typu 5
- Test metody `isAccessAvailable()` dla różnych typów dostępu
- Test soft delete i restore

### **Testy integracyjne**

Zalecane testy dla kontrolera `CoursePriceVariantController`:
- Test tworzenia wariantu (store)
- Test walidacji przy tworzeniu
- Test edycji wariantu (update)
- Test usuwania wariantu (destroy)
- Test przywracania wariantu (restore)
- Test sprawdzania istnienia kursu

---

## 🔮 **Przyszłe rozszerzenia**

Możliwe rozszerzenia funkcjonalności:

### **Limity ilościowe**
- Ograniczenie liczby dostępnych miejsc dla wariantu
- Śledzenie liczby sprzedanych miejsc
- Automatyczne ukrywanie wariantu gdy limit wyczerpany

### **Grupy docelowe**
- Przypisywanie wariantów do określonych grup użytkowników
- Automatyczne wyświetlanie odpowiednich wariantów
- Przykład: wariant "Student" widoczny tylko dla użytkowników ze statusem "Student"

### **Rabaty procentowe**
- Możliwość ustawienia rabatu procentowego zamiast stałej ceny promocyjnej
- Automatyczne obliczanie ceny promocyjnej z ceny podstawowej

### **Waluty**
- Obsługa wielu walut (PLN, EUR, USD)
- Automatyczna konwersja kursowa
- Wyświetlanie ceny w walucie użytkownika

---

## ⚙️ **Znane ograniczenia**

### **Brak kaskadowego soft delete**

Jeśli kurs zostanie usunięty przez soft delete, warianty nie są automatycznie usuwane (soft delete). Warianty pozostają w bazie, ale mogą być nieprawidłowe (kurs usunięty).

**Rozwiązanie:** Sprawdzanie istnienia kursu przed każdą operacją.

### **Obliczanie czasu dostępu dla typu 3**

Dla typu 3 (Przez określony czas, z natychmiastowym dostępem) data końca dostępu jest obliczana po stronie aplikacji podczas aktywacji kursu dla użytkownika. W samym wariancie nie ma informacji o dokładnej dacie końca (zależy od momentu zakupu/aktywacji).

---

## 📝 **Changelog**

### **Wersja 1.0 (2025-11-20)**

- ✅ Inicjalna implementacja systemu wariantów cenowych
- ✅ Utworzenie tabeli course_price_variants
- ✅ Model CoursePriceVariant z podstawowymi metodami
- ✅ Kontroler CoursePriceVariantController z CRUD
- ✅ Widoki Blade (create, edit, show)
- ✅ Walidacja pól formularza
- ✅ Soft delete i restore
- ✅ Automatyczne logowanie aktywności
- ✅ Wyświetlanie wariantów w liście kursów
- ✅ Relacje w modelu Course

---

## 📞 **Kontakt i wsparcie**

W przypadku pytań lub problemów związanych z funkcjonalnością wariantów cenowych, skontaktuj się z zespołem deweloperskim.

**Dokumentacja techniczna:** Zobacz kod źródłowy w:
- `app/Models/CoursePriceVariant.php`
- `app/Http/Controllers/CoursePriceVariantController.php`
- `database/migrations/2025_11_20_004217_create_course_price_variants_table.php`
- `resources/views/course-price-variants/`
- `resources/views/courses/show.blade.php`

---

**Utworzone:** 2025-11-20  
**Ostatnia aktualizacja:** 2025-11-20  
**Status:** ✅ PRODUKCYJNE

