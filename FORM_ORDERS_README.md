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

## Więcej informacji

Zobacz pełną dokumentację: `FORM_ORDERS_MIGRATION.md`

---

**Utworzone:** 17 października 2025
**Wersja:** 1.0

