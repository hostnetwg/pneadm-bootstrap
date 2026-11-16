# Linki do pobierania zaświadczeń (token per e-mail)

> **Uwaga:** Ten dokument dotyczy **szkoleń klasycznych** (`courses`). Kursy online — tylko przez zalogowany dashboard.  
> Pełny przegląd systemu: [CERTIFICATES.md](./CERTIFICATES.md).

## Cel

Uczestnicy z przypisanym adresem e-mail mogą pobrać swoje zaświadczenia ze strony **pnedu.pl** przez unikalny link **bez logowania**. Jeden link pokazuje listę wszystkich szkoleń danego uczestnika; przy każdym szkoleniu widać status (Pobierz / W przygotowaniu) i – po aktywacji przez admina – możliwość pobrania PDF.

## Tabela `participant_download_tokens` (baza pneadm)

- **Lokalizacja migracji:** `pneadm-bootstrap/database/migrations/`
- **Opis:** Mapuje znormalizowany adres e-mail uczestnika na unikalny token (64 znaki). Jeden token na e-mail – ten sam link służy do wyświetlenia listy wszystkich szkoleń i pobierania zaświadczeń.
- **Kolumny:**
  - `id` – klucz główny
  - `email_normalized` – e-mail znormalizowany (trim + lowercase), unikalny
  - `token` – unikalny token 64-znakowy do URL
  - `created_at`, `updated_at`
- **Indeksy:** UNIQUE na `email_normalized`, UNIQUE na `token`.

## Status zaświadczeń na kursie (`certificate_download_status`)

- **Kolumna:** `certificate_download_status` (string: `download_enabled` | `in_preparation` | `no_certificate`).
- **Znaczenie:** Włączenie przez admina w edycji kursu umożliwia uczestnikom pobieranie zaświadczeń dla tego kursu przez link z tokenem lub dashboard. Dopóki status to `in_preparation` lub `no_certificate`, przy szkoleniu wyświetla się odpowiedni komunikat.
- **Migracja:** `2026_03_15_000001_replace_certificates_download_enabled_with_status.php` (zastąpiła starą kolumnę `certificates_download_enabled`).

## Generowanie tokenów

- **Przy pierwszym wystąpieniu e-maila:** Model `ParticipantDownloadToken::getOrCreateTokenForEmail($email)` – zwraca istniejący lub tworzy nowy token (64 znaki).
- **Observer:** `ParticipantObserver::created()` – przy każdym utworzeniu uczestnika z e-mailem wywołuje `getOrCreateTokenForEmail`.
- **Importy (raw insert):** W `EducationController::exportParticipants` (certgen/students) oraz w `NODNSzkoleniaController::exportParticipants` po wstawieniu uczestnika z e-mailem wywoływane jest `getOrCreateTokenForEmail`.
- **Backfill (istniejące dane):** Komenda `sail artisan participants:backfill-download-tokens` (opcja `--dry-run` do podglądu, `--batch=5000` do rozmiaru batcha).

## Przepływ użytkownika

1. Użytkownik otrzymuje link: `https://pnedu.pl/certificates/{token}`.
2. Otwiera link → strona lista szkoleń (bez logowania).
3. Przy każdym szkoleniu: **Pobierz zaświadczenie** (gdy admin włączył pobieranie i zaświadczenie istnieje) lub **W przygotowaniu**.
4. Klik „Pobierz zaświadczenie” → `https://pnedu.pl/certificate/{token}/{course_id}` → pobranie PDF.

## Link dla szkoły po szkoleniu zamkniętym online

Po zakończeniu szkolenia online powiązanego ze sprawą (`closed_training_cases`) operator w ADM klika **Przygotuj e-mail z podziękowaniem**. Mail (do edycji przed wysyłką) zawiera podziękowanie, listę wszystkich uczestników tego szkolenia oraz jeden adres:

`https://pnedu.pl/szkolenie-zamkniete/zaswiadczenia/{token}`

Token (`school_certificate_token`, 64 znaki hex) jest na sprawie, nie na e-mailu uczestnika. Nad tematem przygotowanego e-maila operator ustawia liczbę dni ważności, liczoną od wysłania do szkoły (domyślnie 7, najwyżej 365). Do wysłania link nie działa. Każde kolejne przygotowanie oraz każdy kolejny wysłany e-mail z podziękowaniem wyłącza poprzedni adres. Na karcie sprawy **Unieważnij link do zaświadczeń** kasuje adres od razu. Po terminie i po unieważnieniu strona zwraca 404. Tuż pod linkiem w mailu jest na czerwono data ważności. Tabela w mailu pokazuje tylko, czy link do zaświadczenia poszedł na e-mail uczestnika. Kolumna **Pobranie przez uczestnika** jest wyłącznie na stronie, bo tam stan jest aktualny. Na tej stronie szkoła może sama unieważnić link przyciskiem z potwierdzeniem. W mailu jest do tego odnośnik, a nie wyłącznik: programy pocztowe otwierają linki same i skasowałyby adres przed przeczytaniem wiadomości. Przycisk **Pobierz PDF** działa, gdy na szkoleniu jest status `download_enabled`. To pobranie **nie** wywołuje oznaczenia pobrania uczestnika.

Osobisty link `https://pnedu.pl/certificates/{token}` zostaje dla uczestnika i nie trafia do maila szkoły, bo pokazuje wszystkie jego szkolenia.

Strona ma `noindex` i nie wchodzi do sitemapy. Przed końcem szkolenia adres zwraca 404.

## Admin

- **Gdzie włączyć pobieranie:** Panel pneadm → Szkolenia → Edycja kursu → **Status zaświadczeń** → „Udostępnij pobieranie zaświadczeń (link na pnedu.pl)”.

## Pliki / miejsca w kodzie

| Zadanie | Projekt | Plik / miejsce |
|--------|---------|------------------|
| Migracja tabeli tokenów | pneadm-bootstrap | `database/migrations/2026_03_14_000001_create_participant_download_tokens_table.php` |
| Migracja flagi/statusu | pneadm | `database/migrations/2026_03_15_000001_replace_certificates_download_enabled_with_status.php` |
| Model tokenu | pneadm-bootstrap | `app/Models/ParticipantDownloadToken.php` |
| Model tokenu (odczyt) | pnedu | `app/Models/ParticipantDownloadToken.php` (connection pneadm) |
| Observer | pneadm-bootstrap | `app/Observers/ParticipantObserver.php` (created) |
| Backfill | pneadm-bootstrap | `app/Console/Commands/BackfillParticipantDownloadTokens.php` |
| Lista szkoleń po tokenie | pnedu | `CertificateController::showListByToken`, route `certificates.list-by-token` |
| Pobieranie PDF po tokenie | pnedu | `CertificateController::downloadByToken`, route `certificates.download-by-token` |
| Widok listy | pnedu | `resources/views/certificates/list-by-token.blade.php` |
| Checkbox w edycji kursu | pneadm | `resources/views/courses/edit.blade.php`, `CoursesController::update` |

Zobacz też: [CERTIFICATES.md](./CERTIFICATES.md).

## Uruchomienie backfillu (jednorazowo)

W katalogu **pneadm-bootstrap**:

```bash
sail artisan participants:backfill-download-tokens --dry-run   # podgląd
sail artisan participants:backfill-download-tokens             # zapis
```

Opcja `--batch=5000` (domyślnie) ogranicza liczbę rekordów participants w jednym batchu.
