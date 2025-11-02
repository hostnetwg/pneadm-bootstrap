# Prompt dla zaawansowanego modelu AI - Problem z API iFirma

## Kontekst problemu

Mam problem z wystawianiem faktury krajowej przez API iFirma.pl w systemie Laravel. PRO-FORMA działa poprawnie, ale faktura krajowa zwraca błąd walidacji.

## Szczegóły techniczne

**System:** Laravel 11.31+, PHP 8.2+
**API:** iFirma.pl (HMAC-SHA1 autoryzacja)
**Endpoint działa:** `POST /iapi/fakturaproformakraj.json` ✅
**Endpoint nie działa:** `POST /iapi/fakturakraj.json` ❌

## Konfiguracja konta

- **Login:** HOSTNET
- **Zwolnienie z VAT:** TAK (Art. 43 ust. 1 pkt 29 lit. b))
- **Ryczałt:** TAK (stawka: 8.5% = 0.085)

## Struktura JSON - PRO-FORMA (DZIAŁA ✅)

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
      "NazwaPelna": "SZKOLENIE: Prowadzenie i organizacja sekretariatu...",
      "Ilosc": 1.0,
      "CenaJednostkowa": 365.0,
      "Jednostka": "sztuk",
      "TypStawkiVat": "ZW",
      "PodstawaPrawna": "Art. 43 ust. 1 pkt 29 lit. b)"
    }
  ],
  "Uwagi": "ODBIORCA:\nSzkoła Podstawowa..."
}
```

**Endpoint:** `fakturaproformakraj.json`  
**Status:** ✅ DZIAŁA

## Struktura JSON - FAKTURA KRAJOWA (NIE DZIAŁA ❌)

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
      "NazwaPelna": "SZKOLENIE: Prowadzenie i organizacja sekretariatu...",
      "Jednostka": "sztuk",
      "PKWiU": "",
      "TypStawkiVat": "ZW"
    }
  ],
  "Uwagi": "ODBIORCA:\nSzkoła Podstawowa..."
}
```

**Endpoint:** `fakturakraj.json`  
**Status:** ❌ BŁĄD

## Błąd API

```json
{
  "response": {
    "Kod": 200,
    "Informacja": "Niepoprawna zawartość żądania - nie można utworzyć obiektu na podstawie zawartości żądania."
  }
}
```

## Próbowane rozwiązania (wszystkie zwróciły błąd 200):

1. ❌ `StawkaVat: null` - nie działało
2. ❌ `StawkaVat: 0` - nie działało
3. ❌ `StawkaVat` całkowicie usunięte - nie działało
4. ❌ `StawkaRyczaltu: 0.085` - nie działało
5. ❌ `StawkaRyczaltu` całkowicie usunięte - nie działało
6. ❌ Różne kombinacje `TypStawkiVat: "ZW"` + `PodstawaPrawna` - nie działało
7. ❌ Różne kolejności pól (zgodnie z przykładem dokumentacji) - nie działało
8. ❌ `PKWiU` z wartością / bez wartości - nie działało
9. ❌ `Ilosc: 1` vs `Ilosc: 1.0` - nie działało

## Dokumentacja API iFirma

**Główna dokumentacja:** https://api.ifirma.pl/
**Faktura krajowa:** https://api.ifirma.pl/wystawianie-faktury-sprzedaz%cc%87y-krajowej-towarow-i-uslug/
**Faktura pro forma:** https://api.ifirma.pl/wystawianie-faktury-proforma/

**Ważna sekcja z dokumentacji - Ryczałt:**
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

**UWAGA:** Przykład pokazuje VAT-owca na ryczałcie, a nie zwolnionego z VAT!

## Zadania dla AI:

1. **Przeanalizuj dokumentację API iFirma:**
   - Dokładnie przeczytaj sekcję o fakturze krajowej
   - Sprawdź sekcję "Faktura krajowa (nievatowiec)" - jeśli istnieje
   - Znajdź wszystkie wymagane pola dla faktury krajowej
   - Sprawdź czy są specjalne wymagania dla ryczałtowców zwolnionych z VAT

2. **Przeszukaj fora i repozytoria:**
   - Stack Overflow (tag: ifirma, ifirma-api)
   - GitHub Issues (repozytoria związane z iFirma API)
   - Polskie fora programistyczne (4programmers.net, etc.)
   - Szukaj: "Kod 200 Niepoprawna zawartość żądania iFirma"
   - Szukaj: "ryczałt zwolniony VAT iFirma API"
   - Szukaj: "faktura krajowa ryczałt API ifirma"

3. **Znajdź rozwiązanie:**
   - Jaka powinna być prawidłowa struktura JSON dla ryczałtowca zwolnionego z VAT?
   - Czy może być potrzebny inny endpoint (np. dla nievatowców)?
   - Czy brakuje jakichś pól wymaganych dla tego typu konta?
   - Czy problem może być w konfiguracji konta w panelu iFirma?

4. **Podaj gotowe rozwiązanie:**
   - Przykładowy JSON który zadziała
   - Wyjaśnienie różnic vs obecną strukturę
   - Ewentualne zmiany w kodzie Laravel

## Pliki kodu do analizy:

1. `app/Http/Controllers/FormOrdersController.php`
   - `createIfirmaProForma()` - działa ✅
   - `createIfirmaInvoice()` - nie działa ❌

2. `app/Services/IfirmaApiService.php`
   - `createProFormaInvoice()` - działa ✅
   - `createInvoice()` - nie działa ❌

3. `config/services.php` - konfiguracja iFirma

## Kluczowe pytania:

1. Czy dla ryczałtowca zwolnionego z VAT można używać `StawkaRyczaltu` razem z `TypStawkiVat: "ZW"`?
2. Czy może trzeba użyć innego endpointu niż `fakturakraj.json`?
3. Czy może brakuje pola `PodstawaPrawna` w pozycji faktury (nie tylko w głównej strukturze)?
4. Czy może trzeba usunąć pole `StawkaVat` całkowicie (tak jak w pro-forma)?
5. Czy może problem jest w konfiguracji konta iFirma (np. brak domyślnej stawki ryczałtu)?

---

**WAŻNE:** PRO-FORMA działa, więc autoryzacja HMAC-SHA1, dane kontrahenta i podstawowa struktura są poprawne. Problem jest specyficzny dla faktury krajowej dla konta będącego jednocześnie ryczałtoweM i zwolnionym z VAT.

