# Prompt AI: Wyświetlanie wariantów cenowych na stronie publicznej (pnedu.pl)

## Kontekst projektu

Projekt **pnedu.pl** to publiczny serwis wyświetlający ofertę szkoleń. Aplikacja korzysta z bazy danych **`pneadm`**, która jest współdzielona z projektem administracyjnym **adm.pnedu.pl**.

W bazie danych istnieje tabela **`course_price_variants`**, która przechowuje warianty cenowe dla kursów. Każdy kurs może mieć wiele wariantów cenowych, z których każdy może mieć włączoną promocję.

## Cel zadania

Zaimplementować wyświetlanie aktualnej ceny kursów na stronie publicznej z uwzględnieniem aktywnych promocji. Cena powinna być dynamicznie obliczana na podstawie:
- Podstawowej ceny wariantu (`price`)
- Stanu promocji (`is_promotion`, `promotion_type`)
- Przedziału czasowego promocji (`promotion_start`, `promotion_end`)
- Aktualnej daty i godziny

## Struktura tabeli course_price_variants

Tabela `course_price_variants` zawiera następujące pola kluczowe:

### Podstawowe pola wariantu:
- `id` - identyfikator wariantu
- `course_id` - ID kursu (foreign key do tabeli `courses`)
- `name` - nazwa wariantu (np. "Standard", "Early Bird")
- `description` - opis wariantu
- `is_active` - czy wariant jest aktywny (boolean)
- `price` - **cena podstawowa** (decimal 10,2) - zawsze używana gdy promocja nieaktywna

### Pola promocji:
- `is_promotion` - czy promocja jest włączona (boolean)
- `promotion_price` - **cena promocyjna** (decimal 10,2, nullable)
- `promotion_type` - typ promocji: `'disabled'`, `'unlimited'`, `'time_limited'` (enum)
- `promotion_start` - data i godzina rozpoczęcia promocji (datetime, nullable)
- `promotion_end` - data i godzina zakończenia promocji (datetime, nullable)

### Soft delete:
- `deleted_at` - data usunięcia (timestamp, nullable) - rekordy z wartością są usunięte

## Logika promocji - Szczegółowe zasady

### 1. Typ promocji: 'disabled'
- Promocja **NIGDY** nie jest aktywna
- **Zawsze** wyświetlaj cenę podstawową (`price`)
- Ignoruj `promotion_price`, `promotion_start`, `promotion_end`

### 2. Typ promocji: 'unlimited'
- Promocja jest **ZAWSZE** aktywna (jeśli `is_promotion = true`)
- **Zawsze** wyświetlaj cenę promocyjną (`promotion_price`)
- Ignoruj `promotion_start`, `promotion_end`
- **NIE wyświetlaj daty zakończenia promocji** (promocja bez ram czasowych)

### 3. Typ promocji: 'time_limited'
- Promocja jest aktywna **TYLKO** w określonym przedziale czasowym
- Sprawdź czy aktualna data/czas jest między `promotion_start` a `promotion_end`:
  - **JEŚLI** `promotion_start <= aktualna_data <= promotion_end` → promocja AKTYWNA
  - **JEŚLI** `aktualna_data < promotion_start` → promocja NIEAKTYWNA (wyświetl cenę podstawową)
  - **JEŚLI** `aktualna_data > promotion_end` → promocja NIEAKTYWNA (wyświetl cenę podstawową)

## Algorytm sprawdzania aktywności promocji

Użyj następującej logiki do sprawdzenia, czy promocja jest obecnie aktywna:

```php
function isPromotionActive($variant) {
    // Jeśli promocja wyłączona
    if (!$variant->is_promotion || $variant->promotion_type === 'disabled') {
        return false;
    }
    
    // Jeśli promocja bez ram czasowych
    if ($variant->promotion_type === 'unlimited') {
        return true;
    }
    
    // Jeśli promocja ograniczona czasowo
    if ($variant->promotion_type === 'time_limited') {
        $now = now(); // aktualna data/czas
        if ($variant->promotion_start && $variant->promotion_end) {
            return $now >= $variant->promotion_start && $now <= $variant->promotion_end;
        }
    }
    
    return false;
}
```

## Algorytm obliczania aktualnej ceny

```php
function getCurrentPrice($variant) {
    if (isPromotionActive($variant) && $variant->promotion_price !== null) {
        return $variant->promotion_price; // Cena promocyjna
    }
    return $variant->price; // Cena podstawowa
}
```

## Wymagania wyświetlania ceny

### Scenariusz 1: Promocja aktywna (time_limited lub unlimited)
Jeśli promocja jest aktywna:

1. **Wyświetl cenę podstawową z przekreśleniem:**
   - Format: `<span style="text-decoration: line-through;">999,99 PLN</span>`
   - Lub użyj klasy CSS: `<span class="text-decoration-line-through">999,99 PLN</span>`
   - Formatuj cenę: użyj `number_format($variant->price, 2, ',', ' ')` (separator tysięcy: spacja, separator dziesiętny: przecinek)

2. **Wyświetl cenę promocyjną:**
   - Format: `799,99 PLN` (bez przekreślenia, wyróżniona kolorem, np. czerwonym lub zielonym)
   - Formatuj cenę: użyj `number_format($variant->promotion_price, 2, ',', ' ')`

3. **Wyświetl datę zakończenia promocji (TYLKO dla time_limited):**
   - Format: `"Promocja trwa do: 31.12.2025 23:59"`
   - Tylko jeśli `promotion_type === 'time_limited'`
   - Sformatuj datę: `$variant->promotion_end->format('d.m.Y H:i')` lub `date('d.m.Y H:i', strtotime($variant->promotion_end))`
   - **NIE wyświetlaj daty dla promocji typu 'unlimited'**

### Scenariusz 2: Promocja nieaktywna lub brak promocji
Jeśli promocja nie jest aktywna:

1. **Wyświetl TYLKO cenę podstawową:**
   - Format: `999,99 PLN`
   - Bez przekreślenia
   - Formatuj cenę: użyj `number_format($variant->price, 2, ',', ' ')`

2. **NIE wyświetlaj ceny promocyjnej**
3. **NIE wyświetlaj daty zakończenia promocji**

## Przykładowy kod HTML/CSS do wyświetlania

### Gdy promocja aktywna:
```html
<div class="price-container">
    <span class="old-price" style="text-decoration: line-through; color: #999;">
        1 999,99 PLN
    </span>
    <span class="promotion-price" style="color: #dc3545; font-weight: bold; font-size: 1.2em;">
        1 499,99 PLN
    </span>
    <div class="promotion-end" style="font-size: 0.9em; color: #666; margin-top: 5px;">
        Promocja trwa do: 31.12.2025 23:59
    </div>
</div>
```

### Gdy promocja nieaktywna:
```html
<div class="price-container">
    <span class="regular-price" style="font-weight: bold; font-size: 1.2em;">
        1 999,99 PLN
    </span>
</div>
```

## Uwagi techniczne

### Filtrowanie wariantów:
- **Tylko aktywne warianty:** Używaj `WHERE is_active = 1` lub `where('is_active', true)`
- **Tylko nieusunięte warianty:** Jeśli używasz soft delete w modelu, upewnij się że filtrujesz `WHERE deleted_at IS NULL`
- **Dla każdego kursu:** Wybierz **jeden wariant** (np. pierwszy aktywny) lub wyświetl wszystkie aktywne warianty

### Relacja do kursów:
- Warianty są powiązane z kursami przez `course_id`
- Jeden kurs może mieć wiele wariantów cenowych
- Jeśli kurs ma wiele aktywnych wariantów, możesz:
  - Wyświetlić najtańszy wariant
  - Wyświetlić pierwszy aktywny wariant
  - Wyświetlić wszystkie aktywne warianty z możliwością wyboru przez użytkownika

### Formatowanie daty:
- Używaj formatu polskiego: `d.m.Y H:i` (np. `31.12.2025 23:59`)
- Strefa czasowa: Upewnij się, że używasz prawidłowej strefy czasowej (prawdopodobnie `Europe/Warsaw`)

### Formatowanie ceny:
- Separator tysięcy: **spacja** (np. `1 999,99`)
- Separator dziesiętny: **przecinek** (np. `999,99`)
- Zawsze dodawaj walutę: **PLN**
- Format: `number_format($price, 2, ',', ' ')` w PHP

## Przykładowe zapytanie SQL (dla referencji)

```sql
SELECT 
    cpv.*,
    c.title AS course_title
FROM course_price_variants cpv
INNER JOIN courses c ON cpv.course_id = c.id
WHERE cpv.is_active = 1
    AND cpv.deleted_at IS NULL
    AND c.id = :course_id
ORDER BY cpv.price ASC
LIMIT 1;
```

## Przykład wyświetlania w różnych scenariuszach

### Scenariusz A: Promocja time_limited aktywna (jesteśmy w przedziale czasowym)
```
Cena: 
~~1 999,99 PLN~~ 
1 499,99 PLN
Promocja trwa do: 31.12.2025 23:59
```

### Scenariusz B: Promocja time_limited nieaktywna (przed rozpoczęciem lub po zakończeniu)
```
Cena: 1 999,99 PLN
```

### Scenariusz C: Promocja unlimited (bez ram czasowych)
```
Cena: 
~~1 999,99 PLN~~ 
1 499,99 PLN
(NIE wyświetlaj daty zakończenia)
```

### Scenariusz D: Brak promocji lub promocja disabled
```
Cena: 1 999,99 PLN
```

## Testowanie

Sprawdź następujące przypadki:

1. ✅ Promocja time_limited - jesteśmy w przedziale czasowym → wyświetl cenę promocyjną, przekreśl podstawową, pokaż datę końca
2. ✅ Promocja time_limited - jesteśmy przed rozpoczęciem → wyświetl tylko cenę podstawową
3. ✅ Promocja time_limited - jesteśmy po zakończeniu → wyświetl tylko cenę podstawową
4. ✅ Promocja unlimited - wyświetl cenę promocyjną, przekreśl podstawową, NIE pokazuj daty
5. ✅ Promocja disabled - wyświetl tylko cenę podstawową
6. ✅ Brak promocji (is_promotion = false) - wyświetl tylko cenę podstawową
7. ✅ Wariant nieaktywny (is_active = false) - nie wyświetlaj tego wariantu
8. ✅ Wariant usunięty (deleted_at != NULL) - nie wyświetlaj tego wariantu

## Ważne uwagi

1. **Aktualna data/czas:** Zawsze sprawdzaj promocję względem aktualnej daty/czasu (`now()`, `Carbon::now()`, `date('Y-m-d H:i:s')`)

2. **Timezone:** Upewnij się, że używasz właściwej strefy czasowej przy porównywaniu dat

3. **Wiele wariantów:** Jeśli kurs ma wiele aktywnych wariantów, zadecyduj czy wyświetlasz:
   - Wszystkie warianty
   - Najtańszy wariant
   - Wariant domyślny (np. pierwszy)

4. **Brak wariantów:** Jeśli kurs nie ma żadnych aktywnych wariantów, obsłuż ten przypadek (np. "Cena do ustalenia")

5. **Null safety:** Zawsze sprawdzaj czy `promotion_price`, `promotion_start`, `promotion_end` nie są NULL przed użyciem

## Dodatkowe informacje

- Tabela `course_price_variants` używa soft delete (`deleted_at`)
- Warianty są powiązane z kursami przez foreign key `course_id`
- ON DELETE CASCADE działa tylko przy fizycznym usunięciu kursu
- Przy soft delete kursu, warianty pozostają w bazie, ale mogą być nieprawidłowe

---

**Zadanie:** Zaimplementuj wyświetlanie ceny kursów na stronie publicznej zgodnie z powyższymi zasadami, uwzględniając wszystkie scenariusze promocji i formatowanie zgodne z polskim standardem.

