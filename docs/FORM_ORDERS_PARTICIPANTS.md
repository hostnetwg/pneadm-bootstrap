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

## Stare skrypty zewnętrzne

Jeśli masz PHP/SQL poza tymi repozytoriami, które jeszcze odwołują się do `form_orders.participant_*`, zaktualizuj je do **`form_order_participants`**.
