# Deploy — `ifirma_invoice_id` na `form_orders`

## Co

Kolumna `form_orders.ifirma_invoice_id` (wewnętrzny Identyfikator dokumentu w iFirma), zapis przy wystawianiu FV krajowej / z odbiorcą / +KSeF. UI: „ID iFirma” na szczegółach zamówienia.

## Migracja (baza `pneadm`)

```bash
sail artisan migrate --force
```

Migracja: `2026_07_31_160000_add_ifirma_invoice_id_to_form_orders_table.php`

## Uwagi

- Pro-forma nie ustawia pola.
- Stare zamówienia: pole puste do kolejnego zapisu z iFirma (albo przyszłego backfillu).
- Dokumentacja: `docs/KSEF_FORM_ORDERS.md`, `docs/WINDYKACJA.md`.
