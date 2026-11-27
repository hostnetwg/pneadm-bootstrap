# Notatka o instruktorze na liście szkoleń

Data: 2026-11-27

Na liście szkoleń (`/courses`), w kolumnie instruktora, pod rozliczeniem jest **Notatka**. Widzą ją i zapisują tylko Super Administratorzy, tak jak rozliczenie wypłaty. Zwykły administrator nie widzi linku, karteczki ani treści.

To nie jest pole **Notatki techniczne** z edycji szkolenia (`courses.notatki`). Tamta notatka zostaje osobno.

## Co widać

- Brak treści: link **Notatka**.
- Po zapisie: żółta karteczka. Najechanie pokazuje treść. Kliknięcie otwiera to samo okno.
- W oknie, po lewej na wysokości **Anuluj** i **Zapisz**, jest **Generuj**. Wstawia jedną linię ze spacją na końcu:
  - szkolenie otwarte: `SZKOLENIE: Tytuł (27.11.2026) `
  - szkolenie zamknięte (`category = closed`): `SZKOLENIE RP: Tytuł (27.11.2026) `
- Data to dzień rozpoczęcia. Gdy jej nie ma, w nawiasie jest `brak daty`.
- Gdy pole jest puste, zostaje sam nagłówek. Gdy coś już jest wpisane, nagłówek wchodzi na górę. Drugie **Generuj** odświeża pierwszą linię.
- **USUŃ** jest obok **Anuluj**. Po potwierdzeniu kasuje notatkę i na liście wraca link **Notatka**. Puste pole i **Zapisz** też kasuje notatkę.

Jedna notatka na szkolenie. Należy do szkolenia, nie do karty instruktora. Zmiana prowadzącego nie przenosi jej na inną osobę i nie kasuje jej sama.

## Baza

Tabela `course_instructor_notes` w `pneadm`:

- `course_id` — unikalny, klucz do `courses`, kasowanie szkolenia kasuje notatkę
- `body` — treść

Migracja: `2026_11_27_100000_create_course_instructor_notes_table`.

Zapis: `PUT /courses/{course}/instructor-note`, tylko rola `super_admin`.

## Testy

`sail artisan test --filter=CourseInstructorNote`

Deploy: [2026-11-instructor-note-deploy.md](./deploy/2026-11-instructor-note-deploy.md).
