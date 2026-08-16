# Uczestnicy zamówień formularza (`form_orders`)

## Źródło danych

- Imię, nazwisko i e-mail uczestników są w tabeli **`form_order_participants`**.
- Pierwszy wiersz ma **`is_primary = 1`** (główny — używany m.in. przy wznowieniu checkoutu / kompatybilności).
- Kolumn **`participant_name`** i **`participant_email`** w **`form_orders` nie ma**.
- **`form_orders.product_price`** = kwota **całkowita** (cena jednostkowa szkolenia × liczba uczestników). Bez rabatu grupowego w v1 (miejsce w `OrderFormParticipantService::totalPrice()`).

## Publiczny formularz (pnedu)

- Legacy + V2: przycisk **Dodaj kolejnego uczestnika** (tylko szkoła/firma; osoba prywatna = max 1).
- Limit: `config/order_form.php` → `max_participants` (domyślnie **50**, env `ORDER_FORM_MAX_PARTICIPANTS`).
- Unikalność e-maila na szkoleniu i w ramach zamówienia — walidacja + ostrzeżenie live (`GET /courses/{id}/participant-email-availability`).
- Zapis: `OrderFormParticipantService::sync()` → `FormOrderParticipant::syncManyFromFormOrder()`.

### Edycja po złożeniu

| Tryb płatności | Edycja listy / ilości |
|----------------|------------------------|
| **Online (bramka)** | **Zablokowana** od razu po utworzeniu zamówienia (także awaiting / cancelled) |
| **Odroczona FV** | Do wystawienia faktury (`invoice_number`) / `status_completed` |

## Panel ADM (`/form-orders/{id}`)

- Lista kart uczestników z kopiowaniem danych.
- **Dodaj uczestnika do PNEDU** przy każdej osobie + **Dodaj wszystkich naraz**.
- Po provisionie: **bez przeładowania strony** — soft-refresh kart; status **zwijany** (`PNEDU OK · 3/3`). Po pełnym sukcesie ~2 s rozwinięty, potem zwija; przy problemie w kroku (np. CM) **zostaje rozwinięty** (ręczne zwinięcie możliwe). Krok 2 nie-OK także przy braku tokenu / nieudanym zapisie CM.
- Checkbox **Dodaj uczestnika do listy e-mailowej (Sendy)** — **per osoba** (widoczny tylko gdy e-mail ≠ zamawiający); przy „wszystkich naraz” respektowane są zaznaczenia z każdej karty.
- **Prześlij dostęp ponownie** — **per osoba** (na karcie provisionowanego uczestnika); podgląd/wysyłka z `form_order_participant_id`.
- Provision: `FormOrderPneduProvisionService::provision(..., $formOrderParticipantId)` / `provisionAll(..., $addToSendyByFopId)`.
- `pnedu_provisioned_at` ustawiane dopiero gdy **wszyscy** mają dostęp.
- **Wycofaj dostęp PNEDU** — **per osoba** (karta) + **Wycofaj dostęp PNEDU wszystkim** gdy ≥2 provisionowanych.

## Faktura iFirma

- Jedna pozycja: `Ilosc` = liczba uczestników, `CenaJednostkowa` = `invoiceUnitPrice()` (total / N).
- Uwagi FV: `UCZESTNIK:` / `UCZESTNICY:` z listy.

## Kod

| Obszar | Pliki |
|--------|--------|
| pnedu sync / cena | `OrderFormParticipantService`, `FormOrderParticipant`, `CourseController` |
| UI formularza | `order-form-participants*.blade.php`, `order-form.blade.php`, `order-form-v2.blade.php` |
| ADM | `form-orders/partials/participants-cards.blade.php`, `FormOrderPneduProvisionService` |
| Helpers FV | `FormOrder::invoiceLineQuantity()`, `invoiceUnitPrice()` |

## Provision PNEDU

Pełny opis: **[FORM_ORDERS_PNEDU_PROVISION.md](./FORM_ORDERS_PNEDU_PROVISION.md)**.
