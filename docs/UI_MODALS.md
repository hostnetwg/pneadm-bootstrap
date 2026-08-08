# Potwierdzenia i komunikaty w UI (Bootstrap Modal)

Obowiązuje w panelu admina (`pneadm`) i froncie (`pnedu`), o ile nie ma wyjątku technicznego.

## Zasada

**Nigdy nie używaj natywnych okien przeglądarki** do interakcji z użytkownikiem w panelu:

- ❌ `confirm()`
- ❌ `alert()` (poza awaryjnym debugiem w konsoli)
- ❌ `prompt()`

Zamiast tego **zawsze** stosuj **modal Bootstrap 5** (spójny z resztą panelu).

## Kiedy modal

- potwierdzenie destrukcyjnej lub odwracalnej akcji (anulowanie, usunięcie, cofnięcie statusu),
- krótkie wyjaśnienie skutków przed wykonaniem akcji AJAX,
- opcjonalne pole (np. powód) — w treści modala, nie w `prompt()`.

## Wzorzec (Blade + JS)

1. Przycisk otwiera modal: `data-bs-toggle="modal" data-bs-target="#nazwaModala"`.
2. Modal: nagłówek (kolor semantyczny), treść, ostrzeżenie `alert alert-warning` jeśli trzeba, stopka **Wróć** + **Potwierdź**.
3. Potwierdzenie: osobny przycisk `#confirm…Btn` z `fetch` + CSRF; po sukcesie `location.reload()` lub aktualizacja fragmentu UI.
4. Błędy API: można tymczasowo `alert()` — docelowo też modal/toast; **nie** `confirm()`.

## Przykłady w kodzie

- `resources/views/form-orders/show.blade.php` — anulowanie, przywracanie, „bez FV”, cofnięcie „bez FV”, usunięcie.
- `docs/deploy/2026-06-analytics-production-deploy.md` — przeliczanie agregatów (modal z zakresem dat).

## Spinner na przyciskach (długie operacje)

Przy POST/AJAX, które mogą trwać dłużej (iFirma, import CSV, akceptacja przelewu), używaj `data-loading-submit` na formularzu oraz opcjonalnie `data-loading-text` na przycisku.

Helper JS: `resources/js/button-loading.js` → `window.PneButtonLoading.setButtonLoading(btn, true|false, 'Tekst…')`.

Przykłady: windykacja (`Odśwież status z iFirma`), import wyciągu (`Importuj`, `Przelicz sugestie`, akceptacje, sprawdzenie statusu iFirma).

## Dla AI (Cursor)

Przy każdej nowej akcji wymagającej potwierdzenia **od razu** dodawaj modal — użytkownik nie powinien musieć o tym przypominać.

Skrót zapisany też w `.cursorrules` (sekcja Bootstrap) obu projektów.
