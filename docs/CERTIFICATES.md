# Zaświadczenia — dokumentacja (kanon: pneadm)

Opis systemu generowania PDF zaświadczeń w ekosystemie **pneadm** (adm) + **pnedu** (front).  
Ostatnia aktualizacja: **lipiec 2026**.

## Spis treści

1. [Dwa typy szkoleń](#1-dwa-typy-szkoleń)
2. [Architektura](#2-architektura)
3. [Szablony (`certificate_templates`)](#3-szablony-certificate_templates)
4. [Pole „Tekst wydarzenia” i zmienne](#4-pole-tekst-wydarzenia-i-zmienne)
5. [Zakres i czas trwania na PDF](#5-zakres-i-czas-trwania-na-pdf)
6. [Szkolenia klasyczne (`courses`)](#6-szkolenia-klasyczne-courses)
7. [Kursy online (`online_courses`)](#7-kursy-online-online_courses)
8. [API (pnedu → pneadm)](#8-api-pnedu--pneadm)
9. [Linki bez logowania (token)](#9-linki-bez-logowania-token)
10. [Pliki w repozytorium](#10-pliki-w-repozytorium)
11. [Powiązane dokumenty](#11-powiązane-dokumenty)

---

## 1. Dwa typy szkoleń

| Aspekt | Szkolenia klasyczne | Kursy online |
|--------|---------------------|--------------|
| Tabele | `courses`, `participants` | `online_courses`, `online_course_enrollments` |
| Uczestnictwo | `participants` (email, imię, dane urodzenia) | `online_course_enrollments` (email zapisu) |
| Rekord certyfikatu | `certificates.participant_id` + `course_id` | `certificates.online_course_enrollment_id` + `online_course_id` |
| Pobieranie na pnedu | Dashboard / link tokenem | Dashboard (wymagane logowanie) |
| Czas trwania na PDF | `end_date − start_date` (minuty) | `online_courses.certificate_duration_minutes` (ręcznie) |
| Zakres na PDF | `courses.description` | `online_courses.training_scope` (fallback: `description`) |

---

## 2. Architektura

```
┌─────────────────┐     HTTPS + Bearer token      ┌──────────────────────────┐
│  pnedu.pl       │ ────────────────────────────► │  adm.pnedu.pl (pneadm)   │
│  CertificateApi │   POST /api/certificates/*    │  CertificateApiController│
│  Client         │                               │  CertificateGenerator    │
└─────────────────┘                               │  TemplateRenderer (JSON) │
                                                  └────────────┬─────────────┘
                                                               │
                                                               ▼
                                                  Baza pneadm: certificates,
                                                  certificate_templates, courses,
                                                  online_courses, …
```

- **Źródło prawdy szablonu:** `certificate_templates.config` (JSON w MySQL).
- **PDF:** `CertificateGeneratorService` → `TemplateRenderer::render()` → DomPDF.
- **Pliki** `resources/views/certificates/*.blade.php` — generowane przy zapisie szablonu (legacy); **PDF nie czyta ich bezpośrednio** przy renderze z JSON.

---

## 3. Szablony (`certificate_templates`)

- Panel: `/admin/certificate-templates`
- Blok **Informacje o kursie** (`course_info`):
  - Tekst ukończenia, tekst wydarzenia (`event_text`)
  - Pokaż czas trwania (`show_duration`)
  - Nazwa organizatora, etykieta tematu
  - Pokaż zakres szkolenia (`show_description`)

Kolejność pól w formularzu edycji odpowiada układowi na PDF (organizator → czas → temat → tytuł kursu → zakres).

---

## 4. Pole „Tekst wydarzenia” i zmienne

Klasa: `App\Services\Certificate\CertificateTemplateVariableResolver`.

| Zmienna | Znaczenie |
|---------|-----------|
| `{data_zakonczenia}` | Data ukończenia / wydania zaświadczenia |
| `{data_rozpoczecia}` | Data rozpoczęcia szkolenia (`start_date`) |
| `{data_konca}` | Data zakończenia (`end_date`) |
| `{czas_trwania}` | Fragment „w wymiarze X minut, ” (gdy `show_duration` i X > 0) |
| `{wymiar_minut}` | Sama liczba minut |

**Zasady:**

- Tekst **ze zmiennymi** — na PDF dokładnie to, co wpiszesz (po podstawieniu).
- Tekst **bez zmiennych** — trafia na PDF **dosłownie** (bez automatycznej daty / „przez”).
- **Puste** `event_text` — brak akapitu o wydarzeniu.
- **`null` / brak klucza** — domyślny szablon:  
  `zorganizowanym w dniu {data_zakonczenia} r. {czas_trwania}przez`

Przykład:

```
zorganizowanym w dniu {data_zakonczenia} r. {czas_trwania}przez
```

---

## 5. Zakres i czas trwania na PDF

Wyświetlane w bloku `course_info`, gdy w szablonie włączone `show_description` / `show_duration`.

| Źródło danych | Zakres (lista numerowana lub tekst) | Minuty |
|---------------|-------------------------------------|--------|
| `courses` | Kolumna `description` (w UI: **Zakres szkolenia / Zagadnienia**) | z dat kursu |
| `online_courses` | `training_scope` (fallback: `description`) | `certificate_duration_minutes` |

Lista numerowana: linie zaczynające się od `1.`, `2.` itd. — renderowane jako `<ol>`.

---

## 6. Szkolenia klasyczne (`courses`)

### Admin (pneadm)

- Edycja: `/courses/{id}/edit`
- **Status zaświadczeń:** `certificate_download_status`  
  `download_enabled` | `in_preparation` | `no_certificate`
- **Zakres:** pole „Zakres szkolenia / Zagadnienia” → `courses.description`
- Szablon: `certificate_template_id`, format: `certificate_format`

### pnedu

- Zalogowany użytkownik: `/dashboard/zaswiadczenia`
- Bez logowania: link z tokenem — patrz [CERTIFICATE_DOWNLOAD_LINKS.md](./CERTIFICATE_DOWNLOAD_LINKS.md)

---

## 7. Kursy online (`online_courses`)

Szczegóły struktury kursu: [ONLINE-COURSES.md](./ONLINE-COURSES.md).

### Pola certyfikatu (tabela `online_courses`)

| Kolumna | Opis |
|---------|------|
| `certificate_download_status` | Jak przy `courses` |
| `certificate_template_id` | Szablon PDF |
| `certificate_format` | Np. `{nr}/{online_course_id}/{year}/PNE-KO` |
| `certificate_issue_date` | Opcjonalna stała data na certyfikacie |
| `certificate_duration_minutes` | Czas na PDF (minuty) |
| `training_scope` | Zakres / zagadnienia na PDF |
| `certificate_collect_birth_data` | Zbieraj datę i miejsce urodzenia |
| `certificate_birth_data_required` | Wymagane przed pobraniem |

### Admin

- Edycja kursu: sekcja **Zaświadczenia (kurs online)** w formularzu
- Zapisy: `/online-courses/{id}/enrollments` — generuj / usuń certyfikat, pobierz PDF

### pnedu

- `/dashboard/kursy-online/{enrollment}/zaswiadczenie` — profil + pobranie
- Imię i nazwisko z konta użytkownika; email zapisu musi zgadzać się z kontem

Dokumentacja frontu: `pnedu/docs/CERTIFICATES.md`.

---

## 8. API (pnedu → pneadm)

Middleware: `api.token` — nagłówek `Authorization: Bearer <PNEADM_API_TOKEN>`.

| Metoda | Ścieżka | Opis |
|--------|---------|------|
| GET | `/api/certificates/health` | Diagnostyka |
| POST | `/api/certificates/ensure` | Utwórz rekord certyfikatu (participant lub enrollment) |
| POST | `/api/certificates/generate` | PDF (base64 lub stream) |
| POST | `/api/certificates/data` | Metadane certyfikatu |
| POST | `/api/certificates/mark-downloaded` | Szkolenie klasyczne |
| POST | `/api/certificates/mark-online-downloaded` | Kurs online |
| POST | `/api/participants/update-birth-data` | Dane urodzenia uczestnika |

**`.env`:** `PNEADM_API_TOKEN` — **identyczny** w pneadm i pnedu (sprawdź, że linia w `.env` nie jest sklejona z poprzednią).

Kontroler: `App\Http\Controllers\Api\CertificateApiController`.

---

## 9. Linki bez logowania (token)

Szkolenia klasyczne — uczestnik z e-mailem, jeden token na adres:  
→ [CERTIFICATE_DOWNLOAD_LINKS.md](./CERTIFICATE_DOWNLOAD_LINKS.md)

Kursy online — **tylko przez zalogowane konto** na pnedu (brak tokenu publicznego).

---

## 10. Pliki w repozytorium (pneadm)

| Obszar | Pliki |
|--------|--------|
| API | `app/Http/Controllers/Api/CertificateApiController.php`, `routes/api.php` |
| Generowanie PDF | `app/Services/Certificate/CertificateGeneratorService.php` |
| Szablony JSON | `app/Services/Certificate/TemplateRenderer.php` |
| Zmienne `event_text` | `app/Services/Certificate/CertificateTemplateVariableResolver.php` |
| Kursy online — wydanie | `app/Services/Certificate/OnlineCourseCertificateIssueService.php` |
| Numeracja | `app/Services/Certificate/CertificateNumberGenerator.php` |
| Admin szablony | `app/Http/Controllers/CertificateTemplateController.php` |
| Admin online | `app/Http/Controllers/OnlineCoursesController.php`, `OnlineCourseEnrollmentController.php` |
| Model | `app/Models/Certificate.php` |
| Testy | `tests/Unit/CertificateTemplateVariableResolverTest.php` |

---

## 11. Powiązane dokumenty

| Dokument | Zawartość |
|----------|-----------|
| [CERTIFICATE_DOWNLOAD_LINKS.md](./CERTIFICATE_DOWNLOAD_LINKS.md) | Tokeny publiczne, lista szkoleń bez logowania |
| [ONLINE-COURSES.md](./ONLINE-COURSES.md) | Moduły, lekcje, enrollments |
| [deploy/2026-07-online-certificates-production-deploy.md](./deploy/2026-07-online-certificates-production-deploy.md) | Checklist wdrożenia prod (lipiec 2026) |
| `pnedu/docs/CERTIFICATES.md` | Trasy i serwisy po stronie pnedu |
| `CERTIFICATE_SYSTEM_REFACTORING_PLAN.md` (root) | Historyczny plan refaktoru (JSON + API) |

---

## Migracje (pneadm) — pakiet kursów online + certyfikaty

| Migracja | Opis |
|----------|------|
| `2026_07_07_120000_add_certificate_fields_to_online_courses_table` | Pola cert na `online_courses` |
| `2026_07_07_120001_extend_certificates_for_online_courses` | FK enrollment, nullable participant/course |
| `2026_07_08_120000_add_training_scope_to_online_courses_table` | `training_scope` |
| `2026_07_08_120001_add_comment_to_courses_description_column` | Komentarz MySQL `courses.description` |
| `2026_07_08_130000_add_certificate_duration_minutes_to_online_courses_table` | Czas trwania (min) |

Wcześniejsze: `2026_03_15_000001_replace_certificates_download_enabled_with_status` (`certificate_download_status` na `courses`).
