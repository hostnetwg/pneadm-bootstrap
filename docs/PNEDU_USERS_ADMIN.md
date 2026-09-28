# Użytkownicy pnedu.pl w panelu ADM

Data: 2026-09-28  
Status: obowiązujące  
Lista: `/admin/pnedu-users` · karta: `/admin/pnedu-users/{id}`

## Cel

Podgląd i wsparcie kont z bazy `pnedu.users` (nie kont operatorów ADM). Uprawnienia: `users.view` (podgląd), `users.edit` (zmiany).

## Edycja danych konta

Na karcie użytkownika (uprawnienie `users.edit`) można zmienić **imię**, **nazwisko**, **e-mail**, **datę urodzenia** i **miejsce urodzenia**.

- Zapis idzie do `pnedu.users`.
- Te same imię, nazwisko, data/miejsce urodzenia i e-mail są przepisywane na rekordy `participants` z dotychczasowym adresem (także soft-deleted). Gdy na tym samym szkoleniu jest już inny uczestnik z nowym e-mailem, ten rekord jest pomijany.
- E-mail jest loginem na pnedu.pl i musi być unikalny wśród aktywnych kont (`email_unique_slot`).
- Zmiana e-maila (jak w profilu na pnedu.pl): `email_verified_at` wraca do pustego, flaga bounce znika. Potwierdzenie przez modal Bootstrap.
- **Nie** przepisujemy e-maila na zamówieniach FORM.

## Inne akcje na karcie

Hasło, reset, weryfikacja e-mail, bounce, soft delete — bez zmian (log aktywności).
