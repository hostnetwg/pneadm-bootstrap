# 📊 System Logowania Aktywności - Dokumentacja

## 🎯 **Podsumowanie**

System Activity Logs automatycznie śledzi i loguje wszystkie ważne działania użytkowników w aplikacji, w tym:
- ✅ Logowanie i wylogowanie użytkowników
- ✅ Tworzenie, edycja, usuwanie i przywracanie rekordów
- ✅ Szczegóły zmian (co było przed, co jest po zmianie)
- ✅ Pełna historia aktywności dla każdego rekordu

---

## 🗄️ **Struktura bazy danych**

### **Tabela: `activity_logs`**

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | BIGINT | Klucz główny |
| `user_id` | BIGINT | ID użytkownika (FK → users) |
| `log_type` | ENUM | Typ akcji: login, logout, create, update, delete, restore, view, custom |
| `model_type` | VARCHAR(255) | Typ modelu (np. `App\Models\Course`) |
| `model_id` | BIGINT | ID rekordu w modelu |
| `model_name` | VARCHAR(500) | Czytelna nazwa rekordu |
| `action` | VARCHAR(500) | Opis akcji |
| `description` | TEXT | Dodatkowy opis |
| `old_values` | JSON | Wartości przed zmianą (dla update) |
| `new_values` | JSON | Wartości po zmianie (dla update/create) |
| `ip_address` | VARCHAR(45) | Adres IP użytkownika |
| `user_agent` | TEXT | User Agent przeglądarki |
| `url` | VARCHAR(500) | URL strony |
| `method` | VARCHAR(10) | Metoda HTTP (GET, POST, PUT, DELETE) |
| `created_at` | TIMESTAMP | Data i czas akcji |

**Indeksy dla optymalizacji:**
- `idx_user_id` - wyszukiwanie po użytkowniku
- `idx_log_type` - filtrowanie po typie akcji
- `idx_model` - wyszukiwanie po modelu (model_type + model_id)
- `idx_created_at` - sortowanie po dacie
- `idx_user_date` - wyszukiwanie użytkownik + data

---

## 🏗️ **Architektura systemu**

### **1. Model ActivityLog**
**Lokalizacja:** `app/Models/ActivityLog.php`

**Główne metody:**

#### **Logowanie akcji CRUD:**
```php
// Utworzenie rekordu
ActivityLog::logCreated($model, 'Opis akcji');

// Aktualizacja rekordu
ActivityLog::logUpdated($model, 'Opis akcji');

// Usunięcie rekordu
ActivityLog::logDeleted($model, 'Opis akcji');

// Przywrócenie rekordu
ActivityLog::logRestored($model, 'Opis akcji');

// Wyświetlenie rekordu
ActivityLog::logViewed($model, 'Opis akcji');
```

#### **Logowanie logowania/wylogowania:**
```php
// Logowanie
ActivityLog::logLogin($userId, 'Opis');

// Wylogowanie
ActivityLog::logLogout($userId, 'Opis');
```

#### **Niestandardowe akcje:**
```php
ActivityLog::logCustom('Nazwa akcji', 'Opis', [
    'additional_data' => 'wartość',
]);
```

#### **Scopes (zakresy zapytań):**
```php
// Logi konkretnego użytkownika
ActivityLog::forUser($userId)->get();

// Logi konkretnego typu
ActivityLog::ofType('update')->get();

// Logi konkretnego modelu
ActivityLog::forModel('App\Models\Course', $courseId)->get();

// Logi z ostatnich X dni
ActivityLog::recent(7)->get();

// Logi z dzisiaj
ActivityLog::today()->get();

// Logi z tego miesiąca
ActivityLog::thisMonth()->get();
```

---

### **2. Trait LogsActivity**
**Lokalizacja:** `app/Traits/LogsActivity.php`

**Automatyczne logowanie** - wystarczy dodać trait do modelu:

```php
use App\Traits\LogsActivity;

class Course extends Model
{
    use LogsActivity;
    
    // Automatycznie loguje: created, updated, deleted, restored
}
```

#### **Dostosowanie logowania:**

**a) Wybór logowanych zdarzeń:**
```php
class Course extends Model
{
    use LogsActivity;
    
    // Loguj tylko te zdarzenia
    protected $logActivityEvents = ['created', 'updated'];
}
```

**b) Własne opisy akcji:**
```php
class Course extends Model
{
    use LogsActivity;
    
    public function getActivityDescription($event)
    {
        return match($event) {
            'created' => "Utworzono nowy kurs: {$this->title}",
            'updated' => "Zaktualizowano kurs: {$this->title}",
            'deleted' => "Usunięto kurs: {$this->title}",
            default => "Akcja na kursie: {$this->title}",
        };
    }
}
```

**c) Ręczne logowanie niestandardowej akcji:**
```php
// W kontrolerze
$course = Course::find(1);
$course->logActivity('exported', 'Wyeksportowano listę uczestników do PDF');
```

---

### **3. Kontroler ActivityLogController**
**Lokalizacja:** `app/Http/Controllers/ActivityLogController.php`

**Dostępne metody:**
- `index()` - Lista wszystkich logów z filtrami
- `show($id)` - Szczegóły pojedynczego logu
- `userLogs($userId)` - Logi konkretnego użytkownika
- `modelLogs($modelType, $modelId)` - Logi konkretnego rekordu
- `statistics()` - Statystyki aktywności
- `export()` - Eksport logów do CSV

---

## 🛣️ **Trasy (Routes)**

| Metoda | URL | Nazwa | Opis |
|--------|-----|-------|------|
| GET | `/activity-logs` | `activity-logs.index` | Lista logów |
| GET | `/activity-logs/statistics` | `activity-logs.statistics` | Statystyki |
| GET | `/activity-logs/export` | `activity-logs.export` | Eksport CSV |
| GET | `/activity-logs/user/{userId}` | `activity-logs.user-logs` | Logi użytkownika |
| GET | `/activity-logs/model/{type}/{id}` | `activity-logs.model-logs` | Logi rekordu |
| GET | `/activity-logs/{id}` | `activity-logs.show` | Szczegóły logu |

---

## 🎨 **Widoki (Views)**

### **1. Lista logów** (`activity-logs/index.blade.php`)
**URL:** `/activity-logs`

**Funkcjonalności:**
- ✅ Filtrowanie po: wyszukiwaniu, typie akcji, użytkowniku, modelu, dacie
- ✅ Paginacja (10, 25, 50, 100, wszystkie)
- ✅ Kolorowe ikony dla typów akcji
- ✅ Linki do szczegółów, logów użytkownika, exportu

### **2. Szczegóły logu** (`activity-logs/show.blade.php`)
**URL:** `/activity-logs/{id}`

**Wyświetla:**
- ✅ Pełne informacje o logu
- ✅ Dane techniczne (IP, User Agent, URL, metoda HTTP)
- ✅ Zmiany dla akcji update (przed/po)
- ✅ Dane rekordu dla create/delete
- ✅ Nawigacja (poprzedni/następny log)

### **3. Statystyki** (`activity-logs/statistics.blade.php`)
**URL:** `/activity-logs/statistics`

**Pokazuje:**
- ✅ Ogólne statystyki (wszystkie logi, logowania, aktualizacje, usunięcia)
- ✅ Rozkład typów akcji z procentami
- ✅ Top 10 najbardziej aktywnych użytkowników
- ✅ Najpopularniejsze modele
- ✅ Aktywność według dni (wykres)
- ✅ Filtr okresu (1, 7, 30, 90, 365 dni)

---

## 🚀 **Jak używać**

### **1. Automatyczne logowanie (zalecane)**

Dodaj trait `LogsActivity` do modelu:

```php
use App\Traits\LogsActivity;

class FormOrder extends Model
{
    use LogsActivity; // Dodaj ten trait
}
```

**Od teraz wszystkie operacje CRUD będą automatycznie logowane!**

### **2. Ręczne logowanie**

#### **W kontrolerze:**
```php
use App\Models\ActivityLog;

// Przykład 1: Eksport PDF
ActivityLog::logCustom(
    'Eksport PDF',
    "Wyeksportowano listę uczestników kursu #{$course->id} do PDF",
    [
        'model_type' => 'App\Models\Course',
        'model_id' => $course->id,
        'model_name' => $course->title,
    ]
);

// Przykład 2: Wysłanie emaila
ActivityLog::logCustom(
    'Wysłano email',
    "Wysłano powiadomienie do {$participantCount} uczestników",
    [
        'model_type' => 'App\Models\Course',
        'model_id' => $course->id,
    ]
);
```

#### **Za pomocą modelu:**
```php
$course = Course::find(1);
$course->logActivity('exported', 'Wyeksportowano dane kursu');
```

---

## 📊 **Przykłady zastosowania**

### **1. Zobacz wszystkie logi użytkownika**
```php
$user = User::find(1);
$logs = ActivityLog::forUser($user->id)
    ->orderBy('created_at', 'desc')
    ->paginate(25);
```

### **2. Zobacz historię zmian konkretnego kursu**
```php
$course = Course::find(1);
$logs = ActivityLog::forModel(Course::class, $course->id)
    ->orderBy('created_at', 'desc')
    ->get();
```

### **3. Kto usunął ten rekord?**
```php
$deletionLog = ActivityLog::where('model_type', Course::class)
    ->where('model_id', $courseId)
    ->where('log_type', 'delete')
    ->first();

$deletedBy = $deletionLog->user->name;
```

### **4. Co zostało zmienione?**
```php
$log = ActivityLog::find(123);

if ($log->log_type === 'update') {
    $oldValues = $log->old_values;  // Wartości przed zmianą
    $newValues = $log->new_values;  // Wartości po zmianie
    
    foreach ($newValues as $field => $newValue) {
        $oldValue = $oldValues[$field] ?? null;
        echo "{$field}: {$oldValue} → {$newValue}\n";
    }
}
```

### **5. Statystyki aktywności użytkownika**
```php
$userId = 1;
$period = 30; // dni

$stats = [
    'total' => ActivityLog::forUser($userId)->recent($period)->count(),
    'logins' => ActivityLog::forUser($userId)->recent($period)->ofType('login')->count(),
    'updates' => ActivityLog::forUser($userId)->recent($period)->ofType('update')->count(),
    'deletes' => ActivityLog::forUser($userId)->recent($period)->ofType('delete')->count(),
];
```

---

## 🔧 **Konfiguracja**

### **Które modele logują aktywność:**

✅ **Obecnie włączone:**
1. `Course` - Kursy
2. `Instructor` - Instruktorzy
3. `FormOrder` - Zamówienia
4. `FormOrderParticipant` - Uczestnicy zamówień
5. `Participant` - Uczestnicy kursów
6. `User` - Użytkownicy

### **Dodanie nowego modelu:**

```php
use App\Traits\LogsActivity;

class MojModel extends Model
{
    use LogsActivity; // Dodaj ten trait
}
```

---

## 📈 **Wydajność**

### **Indeksy bazy danych:**
- Wszystkie najczęściej używane kolumny mają indeksy
- Zapytania są optymalizowane przez Eloquent

### **Przechowywanie logów:**
- Logi są przechowywane bezterminowo
- Zalecane: Archiwizacja starszych logów (np. > 1 rok) do plików CSV

### **Sugestia czyszczenia:**
```php
// Przykład: Usuń logi starsze niż 365 dni
ActivityLog::where('created_at', '<', now()->subDays(365))->delete();
```

Możesz dodać to do Scheduled Task w `app/Console/Kernel.php`:
```php
$schedule->call(function () {
    ActivityLog::where('created_at', '<', now()->subYear())->delete();
})->monthly();
```

---

## 🎨 **Interfejs użytkownika**

### **Dostęp:**
1. Panel administracyjny → **Admin** → **Logi aktywności**
2. Bezpośredni URL: `https://adm.pnedu.pl/activity-logs`

### **Funkcje UI:**
- ✅ Przejrzysta lista z kolorowymi ikonami
- ✅ Zaawansowane filtry
- ✅ Eksport do CSV
- ✅ Statystyki wizualne
- ✅ Szczegółowy podgląd zmian

---

## 🔒 **Bezpieczeństwo i prywatność**

### **Dostęp:**
- Tylko zalogowani użytkownicy mogą przeglądać logi
- Middleware: `auth`, `verified`, `check.user.status`

### **Przechowywane dane:**
- Adres IP użytkownika
- User Agent przeglądarki
- Zmiany w rekordach (old_values, new_values)

### **RODO:**
- System logowania służy audytowi bezpieczeństwa
- Logi pomagają w śledzeniu nieautoryzowanych zmian
- Zalecane: Polityka przechowywania (np. 1-2 lata)

---

## 🎉 **Podsumowanie**

### **✅ Zalety systemu:**
1. **Automatyzacja** - Trait automatycznie loguje CRUD
2. **Transparentność** - Pełna historia zmian
3. **Audyt** - Kto, co, kiedy zmienił
4. **Bezpieczeństwo** - Śledzenie nieautoryzowanych działań
5. **Raportowanie** - Statystyki aktywności
6. **Łatwość użycia** - Prosty API, przejrzysty UI

### **📊 Co jest logowane:**
- ✅ Logowanie/wylogowanie użytkowników
- ✅ Tworzenie rekordów (create)
- ✅ Edycja rekordów (update) + szczegóły zmian
- ✅ Usuwanie rekordów (delete)
- ✅ Przywracanie z kosza (restore)
- ✅ Niestandardowe akcje (custom)

### **🚀 Gotowe do użycia!**
System jest w pełni funkcjonalny i gotowy do monitorowania aktywności w aplikacji!

---

## 📞 **Wsparcie**

W razie pytań lub problemów:
1. Sprawdź dokumentację Laravel: https://laravel.com/docs
2. Zobacz kod źródłowy: `app/Models/ActivityLog.php`
3. Sprawdź przykłady w kontrolerach

**System Activity Logs - Śledź każdy ruch w swojej aplikacji! 🔍**










