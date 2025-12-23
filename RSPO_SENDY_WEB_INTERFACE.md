# Interfejs Web - Import szkół z RSPO do Sendy

## 📍 Lokalizacja w menu

**Menu:** RSPO → **Dodaj do Sendy**

Interfejs znajduje się pod menu RSPO, jako drugi element (pod "Wyszukaj").

---

## 🎯 Opis funkcjonalności

### 1. **Strona główna (`/rspo/import`)**

#### Sekcja: "Jak to działa?"
- **Lokalizacja:** Górna część strony, niebieska karta informacyjna
- **Zawartość:**
  - Opis procesu w 4 krokach:
    1. Wybór kryteriów (typ szkoły, województwo)
    2. Podgląd wyników przed importem
    3. Konfiguracja list (nazwa nadawcy, email, grupowanie)
    4. Import - automatyczne tworzenie list i dodawanie subskrybentów
  - Ostrzeżenie o czasie trwania procesu

#### Sekcja: "Konfiguracja importu"
- **Formularz z polami:**

  **Filtry wyszukiwania:**
  - `Typ szkoły/placówki` (select) - opcjonalne, lista typów z RSPO API
  - `Województwo` (select) - opcjonalne, lista województw z RSPO API

  **Konfiguracja list Sendy:**
  - `Grupuj szkoły według` (select, wymagane):
    - Województwo (domyślnie)
    - Typ szkoły
    - Miejscowość
  - `Prefiks nazwy listy` (text) - domyślnie "RSPO - "

  **Dane nadawcy:**
  - `Nazwa nadawcy` (text, wymagane) - domyślnie "NODN"
  - `Email nadawcy` (email, wymagane) - domyślnie z config('mail.from.address')
  - `Email reply-to` (email, opcjonalne) - domyślnie = email nadawcy
  - `Brand ID w Sendy` (number, readonly) - zawsze 4 (NODN)

  **Akcje:**
  - Przycisk "Podgląd wyników" (niebieski) - generuje podgląd bez zapisywania
  - Przycisk "Rozpocznij import" (zielony) - początkowo disabled, aktywuje się po podglądzie
  - Checkbox potwierdzenia (wymagany) - "Potwierdzam, że chcę utworzyć listy..."

#### Sekcja: "Podgląd wyników"
- **Lokalizacja:** Pojawia się po kliknięciu "Podgląd wyników"
- **Zawartość:**
  - Statystyki: liczba znalezionych szkół, liczba list do utworzenia
  - Tabela z podziałem na listy:
    - Nazwa listy (z prefiksem)
    - Liczba szkół w liście
    - Przykładowe emaile (max 5)
  - Po wygenerowaniu podglądu, przycisk "Rozpocznij import" staje się aktywny

#### Sekcja: "Wyniki importu" (po zakończeniu)
- **Lokalizacja:** Pojawia się po zakończeniu importu
- **Zawartość:**
  - Statystyki w 4 kolumnach:
    - Utworzone listy (zielony)
    - Dodani subskrybenci (niebieski)
    - Nieudane listy (żółty)
    - Nieudane subskrypcje (czerwony)
  - Informacja o liczbie przetworzonych szkół i grup
  - Lista błędów (jeśli wystąpiły)

---

## 🔄 Przepływ działania

### Krok 1: Wybór kryteriów
1. Użytkownik wybiera opcjonalnie typ szkoły i/lub województwo
2. Ustawia sposób grupowania (województwo/typ/miejscowość)
3. Konfiguruje dane nadawcy

### Krok 2: Podgląd
1. Użytkownik klika "Podgląd wyników"
2. System pobiera dane z RSPO API (AJAX)
3. Wyświetla statystyki i podgląd list
4. Przycisk "Rozpocznij import" staje się aktywny

### Krok 3: Import
1. Użytkownik zaznacza checkbox potwierdzenia
2. Klika "Rozpocznij import"
3. System pokazuje potwierdzenie (confirm dialog)
4. Po potwierdzeniu:
   - Pobiera wszystkie szkoły z RSPO (nie tylko podgląd)
   - Filtruje tylko te z emailami
   - Grupuje według wybranego kryterium
   - Dla każdej grupy:
     - Tworzy listę w Sendy (Brand ID: 4)
     - Dodaje szkoły jako subskrybentów
   - Wyświetla wyniki

---

## 🎨 Elementy interfejsu

### Kolory i ikony:
- **Info/Sukces:** Niebieski/zielony (`bg-info`, `bg-success`)
- **Ostrzeżenia:** Żółty (`bg-warning`, `alert-warning`)
- **Błędy:** Czerwony (`bg-danger`, `alert-danger`)
- **Ikony Bootstrap Icons:**
  - `bi-cloud-upload` - Import
  - `bi-info-circle` - Informacje
  - `bi-eye` - Podgląd
  - `bi-check-circle` - Sukces
  - `bi-exclamation-triangle` - Ostrzeżenie/Błąd

### Responsywność:
- Formularz w układzie grid (2 kolumny na desktop, 1 na mobile)
- Tabele z `table-responsive` dla małych ekranów

---

## ⚙️ Techniczne szczegóły

### Endpointy:
- `GET /rspo/import` - Formularz importu
- `POST /rspo/import/preview` - Podgląd (AJAX)
- `POST /rspo/import/import` - Wykonanie importu

### Walidacja:
- **Preview:** type_id (integer), wojewodztwo (string), group_by (required, enum)
- **Import:** Wszystkie pola z preview + from_name (required), from_email (required, email), confirm (required, accepted)

### Bezpieczeństwo:
- CSRF protection na wszystkich formularzach
- Walidacja emaili przed dodaniem do Sendy
- Potwierdzenie przed importem (confirm dialog + checkbox)

### Obsługa błędów:
- Wyświetlanie błędów walidacji pod polami formularza
- Komunikaty błędów w alertach
- Logowanie błędów do `storage/logs/laravel.log`
- Graceful handling - nie przerywa całego procesu przy błędzie jednej listy

---

## 📊 Przykładowe scenariusze

### Scenariusz 1: Import wszystkich szkół podstawowych
1. Wybierz typ: "Szkoła podstawowa"
2. Grupowanie: "Województwo"
3. Kliknij "Podgląd" → zobaczysz statystyki dla wszystkich województw
4. Kliknij "Rozpocznij import" → utworzy listy typu "RSPO - Mazowieckie", "RSPO - Śląskie", etc.

### Scenariusz 2: Import przedszkoli z Mazowsza
1. Wybierz typ: "Przedszkole"
2. Wybierz województwo: "mazowieckie"
3. Grupowanie: "Miejscowość"
4. Kliknij "Podgląd" → zobaczysz podział na miejscowości
5. Kliknij "Rozpocznij import" → utworzy listy typu "RSPO - Warszawa", "RSPO - Płock", etc.

### Scenariusz 3: Import wszystkich szkół z podziałem na typy
1. Nie wybieraj filtrów (wszystkie szkoły)
2. Grupowanie: "Typ szkoły"
3. Kliknij "Podgląd" → zobaczysz podział na typy szkół
4. Kliknij "Rozpocznij import" → utworzy listy typu "RSPO - Szkoła podstawowa", "RSPO - Przedszkole", etc.

---

## 🔔 Komunikaty i feedback

### Podczas podglądu:
- Spinner podczas ładowania
- Komunikat "Pobieranie danych z RSPO API..."
- Po zakończeniu: statystyki i tabela

### Podczas importu:
- Confirm dialog przed rozpoczęciem
- Po zakończeniu: redirect z komunikatem sukcesu
- Wyświetlenie wyników w sekcji "Wyniki importu"

### Komunikaty błędów:
- Walidacja: pod polami formularza (czerwone ramki)
- Błędy API: w alertach na górze strony
- Szczegóły błędów: w sekcji wyników (jeśli wystąpiły)

---

## 🚀 Następne kroki po zatwierdzeniu

1. **Testowanie:**
   - Przetestuj podgląd z różnymi filtrami
   - Przetestuj import z małą próbką (np. jedno województwo)
   - Sprawdź czy listy są poprawnie tworzone w Sendy

2. **Optymalizacje (opcjonalne):**
   - Progress bar podczas importu (dla długich operacji)
   - Queue jobs dla asynchronicznego przetwarzania
   - Cache'owanie typów szkół i województw

3. **Dodatkowe funkcje (opcjonalne):**
   - Historia importów
   - Eksport wyników do CSV/PDF
   - Harmonogram automatycznych importów

---

## ✅ Checklist przed wdrożeniem

- [ ] Sprawdź czy Sendy API działa (test connection)
- [ ] Zweryfikuj Brand ID (4) w Sendy
- [ ] Przetestuj podgląd z różnymi filtrami
- [ ] Przetestuj import z małą próbką
- [ ] Sprawdź czy emaile są poprawnie walidowane
- [ ] Zweryfikuj czy listy są poprawnie tworzone
- [ ] Sprawdź czy subskrybenci są poprawnie dodawani
- [ ] Przetestuj obsługę błędów
- [ ] Sprawdź responsywność na mobile
- [ ] Zweryfikuj logi błędów

---

## 📝 Notatki implementacyjne

- Interfejs używa Bootstrap 5.3.3 (zgodnie z projektem)
- AJAX dla podglądu (bez przeładowania strony)
- Formularz używa standardowej walidacji Laravel
- Wszystkie operacje są logowane
- Sendy API ma rate limiting - dodano opóźnienie 0.1s między subskrypcjami



