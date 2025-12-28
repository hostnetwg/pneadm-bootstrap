# 📋 Instrukcja Eksportu/Importu przez phpMyAdmin z Poprawną Obsługą Timezone

## 🎯 Problem

Podczas eksportu/importu przez phpMyAdmin daty mogą być przesunięte o godzinę, ponieważ:
- phpMyAdmin używa timezone sesji MySQL podczas eksportu/importu
- Jeśli timezone serwera produkcyjnego różni się od lokalnego, daty są konwertowane
- Kolumna `order_date` jest typu `DATETIME` i nie przechowuje timezone

## ✅ Rozwiązanie

### 1. Eksport z Produkcji (phpMyAdmin)

#### Krok 1: Ustaw timezone sesji na UTC
Przed eksportem wykonaj w phpMyAdmin (zakładka SQL):

```sql
SET time_zone = '+00:00';
```

#### Krok 2: Eksportuj tabelę
1. Wybierz bazę danych `pneadm`
2. Kliknij na tabelę `form_orders`
3. Kliknij zakładkę **"Eksportuj"** (Export)
4. Wybierz metodę: **"Szybka"** (Quick) lub **"Niestandardowa"** (Custom)
5. Jeśli wybierasz **"Niestandardowa"**, upewnij się że:
   - ✅ **"Dodaj DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT / TRIGGER"** - odznaczone (jeśli tylko dane)
   - ✅ **"Dodaj CREATE TABLE"** - odznaczone (jeśli tylko dane)
   - ✅ **"Dodaj INSERT"** - zaznaczone
   - ✅ **"Użyj transakcji"** - zaznaczone
   - ✅ **"Wyłącz sprawdzanie kluczy obcych"** - zaznaczone
6. Kliknij **"Wykonaj"** (Go)

#### Krok 3: Sprawdź eksportowany plik SQL
Otwórz plik SQL i upewnij się, że:
- Na początku jest: `SET time_zone = '+00:00';` (lub dodaj ręcznie)
- Daty są w formacie: `'2025-12-11 13:56:34'` (bez konwersji)

**Przykład poprawnego eksportu:**
```sql
SET time_zone = '+00:00';
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

INSERT INTO `form_orders` (`id`, `ident`, `order_date`, ...) VALUES
(5513, 'ME_P3_Gfku4zQg0EIW_nEw', '2025-12-11 13:56:34', ...);

COMMIT;
```

### 2. Import na Komputerze Developera (phpMyAdmin)

#### Krok 1: Ustaw timezone sesji na UTC
Przed importem wykonaj w phpMyAdmin (zakładka SQL):

```sql
SET time_zone = '+00:00';
```

#### Krok 2: Importuj plik SQL
1. Wybierz bazę danych `pneadm`
2. Kliknij zakładkę **"SQL"** (lub **"Importuj"**)
3. Jeśli używasz zakładki **"SQL"**:
   - Wklej zawartość pliku SQL (lub załaduj plik)
   - **WAŻNE**: Upewnij się, że na początku pliku jest `SET time_zone = '+00:00';`
   - Kliknij **"Wykonaj"** (Go)
4. Jeśli używasz zakładki **"Importuj"**:
   - Wybierz plik SQL
   - Upewnij się, że opcja **"Częściowy import"** jest odznaczona
   - Kliknij **"Wykonaj"** (Go)

#### Krok 3: Weryfikacja
Po imporcie sprawdź czy daty są poprawne:

```sql
SELECT id, order_date, 
       CONVERT_TZ(order_date, '+00:00', '+01:00') as order_date_warsaw
FROM form_orders 
ORDER BY id DESC 
LIMIT 5;
```

### 3. Alternatywna Metoda: Eksport/Import przez mysqldump (ZALECANE)

Jeśli masz dostęp do terminala, użyj mysqldump zamiast phpMyAdmin:

#### Eksport z Produkcji:
```bash
# Na serwerze produkcyjnym
mysqldump -u username -p \
  --set-gtid-purged=OFF \
  --no-create-info \
  --skip-tz-utc \
  pneadm form_orders > form_orders_export.sql

# Dodaj SET time_zone na początku pliku
echo "SET time_zone = '+00:00';" > form_orders_with_tz.sql
cat form_orders_export.sql >> form_orders_with_tz.sql
```

#### Import na Komputerze Developera:
```bash
# Lokalnie (przez Sail)
./vendor/bin/sail mysql pneadm < form_orders_with_tz.sql

# Lub bezpośrednio
mysql -u sail -ppassword pneadm < form_orders_with_tz.sql
```

## 🔧 Konfiguracja Timezone w Aplikacji

### Upewnij się, że w `.env` masz:

**Na produkcji:**
```env
APP_TIMEZONE=Europe/Warsaw
DB_TIMEZONE=+00:00
```

**Lokalnie (developer):**
```env
APP_TIMEZONE=Europe/Warsaw
DB_TIMEZONE=+00:00
```

### Po zmianie `.env`:
```bash
php artisan config:clear
php artisan cache:clear
```

## 📊 Jak to Działa?

1. **Zapis do bazy**: Aplikacja zapisuje daty w UTC (`now('UTC')`)
2. **Eksport**: phpMyAdmin eksportuje daty jako stringi w UTC (jeśli sesja jest w UTC)
3. **Import**: phpMyAdmin importuje daty jako stringi w UTC (jeśli sesja jest w UTC)
4. **Wyświetlanie**: Aplikacja konwertuje z UTC na Europe/Warsaw przy wyświetlaniu

## ⚠️ Ważne Uwagi

1. **Zawsze ustawiaj `SET time_zone = '+00:00';` przed eksportem/importem**
2. **Nie używaj opcji "Konwertuj daty" w phpMyAdmin** - może to powodować konwersję
3. **Sprawdź eksportowany plik SQL** - daty powinny być dokładnie takie jak w bazie
4. **Po imporcie sprawdź kilka rekordów** - porównaj daty z oryginalnymi

## 🐛 Rozwiązywanie Problemów

### Problem: Daty są przesunięte o godzinę po imporcie

**Rozwiązanie:**
1. Sprawdź czy na początku pliku SQL jest `SET time_zone = '+00:00';`
2. Wykonaj przed importem: `SET time_zone = '+00:00';` w phpMyAdmin
3. Sprawdź konfigurację `DB_TIMEZONE` w `.env`

### Problem: Daty są przesunięte o 2 godziny

**Rozwiązanie:**
- To oznacza, że daty były zapisane w Europe/Warsaw zamiast UTC
- Sprawdź czy używasz `now('UTC')` w kodzie
- Możesz poprawić istniejące dane SQL:
```sql
UPDATE form_orders 
SET order_date = DATE_SUB(order_date, INTERVAL 1 HOUR)
WHERE order_date >= '2025-01-01';
```

### Problem: phpMyAdmin nie pozwala ustawić timezone

**Rozwiązanie:**
- Dodaj `SET time_zone = '+00:00';` ręcznie na początku pliku SQL przed importem
- Lub użyj mysqldump zamiast phpMyAdmin

## ✅ Checklist Przed Eksportem/Importem

- [ ] Ustaw `SET time_zone = '+00:00';` przed eksportem
- [ ] Sprawdź że `DB_TIMEZONE=+00:00` w `.env` na produkcji
- [ ] Sprawdź że `DB_TIMEZONE=+00:00` w `.env` lokalnie
- [ ] Wyczyść cache: `php artisan config:clear`
- [ ] Sprawdź eksportowany plik SQL - daty powinny być w UTC
- [ ] Ustaw `SET time_zone = '+00:00';` przed importem
- [ ] Po imporcie zweryfikuj kilka rekordów








