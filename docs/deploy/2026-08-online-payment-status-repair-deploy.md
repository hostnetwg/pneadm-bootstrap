# Deploy: naprawa statusów płatności online formularzy

Data: 2026-08-03  
Zakres: `pnedu` webhook/return sync + `pneadm` korekta danych w bazie `pneadm`

## Co wdraża

Poprawka zamyka wyścig webhooków PayU/PayNow dla zamówień z formularza.
Jeśli jedno `form_orders` ma wiele powiązanych prób w `online_payment_orders`, status `paid` dowolnej próby wygrywa dla `form_orders.payment_status`.
Spóźnione `cancelled` / `failed` ze starszej próby nadal aktualizuje własny rekord `online_payment_orders`, ale nie degraduje opłaconego `form_orders`.

## Ryzyko, które naprawiamy

Przykład produkcyjny: `form_orders.id = 7969`, `PNEDU_479` dostało `COMPLETED -> paid`, a później starsze `PNEDU_478` dostało `CANCELED -> cancelled`.
Stary kod nadpisał `form_orders.payment_status` z `paid` na `cancelled`, mimo że faktura iFirma została wystawiona jako opłacona.

## Deploy kodu

1. `pnedu`: `git pull`
2. `pneadm`: `git pull`
3. `pnedu`: `sail artisan optimize:clear`
4. `pneadm`: `sail artisan optimize:clear`
5. Brak migracji.

## Weryfikacja przed korektą danych

Na `pneadm` uruchomić:

```bash
sail artisan form-orders:repair-online-payment-statuses
```

Komenda domyślnie działa jako dry-run i nie zapisuje zmian.
Lista powinna zawierać zamówienia online, które mają `form_orders.payment_status != paid`, ale mają co najmniej jedną powiązaną próbę `online_payment_orders.status = paid`.

## Korekta danych

Po akceptacji listy z dry-run:

```bash
sail artisan form-orders:repair-online-payment-statuses --apply
```

Komenda zmienia wyłącznie:

- `form_orders.payment_status` na `paid`,
- `form_orders.updated_at`.

Nie zmienia:

- `online_payment_orders`,
- faktur iFirma / KSeF,
- uczestników,
- `pnedu_provisioned_at`,
- statusów operacyjnych `cancelled_at`.

Log korekty trafia do `storage/logs/form-orders-online-payment-status-repair-*.txt`.

## Smoke test

1. Otworzyć `adm.pnedu.pl/form-orders/7969`.
2. Sprawdzić, że status płatności pokazuje `Opłacone`.
3. Otworzyć `adm.pnedu.pl/online-payment-orders` i potwierdzić, że historyczne próby zachowały własne statusy (`PNEDU_478: cancelled`, `PNEDU_479: paid`).
4. Przy nowej próbie testowej: sukces nowszej płatności nie może zostać nadpisany przez późny webhook anulowania starszej próby.

## Rollback

Rollback kodu: wycofanie zmian w `pnedu` i `pneadm`.

Rollback korekty danych jest ręczny na podstawie logu, ale produkcyjnie nie powinno się przywracać `cancelled` dla zamówień, które mają opłaconą próbę online i wystawioną fakturę jako opłaconą.
