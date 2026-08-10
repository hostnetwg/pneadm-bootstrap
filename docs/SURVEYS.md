# Ankiety po szkoleniu — dokumentacja (kanon: pneadm)

Opis procesu ankiet ewaluacyjnych w ekosystemie **adm.pnedu.pl** (pneadm) + **pnedu.pl**.  
Ostatnia aktualizacja: **2026-08-10** (rekomendacje na homepage od najnowszych).

## Spis treści

1. [Cel i stan](#1-cel-i-stan)
2. [Trzy kanały](#2-trzy-kanały)
3. [Ustawienia globalne](#3-ustawienia-globalne)
4. [Szablony pytań](#4-szablony-pytań)
5. [Ankieta na szkoleniu (adm)](#5-ankieta-na-szkoleniu-adm)
6. [Wypełnianie (pnedu)](#6-wypełnianie-pnedu)
7. [Rekomendacje na homepage](#7-rekomendacje-na-homepage)
8. [Import CSV (Google Forms) — nadal wspierany](#8-import-csv-google-forms--nadal-wspierany)
9. [Schemat bazy](#9-schemat-bazy)
10. [Trasy i pliki](#10-trasy-i-pliki)
11. [Deploy / migracje](#11-deploy--migracje)
12. [Backlog](#12-backlog)

---

## 1. Cel i stan

Po szkoleniu zbieramy opinię uczestników. Od 2026-08 dostępne są:

| Kanał | Wypełnianie | Wyniki w adm |
|-------|-------------|--------------|
| **Natywna (pnedu)** | Formularz na `pnedu.pl/ankieta/{token}` | Od razu w `surveys` / `survey_responses` |
| **Zewnętrzna (Google Forms itd.)** | Redirect z bramki na URL | Nadal ręczny import CSV |
| **Import CSV** | — | Historyczne / Google |

Decyzje właściciela (2026-08): anonimowość **per ankieta**; edycja pytań w szablonie; tryb otwierania auto/ręczny w ustawieniach; Google zostaje do wyboru; rekomendacje z publikacją na homepage.

---

## 2. Trzy kanały

```text
course_survey_links (dystrybucja + bramka)
  ├─ channel=native  → Survey ze szablonu → formularz → (opcjonalnie) rekomendacja → survey_responses + survey_testimonials
  └─ channel=external → redirect na Google/… → (osobno) import CSV → survey_responses

Ustawienia: survey_settings (domyślny kanał, open_mode, szablon, anonimowość)
Szablon:    survey_templates + survey_template_questions
```

---

## 3. Ustawienia globalne

**UI:** menu **Ankiety → Ustawienia** → `/settings/ankiety`

| Pole | Znaczenie |
|------|-----------|
| `default_channel` | `native` / `external` — domyślnie przy dodawaniu ankiety na szkoleniu |
| `open_mode` | `manual` / `auto` — czy puste daty uzupełniać z końca szkolenia |
| `auto_open_offset_hours` | godziny względem `courses.end_date` (domyślnie **−2**; ujemny = przed końcem, zakres −24…720) |
| `auto_close_after_days` | zamknięcie N dni po otwarciu (opcjonalnie) |
| `default_is_anonymous` | domyślna anonimowość (nadpisywalna per ankieta) |
| `allow_multiple_responses` | **domyślna** wartość przy tworzeniu nowej ankiety (nie steruje już istniejącymi) |
| `default_template_id` | szablon kopiowany przy ankiecie natywnej |

Model: `SurveySetting` (singleton id=1, cache).

---

## 4. Szablony pytań

**UI:** `Ankiety → Szablony` → `/surveys/templates`

- Seed: szablon **„Ewaluacja szkolenia (standard)”** (`ewaluacja-szkolenia`) — odpowiednik Google Form (pytanie `testimonial` w szablonie jest pomijane w fill — rekomendacja to osobny krok).
- Edycja: dodawanie / usuwanie / zmiana treści, typu, kolejności, opcji.
- Typy: `rating`, `text`, `single_choice`, `multiple_choice`, `date`, `time`, `availability`, `testimonial` (legacy / pomijane w fill).

Zmiana szablonu **nie** zmienia pytań już utworzonych ankiet na szkoleniach (kopia w momencie provision).

---

## 5. Ankieta na szkoleniu (adm)

Na liście szkoleń (`/courses`) oraz na karcie szkolenia → modal **Ankiety**:

- kanał: Natywna / Zewnętrzna (Google…),
- anonimowa: tak/nie,
- zezwolenie na wielokrotne wypełnienie: tak/nie (per ankieta; domyślnie z ustawień),
- szablon (tylko native),
- URL (tylko external),
- okno `opens_at` / `closes_at`, aktywność, kolejność.

Przy **native** system tworzy `Survey` + kopię pytań (`NativeSurveyProvisioner`) i wiąże `course_survey_links.survey_id`.  
Tytuł: wzorzec `ANKIETA: {tytuł szkolenia bez HTML/&nbsp;} (YYYY-MM-DD)` (data z `start_date`) jest **wstawiany do pola „Tytuł / opis”** przy otwarciu modala — można go zmienić przed zapisem/edycją; przy native aktualizuje też `surveys.title`.  
Link dla uczestnika: zawsze `{PNEDU_FRONTEND_URL}/ankieta/{public_token}`.

---

## 6. Wypełnianie (pnedu)

| Route | Rola |
|-------|------|
| `GET /ankieta/{token}` | native → formularz; external → redirect; poza oknem → niedostępna |
| `POST /ankieta/{token}` | zapis odpowiedzi (+ honeypot) → redirect do rekomendacji |
| `GET /ankieta/{token}/rekomendacja` | opcjonalny krok (sesja po submitcie, max 2h) |
| `POST /ankieta/{token}/rekomendacja` | zapis `survey_testimonials` (+ honeypot) |
| `GET /ankieta/{token}/rekomendacja/pomin` | pominięcie rekomendacji → dzięki |
| `GET /ankieta/{token}/dziekujemy` | podziękowanie (`?rec=1` gdy wysłano opinię) |
| `GET /ankieta/{token}/juz-wypelniona` | komunikat przy ponownej próbie (limit 1×) |

- Anonimowa: bez e-maila.
- Z tożsamością: wymagany e-mail **tylko gdy niezalogowany**; zalogowany na pnedu.pl — blok e-mail ukryty, adres brany z konta.
- Opcjonalne powiązanie z `participants` po e-mailu + `course_id`.
- **Limit wypełnień** (gdy na danej ankiecie `course_survey_links.allow_multiple_responses=false`): nieanonimowa = 1× na e-mail/konto; anonimowa = cookie przeglądarki (miękkie). Flaga per ankieta w modalu; w **Ankiety → Ustawienia** tylko domyślna wartość przy tworzeniu.
- Po wypełnieniu natywnej ankiety blok „Ankiety po szkoleniu” na `/dashboard/szkolenia/{participant}/wideo` znika (gdy nie ma innych niewypełnionych).
- Pytania `question_type=testimonial` są **pomijane** w formularzu głównym.
- Wyniki od razu widoczne w adm (lista ankiet / szczegóły / PDF jak dotychczas).

---

## 7. Rekomendacje na homepage

Po wysłaniu ankiety natywnej uczestnik trafia na osobny krok `/ankieta/{token}/rekomendacja`
(może pominąć). Formularz zbiera: ocenę 1–5 (wymagana), cytat, imię, stanowisko, miasto
oraz opcjonalny awatar. Wysłanie = zgoda na publikację (tekst przy przycisku; `publish_consent=true`).
Powiązanie z `survey_response_id` przez sesję.

**Moderacja adm:** `Ankiety → Rekomendacje` → `/surveys/testimonials`  
Publikacja na homepage dopiero po ręcznym „Publikuj” (mimo zgody z formularza).  
Edycja: przycisk **Edytuj** — poprawa opinii, autora, stanowiska, miasta i oceny (literówki przed publikacją).

**Awatary w formularzu:** `Ustawienia → Ankiety` → checkboxy (`survey_settings.enabled_avatar_presets`).
Katalog: **32** self-hosted SVG (DiceBear Avataaars, zestaw „nauczycielski” — nie pełna biblioteka).
Domyślnie włączone **16** (rdzeń); w ustawieniach można włączyć kolejne.
Zawsze: BRAK + własne zdjęcie. Stare klucze mapowane w `SurveyAvatarPresets::migrateLegacyKey()`.
Po zapisie ustawień adm czyści cache pnedu (`POST /api/internal/cache/survey-settings`) — bez tego formularz mógł trzymać starą listę do ~120 s.

**Data na homepage:** `Ustawienia → Ankiety` → checkbox
„Pokazuj datę rekomendacji na stronie głównej pnedu.pl”
(`survey_settings.show_testimonial_date_on_homepage`, domyślnie **wyłączone**).
Gdy włączone: pod autorem wyświetlana jest data wystawienia (`created_at`, `d.m.Y`, Europe/Warsaw)
na kartach początkowych i dociągniętych przez „Pokaż więcej”.

**pnedu homepage:** `HomeController` ładuje **6 najnowszych** opublikowanych rekomendacji
(`orderByDesc(created_at)` — jak lista w `Ankiety → Rekomendacje`);
przycisk **Pokaż więcej** dociąga kolejne starsze (`/fragments/homepage-testimonials`, bez już pokazanych).  
Migracja seeduje dwie dotychczasowe opinie placeholder (Anna Nowak, Piotr Zieliński) jako opublikowane.

**RODO / Polityka (pnedu, 2026-08-09):** klauzula RODO + Polityka prywatności opisują rekomendacje (zgoda, opcjonalne zdjęcie, publikacja po moderacji, wycofanie przez e-mail). Formularz: tekst zgody + linki (bez osobnego checkboxa). Polityka: domena `pnedu.pl`.

---

## 8. Import CSV (Google Forms) — nadal wspierany

Bez zmian w ścieżce:

- `/courses/{id}/surveys/import` lub `/surveys/create`
- kolumna `Sygnatura czasowa`, UTF-8 CSV
- pliki: `storage/app/private/surveys/imports/`

Można równolegle używać Google Forms (channel=external) i importować wyniki.

---

## 9. Schemat bazy

Wszystko w **`pneadm`** (migracja `2026_08_08_160000_create_native_survey_foundation.php`):

- `survey_settings`
- `survey_templates`, `survey_template_questions`
- `survey_testimonials`
- rozszerzenia: `course_survey_links` (`channel`, `is_anonymous`, `survey_id`, `survey_template_id`, `url` nullable)
- rozszerzenia: `surveys` (`channel`, `is_anonymous`, `survey_template_id`)
- rozszerzenia: `survey_responses` (`participant_id`, `respondent_email`)
- enum `survey_questions.question_type` + `testimonial`, `availability`

---

## 10. Trasy i pliki

### adm

- Controllers: `CourseSurveyLinkController`, `SurveyTemplateController`, `SurveyTestimonialController`, `Settings\SurveySettingsController`
- Services: `NativeSurveyProvisioner`, `NativeSurveySubmissionService`
- Views: `settings/surveys`, `surveys/templates/*`, `surveys/testimonials/*`, modal w `courses/show.blade.php`

### pnedu

- `ExternalSurveyGateController` (visit / submit / recommend / skip / thanks)
- `NativeSurveySubmissionService`, `SurveyTestimonial`, `SurveyAvatarPresets`
- Views: `survey-native-form`, `survey-recommendation`, `survey-native-thanks`, `layouts/survey`
- Homepage: `welcome.blade.php` (dynamiczne opinie)

---

## 11. Deploy / migracje

Na produkcji (pneadm):

```bash
php artisan migrate --force
php artisan optimize:clear
```

pnedu: sam deploy kodu (brak migracji po stronie pnedu; czyta `pneadm`).

Wymagane: `PNEDU_FRONTEND_URL` w adm (budowa linków uczestnika).

---

## 12. Backlog

- Automatyczne tworzenie ankiety przy końcu szkolenia (job/cron przy `open_mode=auto`) — dziś auto uzupełnia daty przy **tworzeniu** linku
- Edycja pytań już utworzonej instancji `Survey` (nie tylko szablonu)
- Testy automatyczne importu / native submit / bramki
