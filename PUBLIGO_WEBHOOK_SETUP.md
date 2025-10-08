# Konfiguracja Webhooka Publigo.pl

## Przegląd

Webhook Publigo.pl automatycznie zapisuje uczestników do kursów na adm.pnedu.pl gdy zamówienie zostanie opłacone w Publigo.pl.

## Instalacja

### 1. Dodaj zmienne środowiskowe

Dodaj następujące zmienne do pliku `.env`:

```env
# Publigo Configuration
PUBLIGO_API_KEY=785c5a5180e74789554dd3f3bba6b15e
PUBLIGO_INSTANCE_URL=https://nowoczesna-edukacja.pl
PUBLIGO_API_VERSION=v1
PUBLIGO_API_TIMEOUT=30
PUBLIGO_WEBHOOK_URL=https://adm.pnedu.pl/api/publigo/webhook
PUBLIGO_API_SECRET=your_api_secret_here
PUBLIGO_WEBHOOK_TOKEN=your_secret_token_here
```

### 2. URL Webhooka

Główny URL webhooka: `https://adm.pnedu.pl/api/publigo/webhook`

### 3. Konfiguracja kursów

Przed skonfigurowaniem webhooka upewnij się, że kursy mają odpowiednie ustawienia:

1. **W tabeli `courses`**:
   - `source_id_old` = `"certgen_Publigo"`
   - `id_old` = ID produktu z Publigo.pl

2. **Przykład**:
   ```sql
   UPDATE courses 
   SET source_id_old = 'certgen_Publigo', id_old = 'PRODUCT_123' 
   WHERE id = 1;
   ```

### 4. Konfiguracja w Publigo.pl

1. Zaloguj się do panelu Publigo.pl
2. Przejdź do ustawień webhooków
3. Dodaj nowy webhook z następującymi parametrami:
   - **URL**: `https://adm.pnedu.pl/api/publigo/webhook`
   - **Event**: `Zamówienie zostało opłacone`
   - **Format**: JSON
   - **Token** (opcjonalny): Ustaw token z `.env`

## Jak to działa

### Format danych z Publigo.pl

Webhook odbiera dane w formacie:

```json
{
  "id": 12345,
  "status": "Zakończone",
  "customer": {
    "first_name": "Jan",
    "last_name": "Kowalski", 
    "email": "jan@example.com"
  },
  "url_params": [
    {
      "product_id": "COURSE_123",
      "external_id": "COURSE_123"
    }
  ]
}
```

### Mapowanie kursów

System szuka kursów na podstawie:
- `source_id_old` musi być ustawione na `"certgen_Publigo"`
- `id_old` w tabeli `courses` (odpowiada `product_id` z Publigo)

**Ważne:** Webhook działa tylko dla kursów z `source_id_old = "certgen_Publigo"`. 
Kursy bez tego ustawienia nie będą automatycznie zapisywać uczestników.

### Proces przetwarzania

1. **Walidacja**: Sprawdzenie czy zamówienie ma status "Zakończone"
2. **Mapowanie**: Znalezienie kursu na podstawie `product_id` lub `external_id`
3. **Sprawdzenie duplikatów**: Weryfikacja czy uczestnik już istnieje
4. **Zapis**: Utworzenie nowego uczestnika w tabeli `participants`

## Testowanie

### 1. Komenda Artisan

```bash
# Test webhooka
php artisan publigo:test-webhook --course-id=COURSE_123 --email=test@example.com

# Konfiguracja kursów
php artisan publigo:configure-courses --course-id=1 --publigo-id=PRODUCT_123
php artisan publigo:configure-courses --list
php artisan publigo:configure-courses --all
```

### 2. Komendy konfiguracyjne

**Lista kursów skonfigurowanych dla Publigo:**
```bash
php artisan publigo:configure-courses --list
```

**Konfiguracja pojedynczego kursu:**
```bash
php artisan publigo:configure-courses --course-id=1 --publigo-id=PRODUCT_123
```

**Konfiguracja wszystkich kursów:**
```bash
php artisan publigo:configure-courses --all
```

### 3. Panel administracyjny

Przejdź do: `/publigo/webhooks` i użyj formularza testowego.

### 4. Logi

Sprawdź logi w: `/publigo/webhooks/logs`

## Bezpieczeństwo

### Middleware

Webhook używa middleware `PubligoWebhookMiddleware` który:
- Sprawdza metodę HTTP (tylko POST)
- Weryfikuje Content-Type (application/json)
- Loguje wszystkie requesty
- Może weryfikować token (opcjonalnie)

### Weryfikacja tokenu

Aby włączyć weryfikację tokenu, odkomentuj kod w `PubligoWebhookMiddleware.php`:

```php
$token = $request->header('X-Publigo-Token');
if ($token !== config('services.publigo.webhook_token')) {
    return response()->json(['message' => 'Unauthorized'], 401);
}
```

## Rozwiązywanie problemów

### Kurs nie zostaje znaleziony

1. Sprawdź czy kurs ma `source_id_old = "certgen_Publigo"`
2. Sprawdź czy `product_id` z Publigo odpowiada `id_old` w tabeli `courses`
3. Sprawdź logi w `/publigo/webhooks/logs`

### Uczestnik nie zostaje dodany

1. Sprawdź czy email nie jest już zajęty dla danego kursu
2. Sprawdź logi błędów
3. Sprawdź czy zamówienie ma status "Zakończone"

### Webhook nie działa

1. Sprawdź czy URL jest poprawny
2. Sprawdź czy serwer jest dostępny
3. Sprawdź logi błędów w Publigo.pl
4. Sprawdź logi aplikacji

## Monitoring

### Logi

Wszystkie webhooki są logowane w `storage/logs/laravel.log` z tagiem "Publigo webhook".

### Panel administracyjny

- `/publigo/webhooks` - Zarządzanie i testowanie
- `/publigo/webhooks/logs` - Przeglądanie logów

## Struktura bazy danych

### Tabela courses
- `id` - ID kursu
- `id_old` - Stare ID (musi odpowiadać product_id z Publigo)
- `source_id_old` - ID źródłowe (musi być "certgen_Publigo" dla webhooków)

### Tabela participants
- `course_id` - Powiązanie z kursem
- `first_name`, `last_name`, `email` - Dane uczestnika
- `order` - Kolejność uczestnika w kursie

## Aktualizacje

### Dodanie nowych pól

Jeśli Publigo.pl doda nowe pola, zaktualizuj:
1. Walidację w `PubligoController::webhook()`
2. Mapowanie danych
3. Dokumentację

### Zmiana formatu danych

Jeśli format danych z Publigo.pl się zmieni:
1. Zaktualizuj walidację
2. Dostosuj mapowanie
3. Przetestuj z nowymi danymi

## Testowanie API Publigo.pl

### 1. Przycisk testowy

W menu sprzedaży zamówień (`/sales`) znajduje się przycisk **"API Publigo - test"**, który prowadzi do panelu testowania API.

### 2. Panel testowania API

Przejdź do: `/publigo/test-api`

Panel wykonuje automatycznie 3 testy:

1. **Test połączenia** - Sprawdza dostępność API
2. **Test kursów** - Pobiera listę dostępnych kursów
3. **Test webhooków** - Sprawdza skonfigurowane webhooki

### 3. Konfiguracja API

Upewnij się, że w pliku `.env` masz ustawione:

```env
PUBLIGO_API_KEY=785c5a5180e74789554dd3f3bba6b15e
PUBLIGO_INSTANCE_URL=https://nowoczesna-edukacja.pl
PUBLIGO_API_VERSION=v1
PUBLIGO_API_TIMEOUT=30
```

### 4. Interpretacja wyników

- **Zielone alerty** - Testy zakończone sukcesem
- **Żółte alerty** - Błędy po stronie klienta (np. nieprawidłowy klucz API)
- **Czerwone alerty** - Błędy po stronie serwera (np. niedostępność API)

### 5. Rozwiązywanie problemów z API

#### Błąd autoryzacji (401)
- Sprawdź czy klucz API jest poprawny
- Sprawdź czy klucz ma odpowiednie uprawnienia

#### Błąd połączenia
- Sprawdź czy URL instancji jest poprawny
- Sprawdź czy instancja Publigo.pl jest dostępna

#### Błąd timeout
- Zwiększ wartość `PUBLIGO_API_TIMEOUT` w `.env`
- Sprawdź połączenie internetowe

### 6. Logi API

Wszystkie testy API są logowane w `storage/logs/laravel.log` z tagiem "Publigo API test".
