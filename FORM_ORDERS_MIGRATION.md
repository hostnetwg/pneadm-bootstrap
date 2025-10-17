# Migracja tabeli zamowienia_FORM do form_orders

## Przegląd

Dokument opisuje mapowanie pól między tabelą `zamowienia_FORM` z bazy `certgen` (stary system) a tabelą `form_orders` w bazie `pneadm` (nowy system).

## Informacje o tabelach

- **Stara tabela**: `zamowienia_FORM` (baza: `certgen`, połączenie: `mysql_certgen`)
- **Nowa tabela**: `form_orders` (baza: `pneadm`, połączenie: `mysql`)
- **Model starej tabeli**: Brak dedykowanego modelu (używane DB::connection)
- **Model nowej tabeli**: `App\Models\FormOrder`

## Mapowanie pól

| Pole w certgen (zamowienia_FORM) | Pole w pneadm (form_orders) | Typ danych | Opis |
|----------------------------------|----------------------------|------------|------|
| `id` | `id` | bigint unsigned | Klucz główny (auto increment) |
| `data_zamowienia` | `order_date` | timestamp | Data złożenia zamówienia |
| `produkt_nazwa` | `product_name` | varchar(500) | Nazwa produktu/szkolenia |
| `produkt_cena` | `product_price` | decimal(10,2) | Cena produktu |
| `idProdPubligo` | `publigo_product_id` | varchar(255) | ID produktu w systemie Publigo |
| `price_idProdPubligo` | `publigo_price_id` | varchar(255) | ID ceny w systemie Publigo |
| `publigo_sent` | `publigo_sent` | tinyint | Czy wysłano do Publigo (0/1) |
| `publigo_sent_at` | `publigo_sent_at` | timestamp | Data wysłania do Publigo |
| `konto_imie_nazwisko` | `participant_name` | varchar(255) | Imię i nazwisko uczestnika |
| `konto_email` | `participant_email` | varchar(255) | Email uczestnika |
| `nab_nazwa` | `buyer_name` | varchar(500) | Nazwa nabywcy/firmy |
| `nab_adres` | `buyer_address` | varchar(500) | Adres nabywcy |
| `nab_kod` | `buyer_postal_code` | varchar(10) | Kod pocztowy nabywcy |
| `nab_poczta` | `buyer_city` | varchar(255) | Miejscowość nabywcy |
| `nab_nip` | `buyer_nip` | varchar(20) | NIP nabywcy |
| `odb_nazwa` | `recipient_name` | varchar(500) | Nazwa odbiorcy |
| `odb_adres` | `recipient_address` | varchar(500) | Adres odbiorcy |
| `odb_kod` | `recipient_postal_code` | varchar(10) | Kod pocztowy odbiorcy |
| `odb_poczta` | `recipient_city` | varchar(255) | Miejscowość odbiorcy |
| `nr_fakury` | `invoice_number` | varchar(100) | Numer faktury |
| `zam_email` | `invoice_email` | varchar(255) | Email do wysyłki faktury |
| `zam_tel` | `contact_phone` | varchar(50) | Telefon kontaktowy |
| `faktura_uwagi` | `invoice_notes` | text | Uwagi do faktury |
| `faktura_odroczenie` | `invoice_payment_delay` | integer | Odroczenie płatności (dni) |
| `status_zakonczone` | `status_completed` | tinyint | Status zakończenia (0/1) |
| `przetworzone` | `processed` | tinyint | Czy przetworzone (0/1) |
| `notatki` | `notes` | text | Notatki wewnętrzne |
| `data_przetworzenia` | `processed_at` | timestamp | Data przetworzenia |
| `data_update` | `updated_manually_at` | timestamp | Data ręcznej aktualizacji |
| `ip` | `ip_address` | varchar(45) | Adres IP użytkownika |
| `fb` | `fb_source` | varchar(255) | Źródło Facebook/marketing |
| `created_at` | `created_at` | timestamp | Data utworzenia rekordu |
| `updated_at` | `updated_at` | timestamp | Data ostatniej aktualizacji |

## Różnice i ulepszenia

### 1. Nazewnictwo
- Stara tabela używała polskich nazw pól i skrótów (np. `nab_`, `odb_`, `zam_`)
- Nowa tabela używa angielskich nazw opisowych (np. `buyer_`, `recipient_`, `invoice_`)

### 2. Indeksy
Nowa tabela zawiera dodatkowe indeksy dla lepszej wydajności:
- `idx_order_date` - szybkie wyszukiwanie po dacie zamówienia
- `idx_participant_email` - wyszukiwanie po emailu uczestnika
- `idx_invoice_email` - wyszukiwanie po emailu faktury
- `idx_invoice_number` - wyszukiwanie po numerze faktury
- `idx_status_completed` - filtrowanie po statusie
- `idx_processed` - filtrowanie po przetworzeniu
- `idx_publigo_sent` - filtrowanie wysłanych do Publigo
- `idx_status_invoice` - złożony indeks dla nowych zamówień

### 3. Dokumentacja
- Wszystkie pola w nowej tabeli mają komentarze w bazie danych
- Model zawiera szczegółową dokumentację PHPDoc

### 4. Model Eloquent
Nowy model `FormOrder` zawiera:
- **Scopes** (zakresy zapytań):
  - `new()` - nowe zamówienia bez faktury
  - `completed()` - zamówienia zakończone
  - `withInvoice()` - zamówienia z fakturą
  - `sentToPubligo()` - wysłane do Publigo
  - `notSentToPubligo()` - niewysłane do Publigo (ale gotowe)

- **Accessors** (akcesory):
  - `is_new` - czy zamówienie jest nowe
  - `has_invoice` - czy ma fakturę
  - `is_sent_to_publigo` - czy wysłane do Publigo
  - `formatted_nip` - NIP bez formatowania
  - `buyer_full_address` - pełny adres nabywcy
  - `recipient_full_address` - pełny adres odbiorcy

## Przykłady użycia

### Stary sposób (baza certgen)
```php
// Pobieranie danych z bazy certgen
$zamowienia = DB::connection('mysql_certgen')
    ->table('zamowienia_FORM')
    ->where('status_zakonczone', 0)
    ->get();
```

### Nowy sposób (baza pneadm)
```php
// Pobieranie danych z bazy pneadm
$orders = FormOrder::new()->get();

// Lub z dodatkową logiką
$orders = FormOrder::new()
    ->where('participant_email', 'like', '%@example.com')
    ->orderBy('order_date', 'desc')
    ->paginate(50);
```

## Migracja danych

### Automatyczna migracja za pomocą komendy Artisan

Do dyspozycji jest gotowa komenda Artisan, która automatycznie migruje dane:

```bash
# Podstawowe użycie - migracja wszystkich danych
php artisan migrate:form-orders-data

# Migracja z wyczyszczeniem tabeli docelowej (!)
php artisan migrate:form-orders-data --fresh

# Testowa migracja tylko pierwszych 10 rekordów
php artisan migrate:form-orders-data --limit=10

# Pomiń pierwsze 100 rekordów (przydatne przy wznawianiu migracji)
php artisan migrate:form-orders-data --skip=100

# Kombinacja opcji
php artisan migrate:form-orders-data --fresh --limit=100
```

**Funkcje komendy:**
- ✅ Automatyczna walidacja połączeń z bazami danych
- ✅ Sprawdzanie czy tabela docelowa istnieje
- ✅ Pasek postępu w czasie rzeczywistym
- ✅ Transakcje - w przypadku błędu wszystko jest cofane (rollback)
- ✅ Szczegółowe raporty błędów
- ✅ Statystyki po zakończeniu migracji
- ✅ Opcje testowe (--limit, --skip)
- ✅ Opcja czyszczenia tabeli przed migracją (--fresh)

### Ręczna migracja danych (opcjonalnie)

Jeśli potrzebujesz większej kontroli, możesz użyć poniższego kodu w Tinker lub własnym skrypcie:

```php
use App\Models\FormOrder;
use Illuminate\Support\Facades\DB;

// Pobranie danych ze starej bazy
$oldOrders = DB::connection('mysql_certgen')
    ->table('zamowienia_FORM')
    ->get();

foreach ($oldOrders as $oldOrder) {
    FormOrder::create([
        'order_date' => $oldOrder->data_zamowienia,
        'product_name' => $oldOrder->produkt_nazwa,
        'product_price' => $oldOrder->produkt_cena,
        'publigo_product_id' => $oldOrder->idProdPubligo,
        'publigo_price_id' => $oldOrder->price_idProdPubligo,
        'publigo_sent' => $oldOrder->publigo_sent,
        'publigo_sent_at' => $oldOrder->publigo_sent_at,
        'participant_name' => $oldOrder->konto_imie_nazwisko,
        'participant_email' => $oldOrder->konto_email,
        'buyer_name' => $oldOrder->nab_nazwa,
        'buyer_address' => $oldOrder->nab_adres,
        'buyer_postal_code' => $oldOrder->nab_kod,
        'buyer_city' => $oldOrder->nab_poczta,
        'buyer_nip' => $oldOrder->nab_nip,
        'recipient_name' => $oldOrder->odb_nazwa,
        'recipient_address' => $oldOrder->odb_adres,
        'recipient_postal_code' => $oldOrder->odb_kod,
        'recipient_city' => $oldOrder->odb_poczta,
        'invoice_number' => $oldOrder->nr_fakury,
        'invoice_email' => $oldOrder->zam_email,
        'contact_phone' => $oldOrder->zam_tel,
        'invoice_notes' => $oldOrder->faktura_uwagi,
        'invoice_payment_delay' => $oldOrder->faktura_odroczenie,
        'status_completed' => $oldOrder->status_zakonczone,
        'processed' => $oldOrder->przetworzone ?? 0,
        'notes' => $oldOrder->notatki,
        'processed_at' => $oldOrder->data_przetworzenia,
        'updated_manually_at' => $oldOrder->data_update,
        'ip_address' => $oldOrder->ip,
        'fb_source' => $oldOrder->fb,
        'created_at' => $oldOrder->created_at,
        'updated_at' => $oldOrder->updated_at,
    ]);
}
```

## Uruchomienie migracji

```bash
# Uruchomienie migracji w bazie pneadm
php artisan migrate

# Sprawdzenie statusu migracji
php artisan migrate:status

# W razie potrzeby rollback
php artisan migrate:rollback
```

## Kontrolery do aktualizacji

Po migracji danych należy zaktualizować następujące kontrolery:
1. `SalesController` - zmienić z DB::connection na FormOrder model
2. `DashboardController` - zaktualizować statystyki
3. Wszystkie widoki używające tabeli `zamowienia_FORM`

## Uwagi końcowe

- Tabela `form_orders` jest przygotowana do przyszłościowego rozwoju systemu
- Zachowano kompatybilność ze starym systemem poprzez szczegółowe mapowanie
- Wszystkie pola mają właściwe typy i indeksy dla optymalnej wydajności
- Model zawiera pomocne metody ułatwiające pracę z danymi

## Status

- ✅ Migracja utworzona: `2025_10_17_205515_create_form_orders_table.php`
- ✅ Model utworzony: `app/Models/FormOrder.php`
- ✅ Komenda migracji danych: `app/Console/Commands/MigrateFormOrdersData.php`
- ⏳ Uruchomienie migracji struktury: Do wykonania (`php artisan migrate`)
- ⏳ Migracja danych: Do wykonania (`php artisan migrate:form-orders-data`)
- ⏳ Aktualizacja kontrolerów: Do wykonania
- ⏳ Aktualizacja widoków: Do wykonania

## Kolejne kroki

### 1. Uruchomienie migracji struktury (w środowisku produkcyjnym lub developerskim)
```bash
php artisan migrate
```

### 2. Testowa migracja danych (zalecane najpierw przetestować)
```bash
# Test z tylko 10 rekordami
php artisan migrate:form-orders-data --limit=10

# Sprawdź czy dane się poprawnie przeniosły
php artisan tinker
>>> FormOrder::count()
>>> FormOrder::first()
```

### 3. Pełna migracja danych
```bash
# Gdy test przejdzie pomyślnie, uruchom pełną migrację
php artisan migrate:form-orders-data
```

### 4. Weryfikacja migracji
```bash
php artisan tinker
>>> FormOrder::count()
>>> DB::connection('mysql_certgen')->table('zamowienia_FORM')->count()
# Sprawdź czy liczby się zgadzają
```

### 5. Aktualizacja aplikacji
Po pomyślnej migracji danych, stopniowo aktualizuj kontrolery i widoki, aby korzystały z nowej tabeli `form_orders` zamiast `zamowienia_FORM`.

