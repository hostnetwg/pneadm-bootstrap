# Deploy: rejestracja wpłaty iFirma z importu wyciągu

Data: 2026-08-01  
Zakres: `pneadm` (lokalnie / prod adm)

## Co wdraża

Po akceptacji dopasowania z importu mBank (gdy **kwota przelewu = kwota FV/zamówienia**) operator może wybrać:

- **Akceptuj + wpłata w iFirma** — lokalny `bank_match` + `POST /iapi/faktury/wplaty/prz_faktura_kraj/{id|numer}.json` + odświeżenie `ifirma_payment_status`,
- **Tylko lokalnie** — jak dotychczas, bez zapisu w iFirma.

W modalu podglądu można też kliknąć **Sprawdź status z iFirma**. Jeśli faktura jest już opłacona, operator może wybrać **Zaakceptuj jako opłacone w iFirma** — to zapisuje lokalny `bank_match`, ale nie dodaje kolejnej wpłaty w iFirma.

Przy `amount_mismatch` — tylko akceptacja lokalna (bez opcji iFirma).

## Wymagania

- Klucz API iFirma `IFIRMA_KEY_FAKTURA` (ten sam co wystawianie/odczyt faktur).
- Faktura musi dać się znaleźć po `form_orders.ifirma_invoice_id` albo numerze FV.

## Deploy

1. `git pull` na adm.
2. Brak nowych migracji.
3. `php artisan optimize:clear` (lub `sail artisan optimize:clear` lokalnie).
4. Smoke:
   - Import / istniejący wyciąg → sugestia High (zgodna kwota) → Akceptuj → modal → **Akceptuj + wpłata w iFirma**.
   - Sprawdź w iFirma, że wpłata się pojawiła; na sprawie status „Opłacona” / sync w historii.
   - Podgląd sugestii → **Sprawdź status z iFirma** dla faktury już opłaconej → **Zaakceptuj jako opłacone w iFirma**; brak nowej wpłaty w iFirma, jest lokalny `bank_match`.
   - Przy różnicy kwot: modal ostrzeżenia, brak rejestracji w iFirma.

## Rollback

Wycofanie kodu. Wpłaty już zapisane w iFirma zostają (księgowość) — ewentualne cofnięcie tylko ręcznie w iFirma.
