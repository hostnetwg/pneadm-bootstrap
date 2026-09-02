# Zamknięcie zapisów i przekierowanie na kolejną edycję

## Cel

Gdy konkretna edycja szkolenia ma już komplet miejsc, stare linki z e-maili, Facebooka i kampanii nadal mogą prowadzić na jej stronę. Funkcja pozwala ręcznie zamknąć zapisy na tę edycję, zostawić stronę opisową dostępną i skierować formularze zapisu na kolejną edycję.

## Model działania

- Decyzja o zamknięciu zapisów jest ręczna, ustawiana w panelu `pneadm` na edycji szkolenia.
- Stara strona `/courses/{id}` w `pnedu` pozostaje dostępna i pokazuje komunikat o braku miejsc.
- Przyciski zapisu na starej stronie kierują na wskazaną następną edycję.
- Bezpośrednie wejścia na stare formularze (`/order-form`, `/deferred-order`, `/pay-online`) są przekierowywane na **stronę opisu** następnej edycji z komunikatem (nie od razu na formularz zapisu).
- POST nowych zapisów na zamkniętą edycję jest blokowany i kierowany na stronę opisu następnej edycji.
- Edycja istniejącego zamówienia pozostaje dozwolona, żeby klient mógł poprawić dane z wcześniej wysłanego linku.

## Pola w `courses`

Migracja znajduje się w projekcie `pneadm`, bo tabela `courses` należy do bazy `pneadm`.

- `registration_closed_at` — gdy niepuste, publiczne zapisy na edycję są zamknięte.
- `registration_successor_course_id` — kolejna edycja, na którą kierujemy formularze.
- `registration_closed_message` — opcjonalny komunikat widoczny na starej stronie.

## Gdzie ustawiać

Panel `pneadm` → edycja szkolenia → sekcja **Zapisy publiczne na pnedu.pl**:

1. Zaznacz **Zamknij zapisy na tę edycję**.
2. Wybierz **Następna edycja szkolenia**.
3. Opcjonalnie wpisz własny komunikat.
4. Zapisz zmiany.

## Czego MVP nie robi

- Nie zamyka zapisów automatycznie po limicie miejsc.
- Nie przepisuje masowo kampanii marketingowych na nowy kurs.
- Nie robi twardego redirectu całej starej strony kursu.

Automatyczne liczenie limitu można dodać później. Ustalona definicja zajętego miejsca na przyszłość: uczestnicy już dodani + aktywne zamówienia, bez starych porzuconych płatności online.
