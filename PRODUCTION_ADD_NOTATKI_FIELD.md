# 🔧 Dodanie pola 'notatki' do tabeli courses na produkcji

## Problem
Błąd: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'notatki' in 'SET'`

## Rozwiązanie

Na produkcji musisz dodać kolumnę `notatki` do tabeli `courses`. Masz dwie opcje:

### Opcja 1: Uruchom migrację (zalecane)

```bash
cd /ścieżka/do/adm.pnedu.pl/public_html/pneadm-bootstrap

# Uruchom migrację
php artisan migrate
```

Jeśli migracja już była uruchomiona (ale nie wykonała się poprawnie), możesz:

```bash
# Cofnij ostatnią migrację
php artisan migrate:rollback --step=1

# Uruchom ponownie
php artisan migrate
```

### Opcja 2: Dodaj kolumnę bezpośrednio przez SQL

Jeśli nie możesz uruchomić migracji, możesz dodać kolumnę bezpośrednio:

```bash
# Zaloguj się do MySQL
mysql -u użytkownik -p nazwa_bazy

# Dodaj kolumnę
ALTER TABLE courses ADD COLUMN notatki TEXT NULL AFTER access_notes;

# Sprawdź czy została dodana
SHOW COLUMNS FROM courses LIKE 'notatki';

# Wyjdź
exit;
```

### Opcja 3: Przez phpMyAdmin

1. Zaloguj się do phpMyAdmin
2. Wybierz bazę danych
3. Kliknij na tabelę `courses`
4. Przejdź do zakładki "Struktura"
5. Znajdź kolumnę `access_notes`
6. Kliknij "Zmień" lub "Dodaj kolumnę"
7. Ustaw:
   - **Nazwa kolumny:** `notatki`
   - **Typ:** `TEXT`
   - **Null:** ✅ (Zaznaczone)
   - **Po kolumnie:** `access_notes`
8. Kliknij "Zapisz"

## ✅ Weryfikacja

Po dodaniu kolumny możesz sprawdzić czy wszystko działa:

```bash
php artisan tinker --execute="echo Schema::hasColumn('courses', 'notatki') ? 'Kolumna istnieje' : 'Kolumna nie istnieje';"
```

Lub przez SQL:

```sql
SHOW COLUMNS FROM courses WHERE Field = 'notatki';
```

## 📝 Uwagi

- Kolumna jest typu `TEXT` i może być `NULL`
- Kolumna jest umieszczona po kolumnie `access_notes`
- Po dodaniu kolumny aplikacja powinna działać bez błędów




