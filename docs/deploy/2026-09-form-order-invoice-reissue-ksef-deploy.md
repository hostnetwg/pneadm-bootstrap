# Deploy: ponowne wystawienie faktury po korekcie anulującej

Data: 2026-09-30  
Projekt: `pneadm`  
Kanon: [KSEF_FORM_ORDERS.md](../KSEF_FORM_ORDERS.md)

## Zakres

- wyczyszczenie `invoice_number` resetuje ID iFirma oraz metadane KSeF,
- nowa faktura nie dziedziczy numeru/statusu KSeF poprzedniej,
- joby sprawdzają oczekiwany `ifirma_invoice_id`.

Brak migracji i zmian w `pnedu`.

## Deploy

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan queue:restart
```

## Smoke

1. Na zamówieniu testowym z fakturą zapisz numer korekty w notatkach.
2. Wyczyść „Numer faktury” i zapisz.
3. Potwierdź brak numeru FV, ID iFirma i numeru KSeF.
4. Użyj czerwonego przycisku.
5. Potwierdź nowe `PelnyNumer`, nowe ID iFirma, status oczekiwania na KSeF,
   a następnie nowy NumerKSeF.

