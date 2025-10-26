# 🚀 Quick Start - Migracja uczestników

## ✅ Co zostało zrobione:

1. ✅ Utworzono tabelę `form_order_participants`
2. ✅ Utworzono model `FormOrderParticipant`
3. ✅ Dodano relacje w modelu `FormOrder`
4. ✅ Utworzono `FormOrderObserver` - automatyczny zapis nowych zamówień
5. ✅ Utworzono komendę migracji danych `formorders:migrate-participants`

---

## 🎯 Jak uruchomić migrację istniejących danych:

### 1. Test (symulacja - nic nie zapisuje):
```bash
sail artisan formorders:migrate-participants --dry-run
```

### 2. Test z limitem (np. 10 rekordów):
```bash
sail artisan formorders:migrate-participants --limit=10 --dry-run
```

### 3. Pełna migracja (ZAPISUJE DO BAZY):
```bash
sail artisan formorders:migrate-participants
```

**Przykładowy output:**
```
🚀 Start migracji uczestników z form_orders do form_order_participants
📋 Znaleziono 150 zamówień do przetworzenia

 150/150 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

═══════════════════════════════════════════════════════════
                    📊 PODSUMOWANIE                        
═══════════════════════════════════════════════════════════
Znalezione rekordy      150
Zmigrowane              145
Pominięte (już istnieją) 5
Błędy                    0

✅ Migracja zakończona pomyślnie!
📝 Zmigrowano 145 uczestników
```

---

## 🔍 Przykłady normalizacji:

| Oryginał (form_orders) | Imię (normalized) | Nazwisko (normalized) |
|------------------------|-------------------|----------------------|
| `ADAM KOWALSKI` | `Adam` | `Kowalski` |
| `jan maria nowak` | `Jan Maria` | `Nowak` |
| `Anna KOWALSKA-NOWAK` | `Anna` | `Kowalska-Nowak` |
| `PIOTR   WIŚNIEWSKI` | `Piotr` | `Wiśniewski` |

---

## ✅ Weryfikacja po migracji:

```bash
# W terminalu (MySQL)
sail mysql

# W MySQL:
SELECT COUNT(*) FROM form_orders 
WHERE participant_name IS NOT NULL AND participant_name != '';

SELECT COUNT(*) FROM form_order_participants WHERE is_primary = 1;

# Powinny być identyczne!
```

---

## 🎯 Co się dzieje od teraz?

### Automatyczny zapis (bez zmian w kodzie):

**Gdy tworzysz nowe zamówienie:**
```php
$order = FormOrder::create([
    'participant_name' => 'Jan Kowalski',
    'participant_email' => 'jan@example.com',
    // ... inne pola
]);

// Observer automatycznie utworzy:
// FormOrderParticipant:
//   - participant_firstname: "Jan"
//   - participant_lastname: "Kowalski"
//   - participant_email: "jan@example.com"
//   - is_primary: 1
```

**WAŻNE:** Formularz i logika zapisu w `form_orders` **NIE ZMIENIAJĄ SIĘ**.  
Observer robi wszystko w tle! 🎉

---

## 📖 Pełna dokumentacja:

Szczegółowe informacje znajdziesz w: **`MIGRATION_PARTICIPANTS.md`**

---

## 🐛 Problemy?

```bash
# Sprawdź logi:
sail artisan pail

# Lub:
tail -f storage/logs/laravel.log
```

---

**Status:** ✅ Gotowe do użycia  
**Następny krok:** Uruchom migrację komendą powyżej

