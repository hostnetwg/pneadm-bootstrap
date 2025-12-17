# Zaawansowane wyszukiwanie RSPO - Instrukcja

## 📋 Opis funkcjonalności

Dodano zaawansowane wyszukiwanie do strony `/rspo/search` z możliwością filtrowania po:
- **Typie podmiotu** (jak wcześniej)
- **Województwie** (z API TERYT)
- **Powiecie** (z API TERYT, dynamicznie ładowane po wyborze województwa)
- **Miejscowości** (z API TERYT, dynamicznie ładowane po wyborze powiatu)

## 🔧 Utworzone komponenty

### 1. **TerytService** (`app/Services/TerytService.php`)
Serwis do komunikacji z API GUS TERYT (SOAP):
- `getWojewodztwa()` - pobiera wszystkie województwa
- `getPowiaty($wojewodztwoKod)` - pobiera powiaty dla województwa
- `getMiejscowosci($wojewodztwoKod, $powiatKod)` - pobiera miejscowości dla powiatu
- Cache'owanie wyników na 7 dni (dane TERYT rzadko się zmieniają)

### 2. **RSPOController** (rozszerzony)
- Dodano pobieranie województw z TERYT
- Dodano obsługę parametrów: `wojewodztwo_nazwa`, `powiat_nazwa`, `miejscowosc_nazwa`
- Endpointy AJAX:
  - `GET /rspo/api/powiaty?wojewodztwo_kod=XX` - pobiera powiaty
  - `GET /rspo/api/miejscowosci?wojewodztwo_kod=XX&powiat_kod=XX` - pobiera miejscowości

### 3. **Widok search.blade.php** (zaktualizowany)
- Formularz z 4 polami: Typ, Województwo, Powiat, Miejscowość
- Dynamiczne ładowanie powiatów i miejscowości (AJAX)
- Zachowanie wybranych wartości po wyszukiwaniu

## ⚙️ Konfiguracja

### Zmienne środowiskowe (`.env`):
```env
TERYT_USERNAME=WaldemarGrabowski
TERYT_PASSWORD=k1Yc4S0ius
```

Lub w `config/services.php` (już skonfigurowane z domyślnymi wartościami).

## 🎯 Jak działa

### Przepływ użytkownika:
1. Użytkownik wybiera **Województwo** → JavaScript pobiera powiaty (AJAX)
2. Użytkownik wybiera **Powiat** → JavaScript pobiera miejscowości (AJAX)
3. Użytkownik wybiera **Miejscowość** (opcjonalne)
4. Użytkownik wybiera **Typ podmiotu** (opcjonalne)
5. Klik "Szukaj" → Wysyła zapytanie do API RSPO z wszystkimi filtrami

### Parametry API RSPO:
Zgodnie z dokumentacją API RSPO, obsługiwane parametry:
- `wojewodztwo_nazwa` - pełna nazwa województwa
- `powiat_nazwa` - pełna nazwa powiatu
- `miejscowosc_nazwa` - pełna nazwa miejscowości
- `typ_podmiotu_id` - ID typu podmiotu

## 🔍 Przykłady użycia

### Przykład 1: Szkoły podstawowe w Warszawie
1. Typ: "Szkoła podstawowa"
2. Województwo: "mazowieckie"
3. Powiat: "Warszawa"
4. Miejscowość: "Warszawa"
5. Szukaj

### Przykład 2: Wszystkie szkoły w Małopolsce
1. Województwo: "małopolskie"
2. Szukaj (bez wyboru powiatu/miejscowości)

### Przykład 3: Przedszkola w konkretnym powiecie
1. Typ: "Przedszkole"
2. Województwo: "śląskie"
3. Powiat: "Katowice"
4. Szukaj (bez miejscowości - znajdzie wszystkie w powiecie)

## 🐛 Rozwiązywanie problemów

### Problem: Powiaty/miejscowosci nie ładują się
**Rozwiązanie:**
- Sprawdź logi: `tail -f storage/logs/laravel.log | grep TERYT`
- Sprawdź czy SOAP extension jest włączone w PHP: `php -m | grep soap`
- Sprawdź czy dane logowania TERYT są poprawne

### Problem: Błąd SOAP
**Rozwiązanie:**
- Sprawdź czy masz dostęp do internetu z kontenera Docker
- Sprawdź czy WSDL jest dostępne: `curl https://uslugaterytws1.stat.gov.pl/wsdl/terytws1.wsdl`
- Sprawdź logi dla szczegółów błędu

### Problem: Cache nie odświeża się
**Rozwiązanie:**
```bash
sail artisan cache:clear
```

## 📝 Uwagi techniczne

1. **Cache TERYT:** Dane są cache'owane na 7 dni (dane administracyjne rzadko się zmieniają)
2. **SOAP Client:** Używa cache WSDL dla lepszej wydajności
3. **Timeout:** 30 sekund na połączenie SOAP
4. **AJAX:** Powiaty i miejscowości są ładowane dynamicznie bez przeładowania strony

## ✅ Checklist przed użyciem

- [ ] Sprawdź czy SOAP extension jest włączone: `sail php -m | grep soap`
- [ ] Sprawdź czy dane logowania TERYT są poprawne w `.env`
- [ ] Przetestuj pobieranie województw
- [ ] Przetestuj dynamiczne ładowanie powiatów
- [ ] Przetestuj dynamiczne ładowanie miejscowości
- [ ] Przetestuj wyszukiwanie z różnymi kombinacjami filtrów

## 🔐 Bezpieczeństwo

- Dane logowania TERYT są przechowywane w `.env` (nie commituj!)
- Cache'owanie zmniejsza liczbę zapytań do API TERYT
- Timeout zapobiega zawieszeniu aplikacji

