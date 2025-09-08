# Integracja z Sendy API

## Opis

System został zintegrowany z Sendy API, umożliwiając zarządzanie listami mailingowymi bezpośrednio z panelu administracyjnego.

## Funkcjonalności

### 1. Przeglądanie list mailingowych
- Wyświetlanie wszystkich list z wszystkich marek
- Informacje o liczbie aktywnych subskrybentów
- Szczegóły każdej listy (nazwa, marka, ID)

### 2. Zarządzanie subskrybentami
- Dodawanie nowych subskrybentów do list
- Usuwanie subskrybentów z list
- Sprawdzanie statusu subskrypcji
- Obsługa GDPR i podwójnej opt-in

### 3. Testowanie połączenia
- Test połączenia z Sendy API
- Sprawdzanie dostępności marek i list

## Konfiguracja

### Zmienne środowiskowe (.env)

```env
# Sendy Configuration
SENDY_API_KEY=QWVN3gYyibFsPWh39Til
SENDY_BASE_URL=https://sendyhost.net
SENDY_LICENSE_KEY=1ZmYIrF8HC93FNIcfkHCSKAcM0Tx3iVV
SENDY_CACHE_ENABLED=true
SENDY_CACHE_TTL=300
SENDY_LOGGING_ENABLED=true
SENDY_LOG_LEVEL=info
SENDY_LOG_CHANNEL=daily
```

### Plik konfiguracyjny (config/sendy.php)

Zawiera wszystkie ustawienia konfiguracyjne dla integracji Sendy.

## Struktura plików

```
app/
├── Services/
│   └── SendyService.php          # Główny serwis do komunikacji z API
├── Http/Controllers/
│   └── SendyController.php       # Kontroler obsługujący żądania HTTP

resources/views/sendy/
├── index.blade.php               # Lista wszystkich list mailingowych
└── show.blade.php                # Szczegóły konkretnej listy

config/
└── sendy.php                     # Konfiguracja Sendy

tests/Unit/
└── SendyServiceTest.php          # Testy jednostkowe
```

## API Endpoints

### GET /sendy
Wyświetla listę wszystkich list mailingowych.

### GET /sendy/{listId}
Wyświetla szczegóły konkretnej listy.

### GET /sendy/api/refresh
Odświeża dane list (AJAX).

### GET /sendy/api/test-connection
Testuje połączenie z Sendy API (AJAX).

### POST /sendy/api/subscribe
Dodaje subskrybenta do listy.

**Parametry:**
- `email` (required) - Adres email
- `list_id` (required) - ID listy
- `name` (optional) - Imię i nazwisko
- `country` (optional) - Kod kraju (2 litery)
- `ipaddress` (optional) - Adres IP
- `referrer` (optional) - URL źródła
- `gdpr` (optional) - Zgodność z GDPR (boolean)
- `silent` (optional) - Pomijaj podwójną opt-in (boolean)

### POST /sendy/api/unsubscribe
Usuwa subskrybenta z listy.

**Parametry:**
- `email` (required) - Adres email
- `list_id` (required) - ID listy

### POST /sendy/api/delete-subscriber
Usuwa subskrybenta z listy (pełne usunięcie).

**Parametry:**
- `email` (required) - Adres email
- `list_id` (required) - ID listy

### POST /sendy/api/check-status
Sprawdza status subskrypcji użytkownika.

**Parametry:**
- `email` (required) - Adres email
- `list_id` (required) - ID listy

## Użycie

### 1. Dostęp do list mailingowych
1. Zaloguj się do panelu administracyjnego
2. W menu bocznym kliknij "Sendy" → "Listy"
3. Zostaniesz przekierowany do strony z listą wszystkich list mailingowych

### 2. Dodawanie subskrybenta
1. Na stronie list kliknij "Dodaj" przy wybranej liście
2. Wypełnij formularz (wymagany jest tylko email)
3. Kliknij "Dodaj subskrybenta"

### 3. Usuwanie subskrybenta
1. Na stronie list kliknij "Usuń" przy wybranej liście
2. Wprowadź adres email do usunięcia
3. Kliknij "Usuń subskrybenta"

### 4. Sprawdzanie statusu
1. Na stronie szczegółów listy kliknij "Sprawdź status"
2. Wprowadź adres email
3. Kliknij "Sprawdź status"

## Logowanie

Wszystkie operacje Sendy są logowane do plików dziennika Laravel. Logi zawierają:
- Informacje o żądaniach API
- Błędy połączenia
- Szczegóły operacji na subskrybentach

## Testowanie

Uruchom testy jednostkowe:

```bash
./vendor/bin/phpunit tests/Unit/SendyServiceTest.php
```

## Bezpieczeństwo

- Wszystkie żądania API są walidowane
- Klucz API jest przechowywany w zmiennych środowiskowych
- Logi zawierają informacje o błędach bez ujawniania wrażliwych danych
- Obsługa błędów zapobiega wyciekom informacji

## Rozwiązywanie problemów

### Błąd połączenia z API
1. Sprawdź czy klucz API jest poprawny
2. Sprawdź czy URL Sendy jest dostępny
3. Sprawdź logi aplikacji w `storage/logs/`

### Brak list mailingowych
1. Sprawdź czy masz dostęp do marek w Sendy
2. Uruchom test połączenia z panelu
3. Sprawdź logi API

### Błędy subskrypcji
1. Sprawdź czy email jest poprawny
2. Sprawdź czy ID listy jest prawidłowe
3. Sprawdź logi dla szczegółów błędu

## Wsparcie

W przypadku problemów sprawdź:
1. Logi aplikacji w `storage/logs/`
2. Dokumentację Sendy API: https://sendy.co/api
3. Test połączenia w panelu administracyjnym
