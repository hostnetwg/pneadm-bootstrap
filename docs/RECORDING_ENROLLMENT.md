# Dopisanie do nagrania po szkoleniu

Data: 2026-09-23  
Projekty: `pneadm` + `pnedu`  
Deploy: [2026-09-recording-enrollment-deploy.md](./deploy/2026-09-recording-enrollment-deploy.md)

## Po co

Szkolenie zamknięte często idzie ogólnym linkiem ClickMeeting. Na liście `participants` lądują tylko osoby, które w trakcie spotkania wypełniły formularz obecności albo zostały dodane ręcznie. Kto nie był albo nie zdążył się wpisać, nie widzi nagrania na koncie pnedu.pl.

Przełącznik **Dopisanie do nagrania** na edycji każdego szkolenia daje jeden link. Dyrektor przekazuje go nauczycielom. Formularz dopisuje uczestnika i — gdy adres nie ma jeszcze konta — od razu zakłada konto na pnedu.pl z hasłem ustawionym w tym samym formularzu.

## Jak włączyć

Edycja szkolenia → karta **Dopisanie do nagrania** (`#recording-enrollment`):

- checkbox **Włącz dopisywanie do nagrania**,
- opcjonalnie data **Formularz dostępny do**.

Po pierwszym włączeniu powstaje token. Link jest na karcie szkolenia i na edycji:

`{PNEDU}/dostep-do-szkolenia/{token}`

Formularz sam się zamyka, gdy minie data końcowa albo gdy dostęp do nagrania tego szkolenia już wygasł (ta sama reguła co przy rejestracji zaświadczenia, liczona od końca szkolenia). Późniejsze dopisanie nie przedłuża tego terminu osobie indywidualnie.

## Co widzi nauczyciel

Tekst nad formularzem:

„Ustaw hasło dostępu do nagrania. Po wysłaniu formularza dopiszemy Cię do szkolenia i utworzymy konto na pnedu.pl.”

Pola:

- imię, nazwisko, własny adres e-mail (nie wspólna skrzynka typu sekretariat@),
- hasło do nagrania i potwierdzenie,
- zgoda RODO (wymagana),
- newsletter (dobrowolny).

Pod hasłem jest dopisek: jeśli konto na ten adres już jest, hasła nie zmieniamy.

Przycisk: **Ustaw hasło i zapisz dostęp**.

### Nowy adres

1. pnedu tworzy konto w `users` z podanym hasłem i od razu oznacza e-mail jako potwierdzony, żeby panel nagrania nie czekał na osobny mail weryfikacyjny.
2. API pneadm dopisuje wiersz w `participants`.
3. Osoba jest zalogowana. Strona **Konto gotowe** ma przycisk **Przejdź do nagrania** (`/dashboard/szkolenia`).
4. Pod przyciskiem, mniejszą czcionką: po zalogowaniu w panelu użytkownika można zmienić hasło albo całkowicie usunąć konto na pnedu.pl.

Hasło nie idzie do API panelu. Konto powstaje po stronie pnedu.

Jeśli zapis na listę nie dojdzie, a konto już powstało, formularz prosi o ponowne wysłanie i informuje, że hasła przy drugiej próbie nie zmieniamy.

### Adres, który już ma konto

Hasła nie nadpisujemy, nawet gdy ktoś wpisze nowe. Osoba zostaje dopisana do szkolenia (albo zaktualizowana, gdy ten e-mail już jest na liście). Strona mówi: **Zaloguj się na pnedu.pl** dotychczasowym hasłem. Nie logujemy jej z tego formularza.

Ten sam e-mail aktualizuje istniejący wiersz uczestnika, nie tworzy drugiego. Osoba wpisana bez e-maila, tylko pod zaświadczenie do druku, zostaje osobnym wierszem.

## E-mail i zaświadczenie

Po udanym zapisie wychodzi ten sam `CourseAccessMail` co przycisk **Wyślij e-mail nagranie** na liście uczestników — gdy na szkoleniu jest nagranie, materiały albo włączone pobieranie zaświadczenia.

Przycisk **Wyślij e-mail nagranie** sam konta nie zakłada. Dla osoby bez konta nadal prowadzi na `/register` z uzupełnionym imieniem, nazwiskiem i zablokowanym adresem z listy.

Zaświadczenie działa jak u pozostałych uczestników: pobranie generuje plik, a data i miejsce urodzenia są dopytywane przy pobieraniu, gdy szkolenie tego wymaga.

## Czego ten link nie robi

- Nie otwiera nagrania bez konta i bez hasła.
- Nie zastępuje listy obecności z dnia szkolenia (`/certificate-registration/…`).
- Nie dopisuje osoby do ClickMeeting — szkolenie jest już po spotkaniu albo dyrektor i tak ma link do pokoju.
- Nie zmienia hasła istniejącego konta.

## Kod

| Miejsce | Plik |
|---|---|
| Migracja | `pneadm/database/migrations/2026_09_23_170000_add_recording_enrollment_to_courses_table.php` |
| Przełącznik | `courses.recording_enrollment_open`, `recording_enrollment_ends_at`, `recording_enrollment_token` |
| Zapis uczestnika | `App\Services\RecordingEnrollmentService` |
| API | `GET/POST /api/recording-enrollment/…` (`api.token`) |
| Formularz i konto | pnedu `GET/POST /dostep-do-szkolenia/{token}`, `RecordingEnrollmentController` |
| Mail z listy uczestników | `CourseAccessMail`, link rejestracji: `App\Support\PneduRegistrationLink` |

Testy: `sail artisan test --filter=RecordingEnrollmentApiTest` (pneadm) i `sail artisan test --filter=RecordingEnrollmentTest` (pnedu). W pnedu testy założenia konta są pomijane, gdy baza `testing` nie ma aktualnej tabeli `users` (`deleted_at`, `first_name`, `email_verified_at`). Suit obu projektów nie odpalać równolegle — obie używają bazy `testing`.
