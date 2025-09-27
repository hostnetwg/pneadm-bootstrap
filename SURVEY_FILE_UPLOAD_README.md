# Funkcjonalność automatycznego wyszukiwania szkolenia na podstawie daty z pliku

## Opis
Dodano opcję automatycznego wyszukiwania szkolenia na podstawie daty z pliku ankiety na stronie tworzenia ankiety (`/surveys/create`). System automatycznie parsuje nazwę pliku, wyciąga datę z nawiasów, a następnie wyszukuje odpowiednie szkolenie w bazie danych.

## Jak to działa

### 1. Format nazwy pliku
Nazwa pliku powinna zawierać:
- Datę w nawiasach w jednym z formatów:
  - `(YYYY-MM-DD)` - np. `(2024-01-15)`
  - `(DD.MM.YYYY)` - np. `(15.01.2024)`

**Przykłady poprawnych nazw plików:**
- `Ankieta (2024-01-15).csv`
- `Raport (15.01.2024).xlsx`
- `Wyniki (2024-12-01).csv`

### 2. Proces automatycznego wypełniania
1. Użytkownik wybiera plik ankiety
2. JavaScript parsuje nazwę pliku i wyciąga:
   - Datę szkolenia z nawiasów
3. Wysyłane jest żądanie AJAX do serwera w celu wyszukania szkolenia
4. Jeśli znaleziono szkolenie, automatycznie wypełniane są pola:
   - Tytuł ankiety (na podstawie znalezionego szkolenia)
   - Szkolenie
   - Instruktor (jeśli jest przypisany do szkolenia)
5. Po utworzeniu ankiety, jeśli plik to CSV, system automatycznie:
   - Importuje wszystkie pytania z nagłówków CSV
   - Wykrywa typy pytań (rating, text, multiple_choice, single_choice, date)
   - Importuje wszystkie odpowiedzi z pliku
   - Aktualizuje liczbę odpowiedzi w ankiecie

### 3. Wyszukiwanie szkolenia
System wyszukuje szkolenie w następującej kolejności:
1. **Dokładne dopasowanie daty**: Szuka wszystkich szkoleń, których data rozpoczęcia lub zakończenia odpowiada wyciągniętej dacie
2. **Wybór szkolenia**: 
   - Jeśli znaleziono jedno szkolenie - automatycznie je wybiera
   - Jeśli znaleziono kilka szkoleń - pokazuje listę do wyboru z radio buttonami
3. **Podobne daty**: Jeśli nie znajdzie dokładnego dopasowania, pokazuje szkolenia z zakresu ±7 dni od podanej daty

### 4. Obsługiwane formaty plików
- CSV (.csv) - **automatyczny import danych ankiety**
- Excel (.xlsx, .xls) - tylko wyszukiwanie szkolenia
- Maksymalny rozmiar: 10MB

### 5. Automatyczny import danych CSV
System automatycznie importuje dane z plików CSV:
- **Wykrywanie typów pytań**: rating (1-5), text, multiple_choice, single_choice, date
- **Parsowanie odpowiedzi**: automatyczne czyszczenie i formatowanie
- **Obsługa timestampów**: Google Forms format z automatyczną konwersją
- **Walidacja danych**: sprawdzanie poprawności formatu CSV

### 6. Ujednolicony sposób zapisywania plików
Niezależnie od metody importu (przez `/surveys/create` czy `/courses/{id}/surveys/import`), wszystkie pliki CSV są zapisywane w ten sam sposób:
- **Katalog**: `storage/app/private/surveys/imports/`
- **Nazwa pliku**: `{course_id}_{original_filename}`
- **Przykład**: `375_Ankieta (2024-01-15).csv`

## Nowe funkcje

### Frontend (JavaScript)
- Parsowanie nazwy pliku z regex
- Automatyczne wypełnianie pól formularza
- Komunikacja AJAX z serwerem
- Wyświetlanie statusu wyszukiwania

### Backend (PHP)
- Nowa metoda `searchCourse()` w `SurveyController`
- Walidacja danych wejściowych
- Inteligentne wyszukiwanie szkolenia
- Zwracanie sugestii podobnych szkoleń
- **Ujednolicony sposób zapisywania plików**: Wszystkie pliki CSV są zapisywane w katalogu `surveys/imports` z nazwą `{course_id}_{original_filename}`

### Nowa trasa
```
POST /surveys/search-course
```

## Pliki zmodyfikowane
1. `resources/views/surveys/create.blade.php` - dodano sekcję wczytywania pliku i JavaScript
2. `app/Http/Controllers/SurveyController.php` - dodano metodę `searchCourse()` i zaktualizowano `store()`
3. `routes/web.php` - dodano nową trasę

## Przykład użycia

### Scenariusz 1: Jedno szkolenie w danym dniu + import CSV
1. Przejdź na stronę `/surveys/create`
2. W sekcji "Automatyczne wyszukiwanie szkolenia na podstawie daty" wybierz plik CSV o nazwie np. `Ankieta (2024-01-15).csv`
3. System automatycznie:
   - Wyświetli wyciągniętą datę: "2024-01-15"
   - Wyszuka szkolenie w bazie danych na podstawie daty
   - Automatycznie wybierze znalezione szkolenie
   - Wypełni tytuł ankiety: "Ankieta - [Nazwa znalezionego szkolenia]"
   - Wypełni pole "Szkolenie" i "Instruktor"
4. Po kliknięciu "Utwórz ankietę":
   - Utworzy ankietę
   - Automatycznie zaimportuje wszystkie pytania i odpowiedzi z pliku CSV
   - Przekieruje do widoku ankiety z pełnymi danymi

### Scenariusz 2: Kilka szkoleń w danym dniu + import CSV
1. Przejdź na stronę `/surveys/create`
2. Wybierz plik CSV o nazwie np. `Ankieta (2024-01-15).csv`
3. System:
   - Wyświetli wyciągniętą datę: "2024-01-15"
   - Pokaże komunikat: "Znaleziono 3 szkoleń na datę 15.01.2024"
   - Wyświetli listę szkoleń z radio buttonami do wyboru
   - Po wybraniu szkolenia i kliknięciu "Wybierz szkolenie" automatycznie wypełni formularz
4. Po kliknięciu "Utwórz ankietę":
   - Utworzy ankietę
   - Automatycznie zaimportuje wszystkie pytania i odpowiedzi z pliku CSV
   - Przekieruje do widoku ankiety z pełnymi danymi

## Obsługa błędów
- Jeśli nie znaleziono szkolenia na podaną datę, wyświetlany jest komunikat z sugestiami szkoleń z podobnych dat (±7 dni)
- Jeśli nie znaleziono daty w nawiasach w nazwie pliku, wyświetlany jest odpowiedni komunikat
- Walidacja pliku (format, rozmiar)
- Obsługa błędów AJAX z odpowiednimi komunikatami
- **Import CSV**: Jeśli plik CSV jest nieprawidłowy, system wyświetli błąd i nie utworzy ankiety
- **Transakcje**: Wszystkie operacje (tworzenie ankiety + import) są w transakcji - jeśli coś się nie powiedzie, nic nie zostanie zapisane
