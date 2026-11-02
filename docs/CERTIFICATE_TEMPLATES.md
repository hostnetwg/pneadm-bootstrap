# Szablony zaświadczeń — edytor i renderowanie PDF (kanon)

Opis edytora `/admin/certificate-templates` (adm.pnedu.pl) i tego, jak szablon zamienia się w PDF zaświadczenia (panel adm, pnedu.pl, API).  
Ostatnia aktualizacja: **2026-11-02** (pełny przegląd kodu i danych z kopii prod).

Szerszy kontekst (typy szkoleń, API, kursy online, rejestracja): [CERTIFICATES.md](./CERTIFICATES.md). Linki tokenowe: [CERTIFICATE_DOWNLOAD_LINKS.md](./CERTIFICATE_DOWNLOAD_LINKS.md). Retencja plików PDF: [deploy/CERTIFICATE_PDF_RETENTION.md](./deploy/CERTIFICATE_PDF_RETENTION.md).

## Spis treści

1. [Najważniejsze w 10 zdaniach](#1-najważniejsze-w-10-zdaniach)
2. [Pliki i trasy](#2-pliki-i-trasy)
3. [Dane: tabela i JSON `config`](#3-dane-tabela-i-json-config)
4. [Edytor w panelu](#4-edytor-w-panelu)
5. [Przypisanie szablonu do szkolenia](#5-przypisanie-szablonu-do-szkolenia)
6. [Renderowanie: z JSON do PDF](#6-renderowanie-z-json-do-pdf)
7. [Bloki: co trafia na PDF](#7-bloki-co-trafia-na-pdf)
8. [Daty na PDF](#8-daty-na-pdf)
9. [Kiedy powstaje plik PDF i kiedy widać zmianę szablonu](#9-kiedy-powstaje-plik-pdf-i-kiedy-widać-zmianę-szablonu)
10. [Przepływy end-to-end](#10-przepływy-end-to-end)
11. [Pliki Blade (legacy)](#11-pliki-blade-legacy)
12. [Znane problemy (stan 2026-11-02)](#12-znane-problemy-stan-2026-11-02)
13. [Jak dodać nową funkcję do edytora (checklista)](#13-jak-dodać-nową-funkcję-do-edytora-checklista)
14. [Testy i dokumenty historyczne](#14-testy-i-dokumenty-historyczne)

---

## 1. Najważniejsze w 10 zdaniach

1. Źródłem prawdy szablonu jest kolumna `certificate_templates.config` (JSON w bazie **pneadm**).
2. PDF składa `App\Services\Certificate\TemplateRenderer` (HTML budowany w PHP), a DomPDF (`PDFGenerator`) zamienia go na PDF A4.
3. Pliki `resources/views/certificates/*.blade.php` są generowane przy zapisie szablonu, ale **żaden PDF ich nie używa** (legacy, patrz sekcja 11).
4. Szablon ma ustawienia globalne (`settings`) i listę bloków (`blocks`): `header`, `participant_info`, `course_info`, `custom_text` oraz dwa „stałe” bloki: `instructor_signature` i `footer`.
5. Kolejność ustalają tylko bloki zwykłe (`order`); podpis i stopka są zawsze na dole, pozycjonowane absolutnie.
6. Szkolenie bez wybranego szablonu (`courses.certificate_template_id = NULL`, w UI „Domyślny szablon”) dostaje szablon z `is_default = 1` i `is_active = 1` (dziś **id 1**, slug `default-old-1`; to **nie** jest szablon id 5 ze slugiem `default`).
7. pnedu.pl nie generuje PDF — woła API adm (`/api/certificates/ensure`, `/data`, `/generate`).
8. Jeśli plik PDF zaświadczenia już istnieje na dysku, API go zwraca i **nie renderuje ponownie** — zmiana szablonu jest widoczna dopiero po usunięciu/odtworzeniu pliku (sekcja 9).
9. Edytor ma znane błędy (sekcja 12), m.in. regresję tekstu „zorganizowanym w dniu” w szablonach 6–9. Dziura w endpointach galerii, znikający podpis/stopka przy tworzeniu i błędny podgląd są naprawione 2026-11-02.
10. Testy edytora: `tests/Feature/CertificateTemplateEditorTest.php`; brak testów `TemplateRenderer` — przy zmianach wyglądu PDF dodaj je (sekcja 13).

---

## 2. Pliki i trasy

### Trasy (pneadm)

| Metoda | Ścieżka | Akcja | Middleware |
|--------|---------|-------|------------|
| GET | `/admin/certificate-templates` | `CertificateTemplateController@index` | `auth`, `check.user.status` |
| GET | `/admin/certificate-templates/create` (`?clone_id=`) | `create` | j.w. |
| POST | `/admin/certificate-templates` | `store` | j.w. |
| GET | `/admin/certificate-templates/{id}/edit` | `edit` | j.w. |
| PUT | `/admin/certificate-templates/{id}` | `update` | j.w. |
| DELETE | `/admin/certificate-templates/{id}` | `destroy` (soft delete) | j.w. |
| GET | `/admin/certificate-templates/{id}/preview` | `preview` (PDF z danymi testowymi) | j.w. |
| GET | `/admin/certificate-templates/{id}/clone` | `clone` → redirect na `create?clone_id=` | j.w. |
| POST | `/admin/certificate-templates/{id}/restore` | `restore` (brak przycisku na liście; przywracanie przez Kosz `/trash`) | j.w. |
| GET | `/admin/certificate-templates/{id}` | `show` — **widok nie istnieje → 500** | j.w. |
| POST | `/api/admin/certificate-templates/upload-logo` | `uploadLogo` | web + `auth`, `check.user.status`, CSRF (od 2026-11-02; trasa w `routes/web.php` mimo prefiksu `/api`) |
| DELETE | `/api/admin/certificate-templates/delete-logo` | `deleteLogo` — tylko pliki bezpośrednio w `certificates/logos/` | j.w. |
| POST | `/api/admin/certificate-templates/upload-background` | `uploadBackground` | j.w. |
| DELETE | `/api/admin/certificate-templates/delete-background` | `deleteBackground` — tylko pliki bezpośrednio w `certificates/backgrounds/` | j.w. |

Generowanie zaświadczeń (szkolenia klasyczne): `/certificates/generate/{participant}`, `/participants/{participant}/certificate`, `/courses/{course}/certificates/*` — sekcja 10.

### Kod

| Obszar | Plik |
|--------|------|
| Kontroler edytora | `app/Http/Controllers/CertificateTemplateController.php` |
| Definicja typów bloków i pól formularza + generator Blade (legacy) | `app/Services/TemplateBuilderService.php` (`getAvailableBlocks()`, `generateBladeFile()`) |
| Model | `app/Models/CertificateTemplate.php` (`SoftDeletes`, `LogsActivity`, cast `config` → array, relacja `courses()`) |
| Widoki edytora | `resources/views/admin/certificate-templates/{index,create,edit}.blade.php`, `partials/event-text-variables-help.blade.php` (`edit-backup.blade.php` — martwy plik) |
| Dane do PDF | `app/Services/Certificate/CertificateGeneratorService.php` (`getCertificateData`, `getCertificateDataForEnrollment`, `resolveTemplateBlocks`, `resolveEffectiveCompletionDate`) |
| HTML z JSON | `app/Services/Certificate/TemplateRenderer.php` |
| HTML → PDF | `app/Services/Certificate/PDFGenerator.php` (DomPDF, A4, `isRemoteEnabled`) |
| Zmienne w `event_text` | `app/Services/Certificate/CertificateTemplateVariableResolver.php` |
| Numer rejestru | `app/Services/Certificate/CertificateNumberGenerator.php` |
| Komendy jednorazowe (historyczne migracje JSON) | `certificates:regenerate-templates`, `certificates:add-completion-text`, `certificates:add-subject-label`, `certificates:add-block-order`, `template:change-slug` |

---

## 3. Dane: tabela i JSON `config`

Tabela `certificate_templates` (baza pneadm): `id`, `name`, `slug` (unikalny, format `{id}-{slug-nazwy}` dla nowych), `description`, `config` (JSON), `preview_image` (nieużywane), `is_active`, `is_default`, `deleted_at`, `created_at`, `updated_at`.

Powiązania: `courses.certificate_template_id`, `online_courses.certificate_template_id`, `course_series.certificate_template_id` (kopiowany na nowe szkolenia serii — [CERTIFICATES.md](./CERTIFICATES.md) §6).

### Struktura `config`

```json
{
  "settings": {
    "font_family": "DejaVu Sans",
    "orientation": "portrait",
    "title_size": "48",
    "title_color": "#000000",
    "course_title_size": "22",
    "participant_name_size": "24",
    "participant_name_font": "DejaVu Sans",
    "participant_name_italic": false,
    "show_certificate_number": true,
    "margin_top": "100",
    "margin_bottom": "100",
    "margin_left": "100",
    "margin_right": "100",
    "background_image": "certificates/backgrounds/1764727076_gilosz-a4-pionowy.png",
    "show_background": true
  },
  "blocks": {
    "header": { "type": "header", "order": 0, "config": { "title": "ZAŚWIADCZENIE" } },
    "participant_info": { "type": "participant_info", "order": 1, "config": { "show_birth_info": "1" } },
    "course_info": {
      "type": "course_info", "order": 2,
      "config": {
        "completion_text": "ukończył/a szkolenie",
        "event_text": "zorganizowanym w dniu {data_zakonczenia} r. {czas_trwania}przez",
        "show_duration": "1",
        "organizer_name": "Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji",
        "subject_label": "TEMAT SZKOLENIA",
        "show_description": "1"
      }
    },
    "instructor_signature": { "type": "instructor_signature", "order": 9999, "config": [] },
    "footer": {
      "type": "footer", "order": 9999,
      "config": { "show_logo": "1", "logo_path": "certificates/logos/....png", "logo_size": "120", "logo_position": "center", "text": "..." }
    }
  }
}
```

Uwagi:

- `blocks` to **obiekt** (klucze dowolne: `header`, `block_2`, `footer_new`…), nie tablica. Kod zamienia go na listę przez `array_values()`.
- Wartości z formularza zapisują się jako **stringi** (`"48"`, `"1"`); checkbox zaznaczony = `"1"`, odznaczony = brak klucza. Brak walidacji liczb/kolorów.
- `event_text`: **brak klucza** lub `null` = tekst domyślny ze zmiennymi; `""` = brak akapitu; tekst = podstawienie zmiennych (sekcja 7).
- Ścieżki obrazów są względne do `storage/app/public/` (dysk `public`). Szablon 3 ma jeszcze starą ścieżkę `certificate-backgrounds/…`.
- Każdy zapis podbija `updated_at` — używane jako „wersja szablonu” w kluczu cache PDF (cache i tak jest wyłączony we wszystkich wywołaniach produkcyjnych: `cache=false`).

### Szablony na kopii prod (2026-11-02)

| id | slug | Użycie | Uwagi |
|----|------|--------|-------|
| 1 | `default-old-1` | 4 kursy + **wszystkie kursy bez szablonu (317)** | `is_default=1` |
| 2 | `minimal` | 0 | event_text bez zmiennych |
| 3 | `landscape` | 0 | poziomy; event_text bez zmiennych; stara ścieżka tła |
| 5 | `default` („Szablon Domyślny TIK”) | 97 kursów, seria TIK | ze zmiennymi |
| 6 | `6-szablon-domyslny-tik-kopia` | 141 kursów (2020–2023, ok. 176 tys. zaświadczeń) | event_text bez zmiennych — regresja §12 |
| 7 | `7-szablon-akademia-dyrektora` | 2 kursy | j.w. |
| 8 | `8-szablon-szkolny-administrator-office-365` | 8 kursów | j.w. |
| 9 | `9-kurs-tik-w-pracy-nauczyciela` | 1 kurs | j.w. |
| 10 | `10-szablon-domyslny-kopia` | 1 kurs online | e-learning, `{data_zakonczenia}` |

Sprawdzenie lokalnie: `sail artisan tinker` → `App\Models\CertificateTemplate::all()`.

---

## 4. Edytor w panelu

### Lista (`index`)

Kolumny: ID, nazwa, slug, opis, aktywny, domyślny, „Plik” (czy istnieje legacy Blade — bez znaczenia dla PDF), data. Akcje: podgląd PDF, edycja, klonowanie, usuwanie (modal Bootstrap). Usunięcie jest blokowane, gdy szablon ma przypisane **szkolenia klasyczne** (`courses`); kursy online i serie **nie są sprawdzane**. Usunięte szablony nie są widoczne na liście — przywracanie przez `/trash`. Na dole: mylący napis „Lokalizacja plików: `resources/views/certificates/`”.

### Formularz (`create` / `edit`)

Sekcje:

1. **Podstawowe**: nazwa, opis, aktywny, domyślny (zaznaczenie odznacza `is_default` we wszystkich innych; jedyny szablon zawsze staje się domyślny).
2. **Ustawienia**: czcionka (`DejaVu Sans` i inne z listy), orientacja, rozmiary tytułu/tematu/imienia, kolor tytułu, czcionka i kursywa imienia, marginesy (px), tło (galeria lub upload, `show_background`, `remove_background` w edycji).
3. **Bloki szablonu**: dodawanie z modala, usuwanie, zmiana kolejności. Pola bloku generowane z `TemplateBuilderService::getAvailableBlocks()` (typy pól: `text`, `textarea`, `number`, `checkbox`, `select`; `logo_path` = wybór z galerii).
4. **Stałe elementy**: checkbox „Data wystawienia i podpis prowadzącego” (+ „Pokaż numer rejestru” → `settings.show_certificate_number`) oraz checkbox „Stopka” z polami stopki. Bez zaznaczenia checkboxa `store()`/`update()` usuwa dany blok.

Różnice `create` vs `edit` (ważne przy rozbudowie — to dwa osobne, ok. 1300- i 1600-liniowe widoki z duplikowanym JS):

| | `create` | `edit` |
|-|----------|--------|
| Kolejność bloków | przeciąganie (SortableJS z CDN jsdelivr) | strzałki góra/dół |
| Stałe elementy | sekcja z kluczami `instructor_signature_new` / `footer_new`; nowy szablon — oba włączone; klon — jak w szablonie źródłowym | sekcja z kluczami istniejących bloków |
| Modal „Dodaj blok” | bez podpisu i stopki | bez podpisu i stopki |
| Klonowanie | `?clone_id=` — JS `loadBlocksFromConfig()` odtwarza bloki zwykłe; podpis/stopka wypełniają sekcję stałych elementów | — |

Galeria logo/tła: pliki w `storage/app/public/certificates/logos/` i `…/backgrounds/`, lista z dysku (nie z bazy), upload/usuwanie przez endpointy `/api/admin/certificate-templates/*` (fetch w JS z `_token`/`X-CSRF-TOKEN` i `Accept: application/json`). Usuwanie przyjmuje tylko nazwę pliku bezpośrednio w katalogu galerii (`CertificateTemplateController::resolveGalleryPath()`; inne ścieżki → 422). Usunięcie pliku z galerii nie sprawdza, czy używa go jakiś szablon.

### Podgląd (`preview`)

Renderuje PDF przez ten sam `TemplateRenderer`, z danymi testowymi (Jan Kowalski, Anna Nowak, „Przykładowe Szkolenie”, data = dziś, 420 min, numer `1/2025/PRZYKŁAD`). Podgląd pokazuje **zapisany** stan szablonu (nie niezapisane zmiany z formularza). Podpis testowy wskazuje na konkretny plik `instructors/L320…jpg` — gdy go nie ma, podgląd jest bez podpisu.

### Zapis (`store` / `update`)

1. Walidacja: tylko `name` (wymagane), `description`, plik tła (obraz ≤ 5 MB). Reszta przyjmowana bez walidacji.
2. Stałe bloki: jeśli checkbox zaznaczony — blok zostaje lub jest dodawany z `order = 9999`; jeśli nie — usuwany.
3. Sortowanie bloków po `order`, obsługa `remove_logo`, tło (galeria / upload / usunięcie).
4. `settings` budowane od zera z requestu (brak pola = wartość domyślna).
5. Zapis rekordu; przy `store` slug `{id}-{Str::slug(name)}`.
6. `TemplateBuilderService::generateBladeFile()` — zapis legacy Blade. Przy `update` błąd zapisu pliku daje komunikat błędu, mimo że baza jest już zaktualizowana.

---

## 5. Przypisanie szablonu do szkolenia

| Gdzie | Pole | Lista w UI |
|-------|------|-----------|
| Szkolenie klasyczne `/courses/{id}/edit` | `courses.certificate_template_id` | tylko `is_active = 1`; pusta opcja „Domyślny szablon” |
| Kurs online (sekcja Zaświadczenia) | `online_courses.certificate_template_id` | tylko `is_active = 1` |
| Seria `/courses/series/{id}/edit` | `course_series.certificate_template_id` | kopiowane na nowe szkolenia serii |

Wybór przy renderze (`CertificateGeneratorService`): `LEFT JOIN certificate_templates` po ID kursu (**bez** filtra `is_active` i `deleted_at` — przypisany szablon nieaktywny lub w koszu nadal działa). Gdy kurs nie ma szablonu: pierwszy rekord z `is_default = 1 AND is_active = 1`. Gdy takiego nie ma: pusty config → PDF bez bloków (pusta strona).

Format numeru (`certificate_format`, np. `{nr}/{course_id}/{year}/PNE`) i data wydania (`issue_date_certyficates`) są polami **kursu**, nie szablonu.

---

## 6. Renderowanie: z JSON do PDF

```
CertificateGeneratorService::generatePdf($participantId)          (klasyczne)
CertificateGeneratorService::generatePdfForEnrollment($enrollmentId) (online)
  └─ getCertificateData*()  — JOIN certificates + participants/enrollments + courses/online_courses
  │                          + certificate_templates + instructors; dzieli bloki na:
  │                          sorted_blocks (zwykłe, po order), instructor_signature_block, footer_block
  ├─ TemplateRenderer::render($data)   — HTML (style + bloki)
  ├─ PDFGenerator::generate($html, $settings) — DomPDF, A4, orientation
  └─ save_to_storage ? zapis storage/app/public/certificates/{course_id}/{numer}.pdf
                       (online: certificates/online-{online_course_id}/…) + certificates.file_path, generated_at
```

Dane przekazywane do renderera (klucze tablicy `$data`): `participant` (first_name, last_name, birth_date, birth_place), `course` (title, start_date, end_date, description), `instructor` (title, first_name, last_name, gender, signature), `certificate_number`, `duration_minutes`, `effective_completion_date`, `settings`, `sorted_blocks`, `instructor_signature_block`, `footer_block`, `is_pdf_mode`.

Różnice dla kursów online: imię/nazwisko/urodzenie z `certificates.holder_*` (z konta pnedu przy pobraniu), `course.description` = `training_scope` (fallback `description`), `course.start_date` = data wydania, `course.end_date = NULL`, minuty z `certificate_duration_minutes`.

Układ strony (CSS w `TemplateRenderer::buildStyles`):

- `@page { margin: 0 }`, marginesy jako `padding` na `body`; tło jako base64 `background-size: cover` na `body`.
- Bloki zwykłe płyną od góry, wyśrodkowane.
- Sekcja daty (lewa) i prowadzącego (prawa): `position: absolute; top = wysokość_strony − margin_bottom − 180px` (A4 przy 96 DPI: 1123 px pionowo, 794 px poziomo). Przy długim zakresie tekst może nachodzić na podpis — nie ma automatycznego zawijania na drugą stronę.
- Stopka: `position: fixed; bottom: margin_bottom`, czcionka 10 px.
- Obrazy (tło, logo, podpis) wczytywane z dysku i osadzane jako base64. Podpis prowadzącego jest dodatkowo przetwarzany przez GD (białe piksele ≥ 240 → przezroczyste), co przy dużych plikach jest wolne.

---

## 7. Bloki: co trafia na PDF

Legenda „HTML”: czy tekst z szablonu jest wstawiany jako HTML (✅), czy escapowany (❌ — znaczniki pojawią się jako tekst).

| Blok | Pole | Na PDF | HTML |
|------|------|--------|------|
| `header` | `title` | `<h1>` tytuł (domyślnie ZAŚWIADCZENIE) | ❌ |
| `participant_info` | — | stałe „Pan/i” + imię i nazwisko (`.participant-name`) | ❌ (dane) |
| | `show_birth_info` | „urodzony/a: DD.MM.RRRR r. w miejscowości X”; gdy brak danych — pusty akapit | ❌ |
| `course_info` | `completion_text` | akapit (np. „ukończył/a szkolenie”, z `<h3>` cyklu) | ✅ |
| | `event_text` | akapit po podstawieniu zmiennych | ✅ |
| | `show_duration` | włącza zmienną `{czas_trwania}` | — |
| | `organizer_name` | pogrubiony akapit | ✅ |
| | `subject_label` | `<h3>` (np. TEMAT SZKOLENIA) — etykieta w formularzu obiecuje HTML, ale renderer escapuje | ❌ |
| | (tytuł kursu) | `<h2 class="course-title">` z `courses.title` | ✅ (tytuł kursu nie jest escapowany) |
| | `show_description` | zakres: linie `1.`, `2.`… → `<ol>`, inaczej akapit; > 500 znaków → 13 px zamiast 16 px | ❌ |
| `custom_text` | `text`, `align` | akapit (nieużywany w żadnym szablonie) | ❌ (legacy Blade wstawiał HTML) |
| `instructor_signature` | — | lewa: „Data, {end_date} r.” + „Nr rejestru: …” (gdy `settings.show_certificate_number`); prawa: „prowadzący:/prowadząca:/trener:/prowadzący/a:” wg `instructors.gender`, tytuł + imię i nazwisko, obraz podpisu | — |
| `footer` | `show_logo`, `logo_path`, `logo_size`, `logo_position`, `text` | logo (base64, MIME zawsze deklarowany jako PNG) + tekst stopki | ✅ (`text`) |

### Zmienne `event_text`

| Zmienna | Wartość |
|---------|---------|
| `{data_zakonczenia}` | data wydania — patrz sekcja 8 (format DD.MM.RRRR) |
| `{data_rozpoczecia}` | `start_date` kursu |
| `{data_konca}` | `end_date` kursu (dla online puste) |
| `{czas_trwania}` | „w wymiarze X minut, ” gdy `show_duration` i X > 0, inaczej pusty |
| `{wymiar_minut}` | sama liczba minut |

Wielkość liter bez znaczenia; nieznana zmienna zostaje w tekście dosłownie. Tekst **bez zmiennych** trafia na PDF dosłownie (bez daty i „przez”). Domyślny tekst: `zorganizowanym w dniu {data_zakonczenia} r. {czas_trwania}przez`.

---

## 8. Daty na PDF

Na zaświadczeniu są dwie różne daty z różnych źródeł:

| Miejsce | Źródło |
|---------|--------|
| `{data_zakonczenia}` w `event_text` | `effective_completion_date`: `certificates.issue_date` → `online_courses.certificate_issue_date` → `courses.issue_date_certyficates` → **dzisiejsza data** |
| „Data, …” w bloku podpisu | szkolenia klasyczne: `courses.end_date`; kursy online: `end_date = NULL` → **data wygenerowania PDF** (`Carbon::parse(null)`) |

`certificates.issue_date` ustawia: API `ensure` (data z kursu albo dziś), wydanie kursu online, retencja (zamrożenie z `generated_at` przed skasowaniem pliku) i migracja `2026_11_01_120000_backfill_…`. Przycisk **Generuj** w panelu (`certificates.store`) i masowe „Generuj zaświadczenia” **nie** ustawiają `issue_date`.

---

## 9. Kiedy powstaje plik PDF i kiedy widać zmianę szablonu

| Kto | Wywołanie | Zachowanie |
|-----|-----------|-----------|
| Admin: klik w numer na `/courses/{id}/participants` (`/certificates/generate/{participant}`) | `generatePdf(save_to_storage, cache=false)` | **zawsze renderuje od nowa** i nadpisuje plik |
| Admin: „Generuj pliki PDF” dla kursu | sync ≤ 25, inaczej batch `GenerateCertificatePdfJob` | **pomija** zaświadczenia, które mają już plik |
| Admin: ikona PDF (`download-pdf`) | — | zwraca plik z dysku bez renderowania |
| pnedu (token / dashboard): pobranie | API `generate` | **jeśli plik istnieje — zwraca go**; inaczej renderuje i zapisuje |
| Zapis danych urodzenia na pnedu | API `update-birth-data` | kasuje plik, żeby następne pobranie wyrenderowało PDF od nowa |
| Retencja (codziennie 03:15) | `certificates:cleanup-pdfs` | kasuje pliki starsze niż 30 dni → następne pobranie renderuje **aktualnym** szablonem |

Wniosek dla edycji szablonu: zmiana w edytorze **nie zmienia** już wydanych plików. Uczestnik zobaczy nowy wygląd po: usunięciu PDF w panelu („Usuń PDF” / „Usuń pliki PDF” dla kursu), kliknięciu numeru przez admina albo po retencji. Przez retencję każda zmiana szablonu przypisanego do starych szkoleń (np. id 6) prędzej czy później trafia na ponownie pobierane historyczne zaświadczenia.

---

## 10. Przepływy end-to-end

### Szkolenie klasyczne — panel adm (np. `/courses/581/participants`)

1. **Generuj** przy uczestniku (`GET /participants/{id}/certificate`, `CertificateController@store`) — tworzy tylko rekord `certificates` z numerem (`CertificateNumberGenerator`: kolejny `{nr}` w kursie, rok z daty wydania lub startu). Bez PDF.
2. Masowo: „Generuj zaświadczenia” (tylko uczestnicy z kompletem danych: imię, nazwisko, data i miejsce urodzenia) lub „…dla wszystkich” — rekordy z kolejnymi numerami.
3. Klik w numer → `/certificates/generate/{participant}` → render + zapis pliku + pobranie `zaswiadczenie_{numer}.pdf`.
4. „Generuj pliki PDF” — pliki dla całego kursu (kolejka przy > 25; wymaga workera).

### Szkolenie klasyczne — uczestnik na pnedu.pl (np. `/certificate/{token}/581`)

1. Link z maila: `/certificates/{token}` (lista szkoleń dla e-maila) → `/certificate/{token}/{course}`.
2. Zalogowany użytkownik z tym samym, zweryfikowanym e-mailem jest przekierowywany na `/dashboard/zaswiadczenia/{course}`; zalogowany na inne konto dostaje 403.
3. Warunek: `courses.certificate_download_status = download_enabled`, inaczej 403.
4. Formularz daty i miejsca urodzenia pokazuje się, gdy szkolenie jest **płatne** (`courses.is_paid`) i brakuje danych (lub `?edit=1`). Wymóg wynika z rejestru zaświadczeń (rozporządzenie o placówkach doskonalenia), **nie** z ustawienia `show_birth_info` w szablonie. Zapis → API `update-birth-data` (aktualizuje wszystkich uczestników z tym e-mailem, chyba że e-mail ma różne imiona/nazwiska) i kasuje istniejące PDF.
5. Podgląd → „Pobierz” → strona „Trwa pobieranie…” → `/certificate/{token}/{course}/download`.
6. pnedu woła: `ensure` (rekord + numer + `issue_date`, jeśli nie istnieje) → `data` (numer do nazwy pliku) → `generate` (`save_to_storage=true`, `cache=false`) → `mark-downloaded` (statystyki).

Kursy online: [CERTIFICATES.md](./CERTIFICATES.md) §7 i `pnedu/docs/CERTIFICATES.md`.

---

## 11. Pliki Blade (legacy)

`TemplateBuilderService::generateBladeFile()` przy każdym zapisie tworzy `resources/views/certificates/{slug}.blade.php`. Nic w kodzie nie renderuje tych plików do PDF (od refaktoru JSON + API). Pliki różnią się od wyniku `TemplateRenderer` (np. Blade nie escapuje tytułu i `custom_text`, losowo przesuwa podpis `rand()`, nie pokazuje tytułu naukowego prowadzącego). Kolumna „Plik” na liście i napis o lokalizacji plików dotyczą tylko tych plików. Przy rozbudowie edytora **nie trzeba** aktualizować generatora Blade; rozsądny kierunek to jego usunięcie (decyzja Waldemara).

Pakiet `pne/certificate-generator` jest w `composer.json` z `dont-discover` i nie jest używany przez generowanie PDF.

---

## 12. Znane problemy (stan 2026-11-02)

Stan zweryfikowany na kodzie i kopii bazy prod.

### Naprawione 2026-11-02 (kod lokalnie; wdrożenie na prod — patrz `NEXT_STEPS.md`)

- ~~Endpointy galerii bez logowania~~ — trasy `/api/admin/certificate-templates/*` były w `routes/api.php` bez auth i bez CSRF, a `deleteLogo`/`deleteBackground` pozwalały przez `../` usunąć dowolny plik (potwierdzone lokalnie). Teraz: `routes/web.php` w grupie `auth` + `check.user.status`, CSRF, usuwanie tylko plików bezpośrednio w katalogu galerii. Logo nadal przyjmuje SVG (ryzyko tylko ze strony zalogowanych użytkowników ADM).
- ~~Nowy szablon / klon traci podpis i stopkę~~ — `create` ma sekcję stałych elementów.
- ~~Podgląd bez numeru i czasu trwania~~ — `preview()` przekazuje `certificate_number`, `duration_minutes`, `effective_completion_date`.
- ~~Regresja „zorganizowanym w dniu”~~ — migracja `2026_11_02_120000_add_start_date_variables_to_legacy_certificate_event_text` (pkt 2 niżej).

Testy: `tests/Feature/CertificateTemplateEditorTest.php`, `tests/Feature/CertificateLegacyEventTextTest.php`.

### Krytyczne

1. *(numer zachowany dla odwołań — naprawione, patrz wyżej)*
2. ~~**Regresja „zorganizowanym w dniu”**~~ — **naprawione 2026-11-02** (opis zostaje jako historia). Od commita `9a8c4b4`, 2026-07-08). Wcześniej renderer dopisywał do `event_text` datę, czas trwania i „przez”. Teraz tekst bez zmiennych idzie dosłownie. Szablony 2, 3, 6, 7, 8, 9 mają `event_text` bez zmiennych (np. „zorganizowanym w dniu”), więc nowo renderowane PDF mają urwane zdanie bez daty: „…z cyklu TIK w pracy NAUCZYCIELA / zorganizowanym w dniu / „Platforma Nowoczesnej Edukacji””. Dotyczy 152 szkoleń (2020–2026, ok. 179 tys. zaświadczeń) przy każdym ponownym renderze — a retencja 30 dni sprawia, że praktycznie każde ponowne pobranie renderuje od nowa.
   - **Naprawa (decyzja Waldemara):** na PDF ma być **data rozpoczęcia** (bez godziny) i wyliczony czas trwania, nie data zakończenia. Migracja dopisuje do tekstu bez zmiennych w szablonach 2, 3, 6–9 końcówkę ` {data_rozpoczecia} r. {czas_trwania}przez` — dokładnie to, co dopisywał stary kod. Odmiana zostaje („zorganizowane” w 9). Tekst z jakąkolwiek zmienną nie jest ruszany; `down()` cofa tylko tę końcówkę. Zmiana `updated_at` szablonu unieważnia cache PDF (`template_version`). `{czas_trwania}` jest puste, gdy szablon nie ma `show_duration` (2, 9) — jak dawniej.
   - **Szablony 1 i 5** miały `{data_konca}` — ta sama migracja zamienia na `{data_rozpoczecia}` (zasada: „ukończył/a szkolenie zorganizowane w dniu <data rozpoczęcia>”). Bez widocznej różnicy na istniejących kursach (start i koniec ten sam dzień). **Szablon 10** (kursy online, `{data_zakonczenia}`) celowo bez zmian — inna specyfika.
   - **Zasada na przyszłość:** w szablonach szkoleń (nie kursów online) używaj `{data_rozpoczecia}`, nie `{data_konca}`.
   - **Komenda:** `php artisan certificates:audit-event-text [--list] [--since=2026-07-08] [--templates=2,3,6,7,8,9] [--delete-files [--force]]`. Bez `--delete-files` tylko odczyt: PDF-y na dysku wygenerowane od daty (z uciętym tekstem) i zaświadczenia pobrane w tym okresie. `--delete-files` kasuje te pliki przez `CertificatePdfRetentionService::purgeFiles()` (te same zabezpieczenia co retencja: tylko `certificates/<id>/*.pdf`, zamrożenie `issue_date`, rekord i numer zostają). Bez `--templates` wykrywa szablony z `event_text` bez zmiennych — **po migracji podaj ID ręcznie**. Na kopii prod 2026-11-02: 15 zaświadczeń (12 plików).

### Ważne

3. *(naprawione — patrz wyżej)*
4. *(naprawione — patrz wyżej)*
5. **„Data, …” przy podpisie ≠ data wydania.** Klasyczne: zawsze `courses.end_date` (ignoruje `issue_date_certyficates`). Online: data wygenerowania PDF, czyli zmienia się przy każdym renderze.
6. **Generuj w panelu nie zapisuje `issue_date`** (w przeciwieństwie do API `ensure`). Gdy kurs nie ma „Daty wydania zaświadczeń”, `{data_zakonczenia}` = dzień renderu.
7. **Etykieta tematu (`subject_label`) i własny tekst (`custom_text`)** — formularz obiecuje HTML, PDF pokazuje znaczniki jako tekst.
8. **Usuwanie szablonu** sprawdza tylko `courses`, nie `online_courses` ani `course_series`. Szablon w koszu nadal renderuje się dla przypisanych kursów (JOIN bez `deleted_at`), ale znika z listy wyboru w edycji kursu — kolejny zapis kursu po cichu przełączy go na „Domyślny”.

### Drobne / porządkowe

9. `GET /admin/certificate-templates/{id}` (`show`) → 500, brak widoku.
10. Natywne `alert()`/`confirm()` w widokach edytora (ok. 34 wystąpienia) — niezgodne z `docs/UI_MODALS.md`.
11. Brak walidacji ustawień (liczby, kolor) — wartości trafiają wprost do CSS.
12. `create` i `edit` to dwa duże, zduplikowane widoki z różnymi mechanizmami sortowania; SortableJS ładowany z CDN.
13. Brak przycisku przywracania na liście (jest tylko w `/trash`).
14. Logo w stopce zawsze deklarowane jako `image/png`, niezależnie od formatu pliku.
15. Martwe: `edit-backup.blade.php`, generator Blade, kolumna „Plik”, `preview_image`, `header_config`/`course_info_config`/`footer_config` w danych generatora.

---

## 13. Jak dodać nową funkcję do edytora (checklista)

Nowe pole w istniejącym bloku albo nowy typ bloku:

1. `TemplateBuilderService::getAvailableBlocks()` — definicja pola (`type`, `label`, `default`, `options`). Na tej podstawie rysują się pola w obu widokach (Blade dla istniejących bloków, JS `renderField()` dla dodawanych).
2. `edit.blade.php` **i** `create.blade.php` — jeśli pole wymaga specjalnej obsługi (jak `logo_path`, `event_text`), dopisz ją w obu miejscach (PHP i JS).
3. `TemplateRenderer::buildBlock()` / `build…Block()` — rzeczywisty wygląd na PDF. Świadomie zdecyduj, czy tekst ma być escapowany (`htmlspecialchars`).
4. Nowe ustawienie globalne: `store()` i `update()` w kontrolerze (zbudują `settings` od zera — bez tego wartość zniknie przy zapisie) + `TemplateRenderer::buildStyles()`.
5. Wartości domyślne dla istniejących szablonów: brak klucza w JSON musi dawać dotychczasowy wygląd (zaświadczenia historyczne są renderowane ponownie po retencji — sekcja 9).
6. `CertificateTemplateController::preview()` — dane testowe z poprawnymi kluczami (`certificate_number`, `duration_minutes`, `effective_completion_date`).
7. Zmienne tekstowe: `CertificateTemplateVariableResolver` (`buildVariableMap`, `variableHelp`) + test jednostkowy.
8. Testy: renderer na przykładowym `config` (HTML zawiera / nie zawiera fragmentów), zapis szablonu (feature test), regresja dla istniejących szablonów.
9. Generator Blade (`TemplateBuilderService::build…`) — nie jest wymagany (legacy).
10. Dokumentacja: ta strona (§3, §7, §12) + `TESTING.md` + `NEXT_STEPS.md`.

---

## 14. Testy i dokumenty historyczne

Testy dziś: `tests/Feature/CertificateTemplateEditorTest.php` (galeria: brak dostępu bez logowania, upload/usuwanie po zalogowaniu, odrzucanie ścieżek spoza galerii; stałe elementy w `create` i klonie; zapis podpisu/stopki; dane podglądu), `tests/Unit/CertificateTemplateVariableResolverTest.php` (zmienne), `tests/Feature/CourseSeriesCertificateSettingsTest.php`, `tests/Feature/CertificatePdfRetentionTest.php`, `tests/Feature/CertificateRegistrationApiTest.php`. Brak testów `TemplateRenderer` i `CertificateGeneratorService`.

Komenda: `sail artisan test --filter='CertificateTemplateEditorTest|CertificateTemplateVariableResolverTest|CourseSeriesCertificateSettingsTest|CertificatePdfRetentionTest|CertificateRegistrationApiTest'`.

Ręcznie: `/admin/certificate-templates` → Podgląd; prawdziwy PDF lokalnie: `sail artisan tinker` → `app(\App\Services\Certificate\CertificateGeneratorService::class)->generatePdf($participantId, ['cache' => false])->output()` (bez `save_to_storage`, nie nadpisuje pliku).

Pliki `CERTIFICATE_*.md`, `TEMPLATE_MIGRATION_TO_PACKAGE.md`, `LOGO_SYSTEM_README.md`, `CERTIFICATE_SYSTEM_REFACTORING_PLAN.md` itp. w katalogu głównym repo opisują wcześniejsze etapy (pakiet `pne-certificate-generator`, pliki Blade jako źródło PDF). **Nie są aktualne** — kanonem jest ten dokument i [CERTIFICATES.md](./CERTIFICATES.md).
