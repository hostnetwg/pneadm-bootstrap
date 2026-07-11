# Formularze zamówienia pnedu.pl — panel adm

Krótki przewodnik po ustawieniach w **pneadm**. Pełna dokumentacja techniczna (brama URL, V2, analityka, pliki): **[pnedu/docs/ORDER_FORM_V2.md](../../pnedu/docs/ORDER_FORM_V2.md)**.

---

## Gdzie konfigurować

**Menu:** Ustawienia → **Zakupy pnedu.pl**  
**URL:** `/settings/pnedu-zakupy`  
**Tabela:** `payment_display_options` (baza `pneadm`, odczyt przez `pnedu`)

---

## Checkboxy wariantów

| Ustawienie | Efekt |
|------------|--------|
| **Zamawiam szkolenie** (`show_order_form`) | Włącza wariant **legacy** (CTA + dostępność w bramie) |
| **Zamawiam szkolenie v2** (`show_order_form_v2`) | Włącza wariant **V2**; bezpośredni `/order-form-v2` → **404** gdy wyłączone |

Można włączyć **oba** — na stronie kursu widać **jeden** przycisk „Zamawiam szkolenie” (zgodnie z radio domyślnej wersji).

**Zabezpieczenie przy zapisie:** nie można wyłączyć obu checkboxów naraz; radio „Domyślna wersja” musi wskazywać wariant z włączonym checkboxem.

**Linki kampanii** (`/l/…`, UTM, `/order-form?form_variant=…`) zwykle **nadal działają** — przy wyłączonym wymuszonym wariancie brama robi fallback na dostępny wariant (np. `form_variant=v2` + wyłączone V2 → legacy).

---

## Domyślna wersja formularza

Radio **`default_signup_order_form_variant`**: `legacy` | `v2`.

Dotyczy wejść **bez** `?form_variant=…`:

- przycisk **„Zamawiam szkolenie”** na stronie kursu,
- link **„Zapisz się”** (lista / strona główna),
- kampanie z wersją **global**,
- archiwalne linki FB/newsletter bez parametru w URL.

---

## Kampanie marketingowe

**Nowa kampania** (landing: formularz) — domyślnie **Domyślna globalna** (pierwsza opcja w formularzu):

| Wersja w kampanii | URL | Kiedy używać |
|-------------------|-----|--------------|
| **global** | `/courses/{id}/order-form?utm_…` (bez `form_variant`) | Evergreen, FB, centralne sterowanie z Zakupy pnedu.pl |
| **legacy** | `…/order-form?form_variant=legacy&…` | Jednorazowa wysyłka — formularz zamrożony |
| **v2** | `…/order-form?form_variant=v2&…` | j.w. dla kreatora V2 |

Skrót `/l/{kod}` przekierowuje zgodnie z zapisaną wersją kampanii (global → bez parametru).

Przy **edycji** kampanii — zapisana wersja z bazy.

---

## Tryb testowy formularza

Sekcja **Automatyczne wypełnianie formularza (testy)** — obie opcje pokazują **tylko przycisk** „Wypełnij dane testowe” na legacy i V2. **Pola nie wypełniają się automatycznie** przy wejściu na stronę.

| Opcja w adm | Kto widzi przycisk |
|-------------|-------------------|
| **developers_only** | Zalogowani: waldemar.grabowski@hostnet.pl, luman0599@gmail.com |
| **unrestricted** (czerwony) | Wszyscy odwiedzający, także niezalogowani |

Unrestricted na produkcji: auto-wyłączenie po TTL (domyślnie **1 min**) — także odznaczenie w tym panelu.

Dodatkowo: `?test=1` w URL formularza wymusza tryb testowy (przycisk, bez auto-wypełnienia).

Dane po kliknięciu: `OrderFormTestData` w `pnedu` (`PaymentDisplayOption::isOrderFormTestModeEnabled`).

---

## Edycja zamówienia

Linki **`/courses/{id}/order-form/edit/{ident}`** zawsze otwierają **legacy**. V2 nie obsługuje edycji.

---

## Analityka

Eventy lejka zapisują `metadata.form_variant` (`legacy` | `v2`) — m.in. wejście w formularz, submit, wybór płatności. Szczegóły: [`docs/analytics/EVENT_TAXONOMY.md`](./analytics/EVENT_TAXONOMY.md).
