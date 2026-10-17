# PNE Operations — konsultacja

Ten plik jest dla ChatGPT. Nie kopiujemy tu całej dokumentacji. Kanon: [README.md](./README.md).

Analiza: 2026-10-17, lokalne `main`.

- `pneadm` `ec69ddcce2c273625b9d128038cf1cf294aac09f`
- `pnedu` `7a3cc87c7f1b6be849d11ae253dce6b52c45e8ff`

Produkcji nie odpytywano. Dokumenty ofert nadal mówią „wdrożone lokalnie, do deployu”.

## Odpowiedzi z kodu

### 1. Jak działają formularze rad pedagogicznych?

Publiczne trasy w `pnedu/routes/web.php`:

- `GET /szkolenia-rad-pedagogicznych` — lista (`TrainingOfferController::pedagogicalCouncils`)
- `GET|POST /szkolenia-rad-pedagogicznych/zapytanie` — formularz ogólny
- `GET /szkolenia-rad-pedagogicznych/{slug}` — oferta
- `POST /szkolenia-rad-pedagogicznych/{slug}/zapytanie` — formularz oferty

`TrainingOfferInquiryController` waliduje pola i wywołuje `Mail::to(config('mail.system.reply_to_address'))->send(new TrainingOfferInquiryMail($data))`. Domyślny adres w configu to `kontakt@pnedu.pl` (`pnedu/config/mail.php`). Mail idzie synchronicznie (`send`, nie `queue`). Temat maila bierze tytuł oferty albo, przy formularzu ogólnym, wpisany temat albo tekst „(temat nie określony)”. Reply-To to e-mail osoby z formularza.

Pola: `name`, `email`, `phone`, `institution`, `offer_topic` (tylko ogólny), `preferred_format` (`online` | `onsite` | `to_discuss`), `message`, `consent` (musi być zaznaczona). Zgoda nie jest zapisywana.

Oferty czyta model `pnedu/app/Models/TrainingOffer.php` (`$connection = 'pneadm'`), scope `publiclyVisible()`: aktywna, `show_on_pnedu`, bez `deleted_at`.

### 2. Czy oba formularze mają wspólną logikę?

Walidacja jest wspólna: prywatna `validateInquiry()`. Widoki są zdublowane (`general-inquiry.blade.php` i `show.blade.php`), bez wspólnego partiala. Formularz oferty nie ma pola tematu — temat i URL bierze z rekordu oferty. Formularz ogólny ustawia `offer_url = null`.

### 3. Do której bazy zapisywać sprawy?

Do **`pneadm`**. Tam są `training_offers`, `courses`, `instructors` i operatorzy `users`. pnedu już zapisuje zamówienia w tej bazie (`FormOrder::$connection = 'pneadm'`). `pnedu` trzyma konta uczestników, sesje i technikalia portalu.

### 4. Jak pnedu sięga do danych pneadm?

Połączenie Laravel `pneadm` w `pnedu/config/database.php` (env `DB_PNEADM_*`, wstecznie `DB_ADMPNEDU_*`). Odwrotnie: `pneadm/app/Models/PneduUser.php` czyta i zapisuje `pnedu.users`. Dodatkowo wąskie API z tokenem (certyfikaty, nagrania, zrzut cache). Opis układu: [architecture/SYSTEM_OVERVIEW.md](../architecture/SYSTEM_OVERVIEW.md).

### 5. Model `instructors`

Tabela `instructors` w `pneadm` (`database/migrations/2025_02_19_153337_create_instructors_table.php` i późniejsze kolumny). Model `app/Models/Instructor.php`: tytuł, imię, nazwisko, płeć, `email` unique, telefon, bio, bio HTML, zdjęcie, podpis, `is_active`, rozliczenie, notatki, profil głosu AI, linki społecznościowe. Relacje: `courses`, ankiety, faktury trenera, kampanie Growth. Soft delete. Brak `user_id`.

### 6. Czy `instructors.email` bezpiecznie wiąże konto?

Nie jako automat. Jest unikalny i wymagany w `InstructorsController`, ale kontroler nie woła normalizacji. Konta pnedu składują e-mail małymi literami i trzymają `email_unique_slot` także przy soft delete. Ten sam napis może oznaczać trenera, uczestnika albo skrzynkę wspólną. Nadaje się jako podpowiedź dla operatora.

### 7. Czy jest relacja Instructor ↔ user?

Nie. Grep modeli i migracji nie pokazuje klucza obcego z `instructors` do `pneadm.users` ani `pnedu.users`.

### 8. Role na pnedu

Nie ma. `pnedu/app/Models/User.php` nie ma `role_id`. Trasy panelu są za `auth` + zwykle `verified`. Role i `CheckPermission` są tylko w pneadm: `super_admin`, `admin`, `manager`, `user` (`RolePermissionSeeder`). Jedna rola na użytkownika ADM.

### 9. Materiały kursów

`course_file_links`: `course_id`, `url` (do 2048), `title`, `order`. To link, często Google. Model `CourseFileLink`. Nagrania: `course_videos.video_url` + platforma (`CourseVideo`). Grafika oferty i kursu to plik na dysku publicznym ADM.

### 10. Wyniki ankiet

Baza `pneadm`. `surveys` (przy `course_id`, opcjonalnie `instructor_id`), `survey_responses.response_data` JSON, plus `participant_id` i `respondent_email` z późniejszej migracji natywnej. Dystrybucja: `course_survey_links` (token publiczny na pnedu `/ankieta/{token}`). Kanał zewnętrzny nadal może być importem CSV. Kanon: [SURVEYS.md](../SURVEYS.md).

### 11. Czy trener jest jednoznacznie przy wynikach?

Szkolenie ma jednego `courses.instructor_id`. Przy ankiecie natywnej `NativeSurveyProvisioner` kopiuje ten identyfikator do `surveys.instructor_id`. Da się pokazać wyniki szkoleń tego prowadzącego. Nie ma wielu prowadzących na jednym kursie. Import historyczny może mieć puste `instructor_id` (kolumna nullable). To nie jest jeszcze ekran dla trenera.

### 12. Logi, komentarze, notatki

- `activity_logs`: audyt CRUD w ADM (kto, model, old/new JSON). Włącza się tylko dla zalogowanego użytkownika ADM. Publiczny formularz sam z siebie nic tu nie zapisze.
- `courses.notatki` i `training_offers.internal_notes`: jedno pole tekstowe, nie historia.
- `instructors.notes`: notatka o trenerze.
- Brak tabeli komentarzy ogólnych.
- Najbliższa historia biznesowa: `debt_case_actions` (typ, kanał, notatka, autor, następny termin) — tylko windykacja.

### 13. Zadania poza Growth OS

Nie ma ogólnego task managera. Są:

- `growth_tasks` — zawsze przy `growth_campaign_id`, opcjonalnie artifact, assignee = user ADM, `due_at`. Checklistę 9 kroków webinaru tworzy `GrowthOperationalTasks`.
- `debt_case_actions` — działania na sprawie faktury, z `next_action_at`.

### 14. Co z Growth OS wolno reuse’ować?

Wzorzec: zadanie ma tytuł, status, assignee i termin; kampania ma właściciela. Nie wolno reuse’ować tabel. Zadania kasują się z kampanią, assignee to operator ADM, a klucze są na stałe wpisane pod webinar.content. Domena zostaje osobna ([growth-os/README.md](../growth-os/README.md)).

### 15. Jak oferta staje się kursem?

`CoursesController::createFromTrainingOffer` otwiera `courses/create` z prefillem. Użytkownik podaje daty i zapisuje. `training_offer_id` jest nullable FK `nullOnDelete`. Grafika kopiuje się przy zapisie, jeśli zaznaczono. Wariant ceny nie tworzy się sam. Jedna oferta może mieć wiele kursów (`TrainingOffer::courses()`).

### 16. Czy ta konwersja jest produkcyjna?

W repozytorium `pneadm` `main` kod i migracje są. [NEXT_STEPS.md](../NEXT_STEPS.md) (2026-07-28/29) oznacza moduł jako lokalny, do deployu, i nie ma runbooka w `docs/deploy/` dla samych ofert. Ta analiza nie łączyła się z produkcją, więc stanu prod nie potwierdzamy.

### 17. Ograniczenia SeoHost

Współdzielony hosting, PHP 8.2 pod `/opt/alt/php82/usr/bin/php`, brak Supervisora, kolejka cronem co minutę, limity LVE (mało procesów PHP). Dwa katalogi: `domains/adm.pnedu.pl/pneadm` i `domains/pnedu.pl/app`. Nie ma wspólnego dysku zapisu. Sail tylko lokalnie. Kanon: [deploy/PRODUCTION_PATHS.md](../deploy/PRODUCTION_PATHS.md), [QUEUE_SEOHOST.md](../QUEUE_SEOHOST.md).

### 18. Jak bezpiecznie przenosić zmiany pnedu ↔ pneadm?

Dla sprawy: jeden zapis SQL z pnedu do `pneadm` w żądaniu formularza (wzorzec zamówienia), potem e-mail. Źródłem prawdy jest wiersz, nie mail. HTTP z tokenem zostawiamy na akcje, które musi wykonać druga aplikacja (dziś: cache, certyfikaty). Nie synchronizujemy dwóch kopii sprawy.

## Ustalenia po konsultacji (2026-10-17)

Waldemar przyjął rekomendacje ChatGPT. Pytania poniżej są zamknięte. Szczegóły: [DECISIONS.md](./DECISIONS.md).

1. Bez pola `type` na etapie 1. Obiekt to sprawa szkolenia zamkniętego (DEC-OPS-009).
2. Konto trenera to jawne powiązanie z `instructors`, nie flaga przy `pnedu.users` (DEC-OPS-006).
3. Trener dostaje brief przygotowany przez PNE, nie pełną kartę i nie surowe `message` (DEC-OPS-007).
4. Status jest krótki i nie jest krokiem negocjacji. Sytuację niesie następne działanie, termin i historia (DEC-OPS-010).

Nowych pytań do konsultanta nie ma. Schematu SQL ta konsultacja nie otwiera.

## Model po tych ustaleniach

Sprawa szkolenia zamkniętego w `pneadm`, bez pola `type`: krótki status, opiekun, następne działanie, historia. Opcjonalne wskazanie oferty. Kurs i trener później, świadomie. Szczegóły: [ARCHITECTURE.md](./ARCHITECTURE.md).

Odrzucone: generyczna sprawa z `type=closed_training`, flaga `is_instructor`, pełna karta sprawy dla trenera, `growth_tasks` albo `debt_cases` jako wspólny silnik.

Windykacja zostaje przy fakturze. Późniejsze spięcie to instancja procesu z `subject_type` + `subject_id`, nie scalenie tabel (DEC-OPS-008).
