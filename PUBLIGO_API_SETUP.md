# Wdrożenie połączenia z Publigo.pl - Instrukcja krok po kroku

## Przegląd

Ten dokument opisuje krok po kroku wdrożenie połączenia z API Publigo.pl dla instancji https://nowoczesna-edukacja.pl.

**Ważne:** Publigo.pl jest zbudowane na WordPress z wtyczką WP IDEA, co oznacza, że używa WordPress REST API z endpointami `/wp-json/wp-idea/v1/`.

## Krok 1: Konfiguracja zmiennych środowiskowych

### 1.1 Edytuj plik `.env`

Dodaj następujące zmienne do pliku `.env`:

```env
# Publigo API Configuration
PUBLIGO_API_KEY=785c5a5180e74789554dd3f3bba6b15e
PUBLIGO_INSTANCE_URL=https://nowoczesna-edukacja.pl
PUBLIGO_API_VERSION=v1
PUBLIGO_API_TIMEOUT=30
PUBLIGO_WEBHOOK_URL=https://adm.pnedu.pl/api/publigo/webhook
PUBLIGO_API_SECRET=your_api_secret_here
```

### 1.2 Wyjaśnienie zmiennych

- `PUBLIGO_API_KEY` - Twój klucz API v1 (Shoper, Woocommerce, Presta)
- `PUBLIGO_INSTANCE_URL` - URL Twojej instancji Publigo.pl
- `PUBLIGO_API_VERSION` - Wersja API (domyślnie v1)
- `PUBLIGO_API_TIMEOUT` - Timeout dla żądań API w sekundach
- `PUBLIGO_WEBHOOK_URL` - URL webhooka w Twojej aplikacji
- `PUBLIGO_API_SECRET` - Sekretny klucz do weryfikacji webhooków (opcjonalny)
- `PUBLIGO_API_TYPE` - Typ API (domyślnie wp_idea)
- `PUBLIGO_WP_IDEA_ENDPOINT` - Endpoint WP IDEA API (domyślnie /wp-json/wp-idea/v1)

## Krok 2: Sprawdzenie instalacji

### 2.1 Zależności

Upewnij się, że Guzzle HTTP Client jest zainstalowany:

```bash
composer require guzzlehttp/guzzle
```

### 2.2 Kontroler

Sprawdź, czy `PubligoController` ma metodę `testApi()`:

```bash
php artisan route:list --name=publigo.test-api
```

Powinieneś zobaczyć:
```
GET|HEAD  publigo/test-api .................... publigo.test-api › PubligoController@testApi
```

## Krok 3: Testowanie połączenia

### 3.1 Przycisk testowy

1. Przejdź do menu sprzedaży zamówień: `/sales`
2. Znajdź przycisk **"API Publigo - test"** (niebieski przycisk z ikoną wtyczki)
3. Kliknij przycisk

### 3.2 Panel testowania

Panel automatycznie wykona 3 testy zgodnie z dostępnymi endpointami WP IDEA:

1. **Test połączenia** - Sprawdza dostępność WP IDEA API (`GET /wp-json/wp-idea/v1/products`)
2. **Test kursów** - Pobiera listę dostępnych kursów z endpointu (`GET /wp-json/wp-idea/v1/products`)
3. **Test zamówień** - Testuje tworzenie zamówienia (`POST /wp-json/wp-idea/v1/orders`)

### 3.3 Interpretacja wyników

- 🟢 **Zielone alerty** - Testy zakończone sukcesem
- 🟡 **Żółte alerty** - Błędy po stronie klienta (np. nieprawidłowy klucz API)
- 🔴 **Czerwone alerty** - Błędy po stronie serwera (np. niedostępność API)

## Krok 4: Rozwiązywanie problemów

### 4.1 Błąd autoryzacji (401)

**Objawy:**
- Żółty alert "Połączenie nieudane"
- Status: "Client error"
- Kod HTTP: 401

**Rozwiązanie:**
1. Sprawdź czy klucz API jest poprawny
2. Sprawdź czy klucz ma odpowiednie uprawnienia w Publigo.pl
3. Sprawdź czy klucz nie wygasł

### 4.2 Błąd połączenia

**Objawy:**
- Czerwony alert "Połączenie nieudane"
- Status: "Error"
- Brak kodu HTTP

**Rozwiązanie:**
1. Sprawdź czy URL instancji jest poprawny
2. Sprawdź czy instancja Publigo.pl jest dostępna
3. Sprawdź połączenie internetowe
4. Sprawdź czy nie ma blokad firewall

### 4.3 Błąd timeout

**Objawy:**
- Czerwony alert "Połączenie nieudane"
- Status: "Error"
- Wiadomość o timeout

**Rozwiązanie:**
1. Zwiększ wartość `PUBLIGO_API_TIMEOUT` w `.env`
2. Sprawdź połączenie internetowe
3. Sprawdź czy serwer Publigo.pl nie jest przeciążony

## Krok 5: Konfiguracja webhooków

### 5.1 W Publigo.pl

1. Zaloguj się do panelu Publigo.pl
2. Przejdź do ustawień webhooków
3. Dodaj nowy webhook:
   - **URL**: `https://adm.pnedu.pl/api/publigo/webhook`
   - **Event**: `Zamówienie zostało opłacone`
   - **Format**: JSON
   - **Token** (opcjonalny): Ustaw token z `.env`

### 5.2 W Twojej aplikacji

1. Przejdź do: `/publigo/webhooks`
2. Użyj formularza testowego do sprawdzenia webhooka
3. Sprawdź logi w: `/publigo/webhooks/logs`

## Krok 6: Monitoring i logi

### 6.1 Logi aplikacji

Wszystkie testy API są logowane w `storage/logs/laravel.log` z tagiem "Publigo API test".

### 6.2 Panel administracyjny

- `/publigo/test-api` - Testowanie API
- `/publigo/webhooks` - Zarządzanie webhookami
- `/publigo/webhooks/logs` - Przeglądanie logów

## Krok 7: Testowanie end-to-end

### 7.1 Test zamówienia

1. Utwórz testowe zamówienie w Publigo.pl
2. Opłać zamówienie
3. Sprawdź czy uczestnik został automatycznie dodany do kursu
4. Sprawdź logi webhooka

### 7.2 Test webhooka

1. Użyj formularza testowego w `/publigo/webhooks`
2. Wprowadź dane testowe:
   - ID kursu: `COURSE_123`
   - Email: `test@example.com`
   - Imię: `Jan`
   - Nazwisko: `Testowy`

## Krok 8: Produkcja

### 8.1 Bezpieczeństwo

1. Ustaw `APP_DEBUG=false` w `.env`
2. Ustaw `APP_ENV=production` w `.env`
3. Sprawdź czy wszystkie klucze API są bezpieczne
4. Włącz weryfikację tokenów webhooków

### 8.2 Monitoring

1. Skonfiguruj alerty dla błędów API
2. Monitoruj logi webhooków
3. Sprawdzaj regularnie status połączenia

## WP IDEA API

### Endpointy

Publigo.pl używa WordPress REST API z wtyczką WP IDEA zgodnie z [oficjalną dokumentacją](https://documenter.getpostman.com/view/6467622/SzKVSyS5?version=latest#9f53abdb-d1ae-4bb5-b4af-479063c76bbe):

- **Produkty (kursy)**: `GET /wp-json/wp-idea/v1/products`
- **Tworzenie zamówień**: `POST /wp-json/wp-idea/v1/orders`

### Autoryzacja

WP IDEA używa systemu `nonce` i `token`:
- `nonce` - Unikalny identyfikator sesji
- `token` - Token MD5 generowany na podstawie klucza API

### Format danych

**Produkty:**
```json
{
  "21": "Podstawowy",
  "59": "Pakiet podstawowy",
  "61": "Darmowy"
}
```

**Tworzenie zamówienia:**
```json
{
  "source": {
    "platform": "Test Platform",
    "id": 71144,
    "url": "https://test-platform.com"
  },
  "customer": {
    "email": "waldemar.grabowski@hostnet.pl",
    "first_name": "Waldemar",
    "last_name": "Grabowski"
  },
  "shipping_address": {
    "address1": "Test Street",
    "address2": "",
    "zip_code": "00-000",
    "city": "Warszawa",
    "country_code": "PL"
  }
}
```

## Wsparcie

### Dokumentacja

- [PUBLIGO_WEBHOOK_SETUP.md](./PUBLIGO_WEBHOOK_SETUP.md) - Szczegółowa dokumentacja webhooków
- [WP IDEA API Documentation](https://documenter.getpostman.com/view/6467622/SzKVSyS5?version=latest#9f53abdb-d1ae-4bb5-b4af-479063c76bbe) - Oficjalna dokumentacja WP IDEA API

### Logi i debugowanie

1. Sprawdź logi Laravel: `storage/logs/laravel.log`
2. Użyj panelu administracyjnego: `/publigo/test-api`
3. Sprawdź status tras: `php artisan route:list --name=publigo`

### Kontakt

W przypadku problemów:
1. Sprawdź logi aplikacji
2. Sprawdź dokumentację
3. Skontaktuj się z zespołem wsparcia
