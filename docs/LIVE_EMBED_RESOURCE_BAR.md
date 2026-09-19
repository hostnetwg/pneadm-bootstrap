# Belka zasobów na transmisji (osadzony live)

Data: 2026-09-19  
Status: **etap 1 wdrożony (lokalnie)**  
Projekty: `pneadm` + `pnedu`

## Po co

Operator w trakcie **osadzonego** spotkania na pnedu.pl (`/dashboard/szkolenia/{id}/transmisja`) pokazuje uczestnikom linki: materiały, ankieta, zaświadczenie. **Rejestracja: lista obecności** jest na panelu, ale na belce ukryta — uczestnik `/transmisja` jest już zalogowany i na liście. Wróci, gdy live będzie dostępny bez konta pnedu.

Szkolenia zamknięte na ogólnym linku ClickMeeting (bez konta pnedu) **są poza zakresem** etapu 1.

## Etap 1 — uzgodnione (Waldemar, 19.09.2026)

| # | Decyzja |
|---|---------|
| 1 | Jedna belka, **cztery niezależne przełączniki** w ADM. Włączenie jednego nie wymusza reszty. Linki wchodzą i schodzą **na żywo**, bez odświeżania `/transmisja`. Przełącznik da się włączyć **tylko gdy zasób jest już na szkoleniu** (status zaświadczeń `download_enabled` / link materiałów / aktywna ankieta). PATCH wymusza `false`, gdy zasób nie jest gotowy. **Rejestracja: lista obecności** jest zaparkowana na obecnym osadzonym live (zalogowany uczestnik) — przełącznik nieaktywny, link nie wychodzi na belkę. Wróci przy live bez konta pnedu. |
| 2 | Sterowanie: **osobny panel live** (rekomendacja poniżej), nie lista uczestników. |
| 3 | Uczestnik zostaje na transmisji. Klik w link: **nowa karta** + **wyjście z pełnego ekranu** (pokój w pierwszej karcie zostaje). |
| 4 | Zamknięte / sam CM: nie w etapie 1. |
| 5 | Odznaczenie w ADM → link znika z belki przy następnym odczycie (ok. 5 s; cache belki 2 s). |

Harmonogram (minuty po starcie, % czasu, pół godziny przed końcem) — **etap 2**, nie teraz.

Belka instruktora na transmisji i konta prowadzących na pnedu.pl — **parkowane**, zob. [INSTRUCTOR_PORTAL.md](./INSTRUCTOR_PORTAL.md).

## Gdzie klika operator

Panel: **`/courses/{id}/live`**.

- otwierany na **drugim monitorze** na czas spotkania,
- cztery niezależne przełączniki (nieaktywne, dopóki nie ma statusu pobierania zaświadczeń / materiałów / aktywnej ankiety; **Rejestracja: lista obecności** zawsze nieaktywna na obecnym embedzie) + podgląd, które linki istnieją + **kto jest teraz** na `/transmisja` (`embed_last_seen_at` z heartbeat) + liczby wejść (`embed_last_entered_at`: kiedykolwiek / ostatnie 15 min),
- **oferta kolejnego szkolenia:** TomSelect (bez bieżącego kursu) + **Wyświetl uczestnikom** / **Ukryj ofertę**. Druga belka na `/transmisja` (pod paskiem PNE): tytuł, termin, prowadzący, przycisk **Zamawiam szkolenie** (opis `/courses/{id}`, nowa karta + zejście z pełnego ekranu). Poll `live_offer`. Kolumny `live_offer_course_id` + `live_offer_enabled`.
- zapis belki od razu po przełączeniu checkboxów (stan w `course_online_details`, nie w sesji operatora).

Skróty (ten sam panel, nie druga kopia checkboxów):

- na `/courses/{id}` przycisk **Panel live**, gdy szkolenie ma dane online,
- w nagłówku `/courses/{id}/participants` ten sam link.

Gdy radio nie jest na osadzony pokój, panel ostrzega — belka i tak nic nie pokaże (`embed_on_pnedu` musi być ON).

## Zachowanie belki u uczestnika

- Tylko zalogowany właściciel rekordu `participants`, tylko gdy `embed_on_pnedu`.
- Checkbox ON **i** zasób istnieje (URL materiałów / status zaświadczeń `download_enabled` / aktywna ankieta) → przycisk na zielonym pasku PNE (widok normalny i pełny ekran). **Rejestracja: lista obecności** nie wychodzi na belkę, dopóki `/transmisja` wymaga konta i rekordu uczestnika.
- Poll `GET …/transmisja/meeting-status` (pierwszy odczyt ~0,4 s, potem **5 s**; pauza gdy karta ukryta). API ClickMeeting nadal cache **12 s**. Belka+oferta mają cache **2 s** na szkolenie, żeby wielu widzów nie waliło w te same SELECT-y. Pola `resource_links` i `live_offer`.
- Klik: `target=_blank` + zejście z pełnego ekranu; iframe CM zostaje.
- Przyciski mają ikony. **Pobierz zaświadczenie** ma ikonę dyplomu (dokument + pieczęć), nie medal. Niewkliknięty link: ikona **rzadko, miękko miga** (dwa impulsy co ~10 s, nie cały przycisk). Po kliknięciu mignięcie gaśnie dla tej przeglądarki (ciasteczko `pne_live_bar_seen_{courseId}`, 18 h). `prefers-reduced-motion` wyłącza animację.
- Mobile: dziś embed i tak schodzi na CM — belki tam nie ma (etap 1).

Lista obecności: ten sam URL co w mailu do prowadzącego (`certificate_registration_open` + token), **bez** okna od–do. Etykieta w panelu live: **Rejestracja: lista obecności**. Na obecnym osadzonym live **nie publikujemy** tego przycisku (uczestnik już jest na liście). Stała `ATTENDANCE_VISIBLE_ON_AUTHENTICATED_EMBED = false` w serwisach ADM i pnedu — do odwrócenia, gdy live będzie bez logowania.

Materiały: `course_file_links` także przed `end_date` — **tylko belka**, dashboard szkoleń bez zmian. Etykieta: **Pobierz materiały** (nie tytuł pliku).

Ankieta: aktywna w oknie czasowym, URL bramki `/ankieta/{token}`. Etykieta: **Wypełnij ankietę** (bez nazwy i daty z ADM).

Pobierz zaświadczenie (ostatni przycisk na belce i ostatni przełącznik w panelu): gdy `courses.certificate_download_status = download_enabled` (edycja szkolenia: **Status zaświadczeń** → „Udostępnij pobieranie zaświadczeń (link na pnedu.pl)”). URL: `/dashboard/zaswiadczenia/{id}` (zalogowany uczestnik). Etykieta: **Pobierz zaświadczenie**. Nie wymaga zakończenia szkolenia — operator sam wybiera moment na belce.

Nie odsłaniamy przy okazji materiałów na `/dashboard/szkolenia` przed `end_date`. Etap 1 = **tylko belka transmisji**.

## Kod i baza

Kolumny w `course_online_details` (domyślnie OFF):

- `live_bar_attendance_enabled`
- `live_bar_certificate_enabled`
- `live_bar_materials_enabled`
- `live_bar_survey_enabled`
- `live_offer_course_id` (nullable, FK `courses.id`)
- `live_offer_enabled` (domyślnie OFF)

Kolumna w `participant_live_access`:

- `embed_last_seen_at` — ostatni heartbeat `/transmisja` (throttling 20 s). Panel live pokazuje osoby z znacznikiem z ostatnich 90 s.

Migracje:

- `pneadm/database/migrations/2026_09_19_130000_add_live_bar_flags_to_course_online_details_table.php`
- `pneadm/database/migrations/2026_09_19_213000_add_live_offer_to_course_online_details_table.php`
- `pneadm/database/migrations/2026_09_19_221500_add_live_bar_certificate_to_course_online_details_table.php`
- `pneadm/database/migrations/2026_09_19_231700_add_embed_last_seen_at_to_participant_live_access_table.php`

```bash
# lokalnie
cd /home/hostnet/WEB-APP/pneadm && sail artisan migrate
# testy
cd /home/hostnet/WEB-APP/pneadm && sail test --filter=CourseLivePanelTest
cd /home/hostnet/WEB-APP/pnedu && sail test --filter=LiveTransmissionResourceBar
cd /home/hostnet/WEB-APP/pnedu && sail test --filter=LiveEmbedPresence
```

Prod: migracja tylko w `pneadm` (`/opt/alt/php82/usr/bin/php artisan migrate --force`). pnedu bez migracji.

## Oferta kolejnego szkolenia (belka pod PNE)

Gdy operator włączy ofertę w ADM, na `/transmisja` pod zielonym paskiem PNE wysuwa się **złota belka**: kicker „Kolejne szkolenie”, tytuł, data, prowadzący, przycisk **Zamawiam szkolenie** (`/courses/{id}` — opis, nie formularz). Schodzi przy „Ukryj ofertę” (następny poll). Klik CTA: nowa karta + zejście z pełnego ekranu. Widok normalny i pełny ekran.

## Później (nie w etapie 1)

- Live bez logowania / gość z zamkniętego linku: wtedy **Rejestracja: lista obecności** wraca na belkę.
- Automatyczne okna: np. lista + materiały X minut po `start_date`, ankieta Y minut / % przed `end_date`.
- Belka sterująca dla instruktora na `/transmisja`, jeśli e-mail = `instructors.email`.
- Szerszy portal prowadzącego: [INSTRUCTOR_PORTAL.md](./INSTRUCTOR_PORTAL.md).
