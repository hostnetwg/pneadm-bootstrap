# Rozwiązanie problemu przesunięcia dat o 2 godziny

## Problem
Po zaimportowaniu tabeli `form_orders` z lokalnej bazy do produkcji, daty są przesunięte o 2 godziny.

## Przyczyna
**Różne strefy czasowe MySQL** między środowiskiem lokalnym a produkcyjnym:
- **Środowisko lokalne**: MySQL pracuje w UTC
- **Środowisko produkcyjne**: MySQL prawdopodobnie pracuje w Europe/Warsaw (UTC+2/+1)
- **Laravel**: Używa Europe/Warsaw

Gdy Laravel zapisuje datę z timezone Europe/Warsaw do MySQL UTC, a potem czytasz ją na produkcji gdzie MySQL jest w Europe/Warsaw, następuje podwójna konwersja strefy czasowej.

## Rozwiązanie

### 1. Ustaw strefę czasową MySQL w pliku `.env`

Dodaj na serwerze produkcyjnym (i lokalnym) w pliku `.env`:

```env
# Strefa czasowa dla połączeń MySQL (UTC lub +00:00)
DB_TIMEZONE=+00:00
```

**WAŻNE**: Używaj `+00:00` zamiast `UTC` - to format akceptowany przez MySQL.

### 2. Alternatywnie: Zmień APP_TIMEZONE na UTC

Możesz też zmienić strefę czasową całej aplikacji na UTC:

```env
APP_TIMEZONE=UTC
DB_TIMEZONE=+00:00
```

Ale wtedy wszystkie daty w aplikacji będą wyświetlane w UTC, nie w lokalnym czasie.

### 3. Po zmianie konfiguracji

1. **Restart aplikacji** (jeśli używasz kolejek/workers)
2. **Wyczyść cache**: `php artisan config:clear`
3. **Przetestuj**: Sprawdź czy nowe rekordy mają poprawne daty
4. **Re-import**: Jeśli daty są nadal niepoprawne, usuń dane i zaimportuj ponownie

## Rekomendacja

**Najlepsze rozwiązanie dla Twojego przypadku:**

### Na środowisku lokalnym (.env):
```env
APP_TIMEZONE=Europe/Warsaw
DB_TIMEZONE=+00:00
```

### Na produkcji (.env):
```env
APP_TIMEZONE=Europe/Warsaw
DB_TIMEZONE=+00:00
```

To zagwarantuje, że:
- ✅ MySQL zawsze przechowuje daty w UTC
- ✅ Laravel konwertuje daty do Europe/Warsaw przy wyświetlaniu
- ✅ Export/Import działa prawidłowo między środowiskami
- ✅ Brak przesunięcia czasowego

## Co zrobić z istniejącymi danymi na produkcji?

Jeśli już zaimportowałeś dane z błędnymi datami:

### Opcja A: Re-import (ZALECANE)
1. Dodaj `DB_TIMEZONE=+00:00` do `.env` na produkcji
2. Wyczyść tabelę `form_orders` na produkcji
3. Zaimportuj ponownie dump z lokalnej bazy

### Opcja B: Korekta dat SQL (jeśli nie możesz re-importować)
```sql
-- UWAGA: Użyj tego TYLKO jeśli daty są przesunięte o dokładnie 2 godziny!
UPDATE form_orders 
SET created_at = DATE_SUB(created_at, INTERVAL 2 HOUR),
    updated_at = DATE_SUB(updated_at, INTERVAL 2 HOUR),
    order_date = DATE_SUB(order_date, INTERVAL 2 HOUR);
```

## Weryfikacja

Po zastosowaniu poprawek, sprawdź:

```bash
# W tinkerze Laravel
php artisan tinker

# Sprawdź konfigurację
echo config('app.timezone');      // Europe/Warsaw
echo config('database.connections.mysql.timezone');  // +00:00

# Porównaj daty
$old = DB::connection('mysql_certgen')->table('zamowienia_FORM')->first();
$new = DB::table('form_orders')->where('id', $old->id)->first();
echo "Stara: {$old->data_zamowienia}\n";
echo "Nowa:  {$new->created_at}\n";
```

## Dodatkowe informacje

**Typy dat w MySQL:**
- `DATETIME` - nie przechowuje strefy czasowej, zapisuje dokładnie to co dostanieDateTime value: "2020-12-23 11:57:35"
- `TIMESTAMP` - przechowuje w UTC wewnętrznie, konwertuje przy odczycie/zapisie według strefy czasowej sesji

**Nasza tabela używa `DATETIME`**, więc problem nie jest w typie kolumny, tylko w różnicy stref czasowych między MySQL a Laravel podczas eksportu/importu.











