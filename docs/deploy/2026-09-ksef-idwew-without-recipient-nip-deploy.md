# Deploy: JST/VAT + IDWew bez NIP odbiorcy

Data: 2026-09-28  
Projekt: tylko `pneadm` (adm.pnedu.pl).  
Kanon: [KSEF_FORM_ORDERS.md](../KSEF_FORM_ORDERS.md). Ścieżki: [PRODUCTION_PATHS.md](./PRODUCTION_PATHS.md).

## Cel

Przy roli JST / grupa VAT i typie IDWew payload iFirma ma wyłącznie `IdentyfikatorWewnetrznyZNip`. Szkoła bez własnego NIP (np. #10177) da się zafakturować.

## Migracja

Brak.

## Po `git push` — panel

```bash
ssh srv66127@h30.seohost.pl
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
```

Front pnedu.pl — bez zmian.

## Smoke

1. [Zamówienie #10177](https://adm.pnedu.pl/form-orders/10177) — JST + IDWew `8451951677-12000`, pusty NIP odbiorcy.
2. Czerwony przycisk: **nie** pojawia się komunikat o braku NIP Podmiotu3.
3. Po wystawieniu: w iFirma Podmiot3 ma IDWew, bez NIP szkoły.
