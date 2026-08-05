# Uczestnicy zamówień formularza (`form_orders`)

## Źródło danych

- Imię, nazwisko i e-mail uczestnika są w tabeli **`form_order_participants`** (wiersz z **`is_primary = 1`**).
- Kolumn **`participant_name`** i **`participant_email`** w **`form_orders` nie ma** (usunięte migracją `2026_03_23_000001_drop_participant_name_email_from_form_orders_table`).

## Kod (adm / pnedu)

- **`FormOrder::display_participant_name`** / **`display_participant_email`** — czytają wyłącznie głównego uczestnika z `form_order_participants`.
- Zapis uczestnika: **`FormOrdersController`**, **`FormOrderParticipant`**, formularz **`pnedu`** (`CourseController` + `FormOrderParticipant::syncFromFormOrder`).

## Migracja bazy

```bash
sail artisan migrate
```

Rollback kolumn (np. na stagingu): `sail artisan migrate:rollback --step=1` (przywraca `participant_name` / `participant_email`).

## Provision PNEDU (Dodaj tylko do PNEDU)

Pełny opis flow (participants → ClickMeeting → e-mail, tokeny, linki live): **[FORM_ORDERS_PNEDU_PROVISION.md](./FORM_ORDERS_PNEDU_PROVISION.md)**.

## Status operacyjny na karcie zamówienia

Po wystawieniu FV przez iFirma (AJAX) panel **Status operacyjny** na `/form-orders/{id}` odświeża się bez przeładowania strony (`GET form-orders/{id}/operational-status`). Dzięki temu znika m.in. ostrzeżenie „Uczestnik dodany…, ale faktura nie została wystawiona.” zaraz po pojawieniu się numeru FV w polu.

## Wyszukiwarka FV / KSeF na liście

Osobny formularz na `/form-orders` (`invoice_search`) szuka w `invoice_number` i `ksef_number` wśród **wszystkich** zamówień — bez kolejki „Do obsługi” i bez filtra „Przetwarzanie” (nawet gdy w URL jest `filter=handling` / `quick=handling`).

## Stare skrypty zewnętrzne

Jeśli masz PHP/SQL poza tymi repozytoriami, które jeszcze odwołują się do `form_orders.participant_*`, zaktualizuj je do **`form_order_participants`**.
