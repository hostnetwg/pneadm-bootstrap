# Analiza problemu: Wystawianie faktury krajowej przez API iFirma

## 🎯 Podsumowanie problemu

**Problem:** Wystawianie faktury krajowej (nie pro-forma) przez API iFirma zwraca błąd:
```json
{
  "response": {
    "Kod": 200,
    "Informacja": "Niepoprawna zawartość żądania - nie można utworzyć obiektu na podstawie zawartości żądania."
  }
}
```

**Kontekst konta użytkownika:**
- ✅ Konto jest **zwolnione z VAT** (Art. 43 ust. 1 pkt 29 lit. b))
- ✅ Konto jest na **ryczałcie** (stawka ryczałtu: 8.5% = 0.085)
- ✅ PRO-FORMA działa **poprawnie**
- ❌ FAKTURA KRAJOWA **nie działa** pomimo wielu prób dopasowania struktury JSON

---

## ✅ Co działa: PRO-FORMA

### Struktura JSON działającej PRO-FORMA:
```json
{
  "LiczOd": "NET",
  "TypFakturyKrajowej": "SPRZ",
  "DataWystawienia": "2025-11-02",
  "SposobZaplaty": "PRZ",
  "RodzajPodpisuOdbiorcy": "BWO",
  "NumerZamowienia": "5117",
  "TerminPlatnosci": "2025-11-16",
  "Kontrahent": {
    "Nazwa": "Gmina Bieżuń",
    "Kraj": "PL",
    "Ulica": "ul. Warszawska 5",
    "KodPocztowy": "09-320",
    "Miejscowosc": "Bieżuń",
    "NIP": "5110265245"
  },
  "Pozycje": [
    {
      "NazwaPelna": "SZKOLENIE: ...",
      "Ilosc": 1.0,
      "CenaJednostkowa": 365.0,
      "Jednostka": "sztuk",
      "TypStawkiVat": "ZW",
      "PodstawaPrawna": "Art. 43 ust. 1 pkt 29 lit. b)"
    }
  ],
  "Uwagi": "ODBIORCA:\n..."
}
```

### Kluczowe różnice PRO-FORMA:
- **Endpoint:** `fakturaproformakraj.json`
- **Pole `TypFakturyKrajowej`:** WYMAGANE (`SPRZ`)
- **Pole `DataSprzedazy`:** BRAK (nie używane w pro-forma)
- **Pole `Zaplacono` / `ZaplaconoNaDokumencie`:** BRAK
- **Pole `FormatDatySprzedazy`:** BRAK
- **Pozycje:** Dla zwolnionych z VAT:
  - ❌ **BRAK pola `StawkaVat`** (usuwane przez `unset()`)
  - ✅ `TypStawkiVat: "ZW"`
  - ✅ `PodstawaPrawna: "Art. 43..."`
  - ❌ **BRAK pola `StawkaRyczaltu`** (ryczałt nie jest uwzględniany w pro-forma)
  - ❌ **BRAK pola `PKWiU`**

---

## ❌ Co NIE działa: FAKTURA KRAJOWA

### Obecna struktura JSON (nie działa):
```json
{
  "Zaplacono": 0,
  "ZaplaconoNaDokumencie": 0,
  "LiczOd": "NET",
  "DataWystawienia": "2025-11-02",
  "DataSprzedazy": "2025-11-02",
  "FormatDatySprzedazy": "DZN",
  "SposobZaplaty": "PRZ",
  "RodzajPodpisuOdbiorcy": "BWO",
  "NumerZamowienia": "5117",
  "TerminPlatnosci": "2025-11-16",
  "Kontrahent": {
    "Nazwa": "Gmina Bieżuń",
    "Kraj": "PL",
    "Ulica": "ul. Warszawska 5",
    "KodPocztowy": "09-320",
    "Miejscowosc": "Bieżuń",
    "NIP": "5110265245"
  },
  "Pozycje": [
    {
      "StawkaVat": 0,
      "StawkaRyczaltu": 0.085,
      "Ilosc": 1,
      "CenaJednostkowa": 365,
      "NazwaPelna": "SZKOLENIE: ...",
      "Jednostka": "sztuk",
      "PKWiU": "",
      "TypStawkiVat": "ZW"
    }
  ],
  "Uwagi": "ODBIORCA:\n..."
}
```

### Kluczowe różnice FAKTURA KRAJOWA:
- **Endpoint:** `fakturakraj.json`
- **Pole `TypFakturyKrajowej`:** ❌ BRAK (tylko dla pro-forma)
- **Pole `DataSprzedazy`:** ✅ WYMAGANE
- **Pole `FormatDatySprzedazy`:** ✅ WYMAGANE (`DZN` lub `MSC`)
- **Pole `Zaplacono`:** ✅ WYMAGANE (0.0)
- **Pole `ZaplaconoNaDokumencie`:** ✅ WYMAGANE (0.0)
- **Pozycje dla ryczałtowca zwolnionego z VAT:**
  - ✅ `StawkaVat: 0` (testowane - nie działa)
  - ✅ `StawkaRyczaltu: 0.085` (8.5% ryczałtu)
  - ✅ `TypStawkiVat: "ZW"`
  - ✅ `PKWiU: ""` (dodane zgodnie z dokumentacją)
  - ❌ `PodstawaPrawna` (brak w obecnej strukturze)

### Próbowane kombinacje (wszystkie zwracały błąd 200):

1. ❌ `StawkaVat: null` - nie działało
2. ❌ `StawkaVat: 0` - nie działało
3. ❌ `StawkaVat` całkowicie usunięte - nie działało
4. ❌ `StawkaRyczaltu` z wartością - nie działało
5. ❌ `StawkaRyczaltu` całkowicie usunięte (domyślna z konta) - nie działało
6. ❌ Różne kolejności pól (zgodnie z przykładem dokumentacji) - nie działało
7. ❌ `StawkaVat: 0` + `StawkaRyczaltu: 0.085` + `TypStawkiVat: "ZW"` - nie działało
8. ❌ `PKWiU` bez wartości, z wartością - nie działało
9. ❌ `Ilosc: 1.0` vs `Ilosc: 1` (z JSON_PRESERVE_ZERO_FRACTION) - nie działało

---

## 📋 Lista plików z kodem

### 1. Kontroler (logika biznesowa):
**Plik:** `app/Http/Controllers/FormOrdersController.php`

**Funkcje:**
- `createIfirmaProForma()` (linie ~590-893) - ✅ **DZIAŁA**
- `createIfirmaInvoice()` (linie ~898-1190) - ❌ **NIE DZIAŁA**

**Kluczowe różnice w kodzie:**
- PRO-FORMA: `TypFakturyKrajowej: 'SPRZ'`, brak `DataSprzedazy`, `Zaplacono`, `ZaplaconoNaDokumencie`, `FormatDatySprzedazy`
- FAKTURA: brak `TypFakturyKrajowej`, ma `DataSprzedazy`, `Zaplacono`, `ZaplaconoNaDokumencie`, `FormatDatySprzedazy`

### 2. Serwis API (komunikacja z iFirma):
**Plik:** `app/Services/IfirmaApiService.php`

**Funkcje:**
- `createProFormaInvoice()` (linie ~375-409) - ✅ **DZIAŁA**
  - Endpoint: `fakturaproformakraj.json`
- `createInvoice()` (linie ~422-432) - ❌ **NIE DZIAŁA**
  - Endpoint: `fakturakraj.json`
- `post()` (linie ~172-290) - wspólna metoda dla obu, używa HMAC-SHA1
- `generateAuthHeader()` (linie ~134-171) - generowanie nagłówka autoryzacji

### 3. Konfiguracja:
**Plik:** `config/services.php`
- Sekcja `ifirma` (linie ~50-73)
- Konfiguracja kluczy API, URL, timeout
- `vat_exempt: true`
- `vat_exemption_basis: "Art. 43 ust. 1 pkt 29 lit. b)"`
- `is_lump_sum: true`
- `lump_sum_rate: 0.085`

### 4. Widok (interfejs użytkownika):
**Plik:** `resources/views/form-orders/show.blade.php`
- Przyciski "Wystaw PRO-FORMA iFirma" i "Wystaw Fakturę iFirma" (linie ~201-231)
- Funkcje JavaScript: `createIfirmaProForma()`, `createIfirmaInvoice()` (linie ~660-894)

### 5. Routing:
**Plik:** `routes/web.php`
- `Route::post('/{id}/ifirma/proforma', ...)` - ✅ PRO-FORMA
- `Route::post('/{id}/ifirma/invoice', ...)` - ❌ FAKTURA

---

## 🔍 Dokumentacja API iFirma

### Linki do dokumentacji:
- **Ogólna dokumentacja:** https://api.ifirma.pl/
- **Faktura pro forma:** https://api.ifirma.pl/wystawianie-faktury-proforma/
- **Faktura krajowa (towary i usługi):** https://api.ifirma.pl/wystawianie-faktury-sprzedaz%cc%87y-krajowej-towarow-i-uslug/
- **Ryczałt (sekcja w dok. faktury krajowej):** https://api.ifirma.pl/wystawianie-faktury-sprzedaz%cc%87y-krajowej-towarow-i-uslug/

### Kluczowe fragmenty dokumentacji:

**Dla ryczałtowca:**
> "W przypadku wystawiania faktury przez ryczałtowca należy zmodyfikować przesyłane żądanie i w pozycjach faktury należy dodać pole _StawkaRyczaltu_. W przeciwnym wypadku zostanie zastosowana stawka ryczałtu domyślnie ustawiona w konfiguracji konta."

**Przykład z dokumentacji dla ryczałtowca:**
```json
{
  "StawkaVat": 0.23,
  "StawkaRyczaltu": 0.03,
  "Ilosc": 3,
  "CenaJednostkowa": 47.14,
  "NazwaPelna": "Neseser",
  "Jednostka": "sztuk",
  "PKWiU": "",
  "TypStawkiVat": "PRC"
}
```

**UWAGA:** Przykład pokazuje VAT-owca na ryczałcie (`StawkaVat: 0.23`, `TypStawkiVat: "PRC"`), a nie zwolnionego z VAT.

---

## 🤔 Hipotezy problemu

1. **Konflikt: Ryczałt + Zwolnienie z VAT**
   - Możliwe, że API iFirma nie obsługuje jednocześnie `StawkaRyczaltu` + `TypStawkiVat: "ZW"` + `StawkaVat: 0`
   - Może wymagać innego endpointu lub struktury dla "ryczałtowca zwolnionego z VAT"

2. **Brakujące pole wymagane dla ryczałtowca zwolnionego z VAT**
   - Może brakuje pola `PodstawaPrawna` w pozycji (mamy tylko w głównej fakturze?)
   - Może wymagane jest pole `GrupaTowarowa` lub inne specyficzne dla ryczałtu

3. **Niewłaściwy endpoint**
   - Możliwe, że dla ryczałtowca zwolnionego z VAT trzeba użyć endpointu "Faktura krajowa (nievatowiec)"
   - Dokumentacja wspomina taki endpoint, ale nie mamy dostępu do pełnej dokumentacji

4. **Konfiguracja konta w iFirma**
   - Możliwe, że w panelu iFirma brakuje konfiguracji domyślnej stawki ryczałtu
   - Możliwe, że konto nie jest poprawnie oznaczone jako "ryczałt" w systemie iFirma

---

## 📝 Prompt dla zaawansowanego modelu AI

```
Jestem programistą Laravel i mam problem z integracją API iFirma.pl.

KONTEKST:
- Konto w iFirma jest ZWOLNIONE Z VAT (Art. 43 ust. 1 pkt 29 lit. b))
- Konto jest na RYCZAŁCIE (stawka: 8.5% = 0.085)
- Wystawianie FAKTURY PRO-FORMA działa poprawnie ✅
- Wystawianie FAKTURY KRAJOWEJ (nie pro-forma) zwraca błąd ❌

BŁĄD:
```json
{
  "response": {
    "Kod": 200,
    "Informacja": "Niepoprawna zawartość żądania - nie można utworzyć obiektu na podstawie zawartości żądania."
  }
}
```

OBECNA STRUKTURA JSON (nie działa):
```json
{
  "Zaplacono": 0,
  "ZaplaconoNaDokumencie": 0,
  "LiczOd": "NET",
  "DataWystawienia": "2025-11-02",
  "DataSprzedazy": "2025-11-02",
  "FormatDatySprzedazy": "DZN",
  "SposobZaplaty": "PRZ",
  "RodzajPodpisuOdbiorcy": "BWO",
  "NumerZamowienia": "5117",
  "Kontrahent": {
    "Nazwa": "Gmina Bieżuń",
    "Kraj": "PL",
    "Ulica": "ul. Warszawska 5",
    "KodPocztowy": "09-320",
    "Miejscowosc": "Bieżuń",
    "NIP": "5110265245"
  },
  "Pozycje": [
    {
      "StawkaVat": 0,
      "StawkaRyczaltu": 0.085,
      "Ilosc": 1,
      "CenaJednostkowa": 365,
      "NazwaPelna": "SZKOLENIE: ...",
      "Jednostka": "sztuk",
      "PKWiU": "",
      "TypStawkiVat": "ZW"
    }
  ],
  "TerminPlatnosci": "2025-11-16",
  "Uwagi": "..."
}
```

DZIAŁAJĄCA PRO-FORMA (dla porównania):
```json
{
  "LiczOd": "NET",
  "TypFakturyKrajowej": "SPRZ",
  "DataWystawienia": "2025-11-02",
  "SposobZaplaty": "PRZ",
  "RodzajPodpisuOdbiorcy": "BWO",
  "NumerZamowienia": "5117",
  "Kontrahent": { /* identyczne */ },
  "Pozycje": [
    {
      "NazwaPelna": "...",
      "Ilosc": 1.0,
      "CenaJednostkowa": 365.0,
      "Jednostka": "sztuk",
      "TypStawkiVat": "ZW",
      "PodstawaPrawna": "Art. 43 ust. 1 pkt 29 lit. b)"
      // BRAK: StawkaVat, StawkaRyczaltu, PKWiU
    }
  ],
  "TerminPlatnosci": "2025-11-16"
}
```

PRÓBOWANE ROZWIĄZANIA (wszystkie zwracały błąd 200):
1. StawkaVat: null / 0 / całkowicie usunięte
2. StawkaRyczaltu: z wartością / całkowicie usunięte
3. Różne kombinacje pól TypStawkiVat + PodstawaPrawna
4. Różne kolejności pól
5. PKWiU: pusty string / z wartością / brak
6. Ilosc: jako 1 vs 1.0

DOKUMENTACJA:
- https://api.ifirma.pl/
- https://api.ifirma.pl/wystawianie-faktury-sprzedaz%cc%87y-krajowej-towarow-i-uslug/
- https://api.ifirma.pl/wystawianie-faktury-proforma/

ZADANIE:
1. Przeanalizuj dogłębnie dokumentację API iFirma, szczególnie:
   - Sekcję o ryczałtowcach
   - Sekcję "Faktura krajowa (nievatowiec)" - jeśli istnieje
   - Wymagane pola dla faktury krajowej
   - Różnice między fakturą pro-forma a fakturą krajową

2. Przeszukaj fora dla programistów (Stack Overflow, GitHub issues, polskie fora programistyczne) pod kątem:
   - Problemów z wystawianiem faktur przez API iFirma
   - Problemów z ryczałtem + zwolnieniem z VAT
   - Błędu "Kod 200: Niepoprawna zawartość żądania"
   - Przykładowych struktur JSON dla ryczałtowców zwolnionych z VAT

3. Znajdź rozwiązanie:
   - Jaka powinna być prawidłowa struktura JSON?
   - Czy może być potrzebny inny endpoint?
   - Czy brakuje jakichś pól wymaganych dla ryczałtowca zwolnionego z VAT?
   - Czy może być problem z konfiguracją konta w panelu iFirma?

4. Podaj szczegółowe rozwiązanie z przykładowym JSON-em który zadziała.

WAŻNE:
- PRO-FORMA działa, więc autoryzacja, dane kontrahenta, podstawowa struktura są poprawne
- Problem jest specyficzny dla faktury krajowej dla ryczałtowca zwolnionego z VAT
```

---

## 📁 Struktura kodu

### Kluczowe pliki:

1. **`app/Http/Controllers/FormOrdersController.php`**
   - Metoda `createIfirmaProForma()` (linie ~590-893)
   - Metoda `createIfirmaInvoice()` (linie ~898-1190)

2. **`app/Services/IfirmaApiService.php`**
   - Metoda `createProFormaInvoice()` (linie ~375-409)
   - Metoda `createInvoice()` (linie ~422-432)
   - Metoda `post()` (linie ~172-290) - wspólna dla wszystkich żądań
   - Metoda `generateAuthHeader()` (linie ~134-171) - HMAC-SHA1

3. **`config/services.php`**
   - Konfiguracja iFirma (linie ~50-73)

4. **`resources/views/form-orders/show.blade.php`**
   - Przyciski i JavaScript dla obu funkcjonalności

5. **`routes/web.php`**
   - Routing dla endpointów iFirma

---

## 🔧 Zmienne środowiskowe (.env)

```
IFIRMA_LOGIN=HOSTNET
IFIRMA_KEY_FAKTURA=...
IFIRMA_VAT_EXEMPT=true
IFIRMA_VAT_EXEMPTION_BASIS="Art. 43 ust. 1 pkt 29 lit. b)"
IFIRMA_IS_LUMP_SUM=true
IFIRMA_LUMP_SUM_RATE=0.085
IFIRMA_SENDER_EMAIL=waldemar.grabowski@hostnet.pl
IFIRMA_BANK_ACCOUNT=...
```

---

**Data analizy:** 2025-11-02  
**Status:** PROBLEM NIE ROZWIĄZANY - wymaga analizy przez zaawansowany model AI

