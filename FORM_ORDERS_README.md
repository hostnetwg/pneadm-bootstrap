# Tabela form_orders - Szybki start

## Co zostało zrobione?

Stworzono odpowiednik tabeli `zamowienia_FORM` z bazy **certgen** w bazie **pneadm** pod nazwą `form_orders`.

## Pliki utworzone

1. **Migracja bazy danych**
   - `database/migrations/2025_10_17_205515_create_form_orders_table.php`
   - Tworzy tabelę `form_orders` w bazie pneadm (MySQL)

2. **Model Eloquent**
   - `app/Models/FormOrder.php`
   - Zawiera pomocne metody i scopes do pracy z danymi

3. **Komenda migracji danych**
   - `app/Console/Commands/MigrateFormOrdersData.php`
   - Automatycznie migruje dane z certgen do pneadm

4. **Dokumentacja**
   - `FORM_ORDERS_MIGRATION.md` - szczegółowa dokumentacja
   - `FORM_ORDERS_README.md` - ten plik (szybki start)

## Jak uruchomić?

### Krok 1: Utworzenie tabeli
```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
php artisan migrate
```

### Krok 2: Migracja danych (testowo)
```bash
# Najpierw test z 10 rekordami
php artisan migrate:form-orders-data --limit=10
```

### Krok 3: Pełna migracja
```bash
# Gdy test przejdzie pomyślnie
php artisan migrate:form-orders-data
```

## Mapowanie pól (skrócone)

| Stara nazwa (certgen) | Nowa nazwa (pneadm) |
|----------------------|---------------------|
| `data_zamowienia` | `order_date` |
| `produkt_nazwa` | `product_name` |
| `konto_imie_nazwisko` | `participant_name` |
| `konto_email` | `participant_email` |
| `nab_nazwa` | `buyer_name` |
| `odb_nazwa` | `recipient_name` |
| `nr_fakury` | `invoice_number` |
| `zam_email` | `invoice_email` |

*Zobacz `FORM_ORDERS_MIGRATION.md` dla pełnego mapowania*

## Przykłady użycia

### Pobieranie nowych zamówień
```php
use App\Models\FormOrder;

// Nowe zamówienia (bez faktury)
$newOrders = FormOrder::new()->get();

// Z fakturą
$withInvoice = FormOrder::withInvoice()->get();

// Wysłane do Publigo
$sentToPubligo = FormOrder::sentToPubligo()->get();
```

### Wyszukiwanie
```php
// Po emailu uczestnika
$orders = FormOrder::where('participant_email', 'jan@example.com')->get();

// Z ostatniego miesiąca
$orders = FormOrder::where('order_date', '>=', now()->subMonth())->get();
```

### Akcesory (pomocne właściwości)
```php
$order = FormOrder::first();

$order->is_new;                    // bool - czy nowe (bez faktury)
$order->has_invoice;               // bool - czy ma fakturę
$order->is_sent_to_publigo;        // bool - czy wysłane do Publigo
$order->formatted_nip;             // string - NIP bez formatowania
$order->buyer_full_address;        // string - pełny adres nabywcy
$order->recipient_full_address;    // string - pełny adres odbiorcy
```

## Zalety nowej tabeli

✅ **Angielskie nazwy pól** - zgodne z konwencją Laravel
✅ **Indeksy** - szybsze wyszukiwanie
✅ **Model Eloquent** - łatwa praca z danymi
✅ **Scopes** - gotowe zapytania
✅ **Akcesory** - pomocne właściwości
✅ **Dokumentacja** - komentarze w bazie i kodzie
✅ **Komenda migracji** - automatyczna migracja danych

## Różnice względem starej tabeli

| Aspekt | Stara (certgen) | Nowa (pneadm) |
|--------|----------------|---------------|
| Połączenie | `mysql_certgen` | `mysql` |
| Język pól | Polski (skróty) | Angielski |
| Model | Brak | `FormOrder` |
| Indeksy | Podstawowe | Zoptymalizowane |
| Dokumentacja | Brak | Pełna |

## Kontynuacja migracji

Po przeniesieniu danych należy zaktualizować:

1. **SalesController** - zmienić z `DB::connection('mysql_certgen')->table('zamowienia_FORM')` na `FormOrder`
2. **DashboardController** - zaktualizować statystyki
3. **Widoki** - `resources/views/sales/` 

Można to robić **stopniowo** - stary system może działać równolegle.

## iFirma - faktura z odbiorcą

Wystawianie faktury z odbiorcą (przycisk „Wystaw Fakturę iFirma z Odbiorcą”) używa
struktury `Kontrahent.OdbiorcaNaFakturze`, a nie pola root `DodatkowyPodmiot`.

Wymagane minimum dla odbiorcy:
- `UzywajDanychOdbiorcyNaFakturach`
- `Nazwa`
- `KodPocztowy`
- `Miejscowosc`

Implementacja: `FormOrdersController::createIfirmaInvoiceWithReceiver()`.

## iFirma - integracja z KSeF (Krajowy System e-Faktur)

### Funkcjonalność

System umożliwia automatyczne wystawienie faktury w iFirma i przesłanie jej do KSeF w jednym procesie.

### Przycisk "Wystaw fakturę i prześlij do KSeF"

Czerwony przycisk na stronie szczegółów zamówienia (`/form-orders/{id}`) umożliwia:
1. Wystawienie faktury w iFirma (z odbiorcą)
2. Automatyczne przesłanie faktury do KSeF
3. Opcjonalne wysłanie faktury na e-mail (z numerem KSeF)

### Checkbox "Wyślij automatycznie na e-mail"

- Jeśli zaznaczony: faktura zostanie automatycznie wysłana na e-mail po przesłaniu do KSeF
- E-mail zawiera numer KSeF w treści wiadomości
- Adresy e-mail są wyświetlane w nawiasach obok checkboxa

### Flow działania

```
1. Kliknięcie "Wystaw fakturę i prześlij do KSeF"
   ↓
2. Sprawdzenie statusu faktury w bazie
   ↓
3. Wystawienie faktury w iFirma → otrzymanie Identyfikator
   ↓
4. Przesłanie faktury do KSeF → otrzymanie numeru KSeF
   ↓
5. (Opcjonalnie) Wysłanie e-mail z fakturą (z numerem KSeF)
   ↓
6. Zapis wszystkich danych w bazie
```

### Pola w bazie danych

Dodane pola w tabeli `form_orders`:
- `ksef_number` (string, nullable) - Numer KSeF faktury
- `ksef_sent_at` (timestamp, nullable) - Data i czas przesłania do KSeF
- `ksef_status` (enum: 'pending', 'sent', 'failed', nullable) - Status przesłania
- `ksef_error` (text, nullable) - Szczegóły błędu przesłania

### Obsługa błędów

**Scenariusz 1: Błąd wystawienia faktury**
- Proces przerywany
- Faktura nie jest wystawiana
- Komunikat błędu wyświetlany użytkownikowi

**Scenariusz 2: Błąd przesyłania do KSeF**
- Faktura już wystawiona w iFirma
- Status KSeF: `failed`
- Błąd zapisany w `ksef_error`
- E-mail **nie jest wysyłany** (faktura bez numeru KSeF)
- Możliwość retry (przycisk "Spróbuj ponownie")

**Scenariusz 3: Błąd wysyłki e-mail**
- Faktura wystawiona i przesłana do KSeF
- Numer KSeF zapisany w bazie
- Status KSeF: `sent`
- Ostrzeżenie o błędzie e-mail
- Możliwość ponownego wysłania e-mail

### API Endpoint

```
POST /form-orders/{id}/ifirma/invoice-with-ksef
```

**Request body:**
```json
{
  "send_email": true|false,
  "force": true|false  // opcjonalne, dla ponownego wystawienia
}
```

**Response (sukces):**
```json
{
  "success": true,
  "message": "Faktura została wystawiona w iFirma.pl i przesłana do KSeF (nr: XXX) i wysłana na: email@example.com",
  "invoice_id": "123456",
  "invoice_number": "87/2/2026",
  "ksef_number": "KSeF/2026/123456",
  "ksef_sent_at": "2026-02-11 13:28:28",
  "email_sent": true,
  "emails_sent": ["email@example.com"],
  "email_errors": []
}
```

**Response (błąd KSeF):**
```json
{
  "success": false,
  "error": "Faktura została wystawiona, ale nie udało się przesłać do KSeF",
  "step": "ksef_send",
  "invoice_id": "123456",
  "invoice_number": "87/2/2026",
  "ksef_error": "Szczegóły błędu",
  "can_retry": true
}
```

### Implementacja

**Backend:**
- `FormOrdersController::createIfirmaInvoiceWithKsef()` - główna metoda
- `IfirmaApiService::sendInvoiceToKsef()` - wysyłka do KSeF przez API

**Frontend:**
- `checkAndCreateInvoiceWithKsef()` - sprawdzenie statusu przed wystawieniem
- `createIfirmaInvoiceWithKsef()` - główna funkcja JavaScript
- Progress indicator podczas procesu

### Logowanie

Wszystkie operacje są logowane w `storage/logs/laravel.log`:
- `iFirma Invoice With KSeF: Przesyłanie do KSeF` - przed wysyłką
- `iFirma Invoice With KSeF: Przesłane do KSeF` - po sukcesie
- `iFirma Invoice With KSeF: Błąd przesyłania do KSeF` - przy błędzie
- `iFirma: Faktura przesłana do KSeF - pełna odpowiedź` - pełna struktura odpowiedzi API

### Uwagi

1. **Kolejność operacji**: Faktura → KSeF → E-mail (nie można odwrócić)
2. **Numer KSeF**: Może nie być dostępny bezpośrednio w odpowiedzi API - system próbuje pobrać go z szczegółów faktury
3. **Status KSeF**: Zawsze zapisywany (`sent` lub `failed`), nawet jeśli numer KSeF nie jest dostępny
4. **E-mail z KSeF**: Jeśli faktura została wysłana na e-mail bez KSeF, nie można już później przesłać jej do KSeF (ograniczenie iFirma)

## Więcej informacji

Zobacz pełną dokumentację: `FORM_ORDERS_MIGRATION.md`

---

**Utworzone:** 17 października 2025
**Wersja:** 1.0

