# Belka zasobów na transmisji (osadzony live)

Data: 2026-09-20  
Status: **etap 1 wdrożony (lokalnie)**  
Projekty: `pneadm` + `pnedu`

## Po co

Operator w trakcie **osadzonego** spotkania na pnedu.pl pokazuje linki. Są dwie belki:

- **konto** (`/dashboard/szkolenia/{id}/transmisja`) — materiały, ankieta, zaświadczenie. **Rejestracja: lista obecności** ukryta (uczestnik już na liście).
- **gość** (`/live/{token}`, szkolenie zamknięte) — najpierw formularz (imię, nazwisko, e-mail + RODO) zapisuje wiersz w `participants`, potem materiały + ankieta + oferta. **Bez zaświadczenia** (wymaga konta). Link tylko w ADM (karta + panel live); nie ma go w katalogu pnedu.pl ani w mailu z ADM.

Szkolenia zamknięte na ogólnym linku ClickMeeting (bez konta pnedu) **są poza zakresem** etapu 1 belki. Live gościa `/live/{token}`: [CLICKMEETING_TRAININGS.md](./CLICKMEETING_TRAININGS.md) + ten plik (sekcja gość).

## Etap 1 — uzgodnione (Waldemar, 19.09.2026)

| # | Decyzja |
|---|---------|
| 1 | Jedna belka, **cztery niezależne przełączniki** w ADM. Włączenie jednego nie wymusza reszty. Linki wchodzą i schodzą **na żywo**. Przełącznik da się włączyć **tylko gdy zasób jest już na szkoleniu**. PATCH wymusza `false`, gdy zasób nie jest gotowy. **Rejestracja: lista obecności** jest ukryta na `/transmisja` i na `/live/{token}`: gość podaje imię, nazwisko i e-mail **przed** osadzonym pokojem. |
| 2 | Sterowanie: **osobny panel live** (rekomendacja poniżej), nie lista uczestników. |
| 3 | Uczestnik zostaje na transmisji. Klik w link: **nowa karta** + **wyjście z pełnego ekranu** (pokój w pierwszej karcie zostaje). |
| 4 | Zamknięte / sam CM: nie w etapie 1. |
| 5 | Odznaczenie w ADM → link znika z belki przy następnym odczycie (ok. 5 s; cache belki 2 s). |

Harmonogram (minuty po starcie, % czasu, pół godziny przed końcem) — **etap 2**, nie teraz.

Belka instruktora na transmisji i konta prowadzących na pnedu.pl — **parkowane**, zob. [INSTRUCTOR_PORTAL.md](./INSTRUCTOR_PORTAL.md).

## Gdzie klika operator

Panel: **`/courses/{id}/live`**.

- otwierany na **drugim monitorze** na czas spotkania,
- cztery niezależne przełączniki (nieaktywne, dopóki nie ma zasobu; **Rejestracja** na razie nieaktywna — lista obecności zbiera się na formularzu `/live/{token}`) + podgląd belki (dla zamkniętych: belka gościa) + **kto jest teraz** na `/transmisja` + liczby wejść + **Na czat ClickMeeting** (kopiuj linki do wklejenia w pokoju, bo API CM nie umie wysłać czatu),
- **oferta kolejnego szkolenia:** TomSelect + **Wyświetl uczestnikom** / **Ukryj ofertę** + checkbox **Ukryj ofertę po 2 minutach** (domyślnie zaznaczony). Gdy zaznaczony — auto-ukrycie po 120 s; gdy odznaczony — oferta stoi do ręcznego Ukryj. Na `/transmisja` i `/live/{token}`: okienko Bootstrap. Kolumny `live_offer_course_id` + `live_offer_enabled` + `live_offer_enabled_at` + `live_offer_auto_hide`.
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

Lista obecności gościa: formularz na `/live/{token}` (imię, nazwisko, e-mail, zgoda RODO) **przed** iframe — jak lobby ClickMeeting, plus nazwisko. Ten sam e-mail na kursie aktualizuje jeden wiersz `participants`. Autologin CM dostaje `Imię Nazwisko`. Przełącznik **Rejestracja: lista obecności** w panelu live zostaje nieaktywny (nie potrzebny na belce). Stała `ATTENDANCE_VISIBLE_ON_AUTHENTICATED_EMBED = false`.

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
- `live_offer_enabled_at` (nullable) — start okna 2 min (tylko gdy auto-ukrycie ON)
- `live_offer_auto_hide` (domyślnie ON) — checkbox w panelu live

Kolumna w `participant_live_access`:

- `embed_last_seen_at` — kto jest na `/transmisja` (zapis przy wejściu, przy pollu belki i przy heartbeacie; throttling 20 s). Panel live pokazuje osoby z znacznikiem z ostatnich **180 s**. Poll belki **nie jest zatrzymywany** przy ukrytej karcie; po powrocie (visibility / pageshow / focus) natychmiast wznawia belkę i obecność. Przy 401/419 (wygasła sesja) jedno auto-odświeżenie strony. `pagehide` z bfcache nie wywołuje leave.

Kolumna gościa:

- `guest_live_token` (nullable, unique) — sekretny `/live/{token}` na pnedu.pl

Migracje:

- `pneadm/database/migrations/2026_09_19_130000_add_live_bar_flags_to_course_online_details_table.php`
- `pneadm/database/migrations/2026_09_19_213000_add_live_offer_to_course_online_details_table.php`
- `pneadm/database/migrations/2026_09_20_203000_add_live_offer_enabled_at_to_course_online_details_table.php`
- `pneadm/database/migrations/2026_09_20_204500_add_live_offer_auto_hide_to_course_online_details_table.php`
- `pneadm/database/migrations/2026_09_19_221500_add_live_bar_certificate_to_course_online_details_table.php`
- `pneadm/database/migrations/2026_09_19_231700_add_embed_last_seen_at_to_participant_live_access_table.php`
- `pneadm/database/migrations/2026_09_19_235500_add_guest_live_token_to_course_online_details_table.php`

```bash
# lokalnie
cd /home/hostnet/WEB-APP/pneadm && sail artisan migrate
# testy
cd /home/hostnet/WEB-APP/pneadm && sail test --filter=CourseLivePanelTest
cd /home/hostnet/WEB-APP/pneadm && sail test --filter=GuestLiveLinkServiceTest
cd /home/hostnet/WEB-APP/pnedu && sail test --filter=LiveTransmissionResourceBar
cd /home/hostnet/WEB-APP/pnedu && sail test --filter=GuestLiveTransmission
cd /home/hostnet/WEB-APP/pnedu && sail test --filter=LiveEmbedPresence
```

Prod: migracja tylko w `pneadm` (`/opt/alt/php82/usr/bin/php artisan migrate --force`). pnedu bez migracji (czyta `guest_live_token`).

## Live bez logowania (gość)

Tylko **kategoria Zamknięte** + **osadzony pokój** + ClickMeeting **Dla wszystkich**.

- URL: `https://pnedu.pl/live/{token}` (`noindex`, poza sitemapą i katalogiem).
- Pokazany w ADM: karta szkolenia i panel live (Kopiuj link). **Maila do dyrektora jeszcze nie generujemy i nie wysyłamy z ADM.**
- Wejście: formularz imię / nazwisko / e-mail / RODO → wiersz w `participants` → iframe z autologinem (`nickname` = Imię Nazwisko). Sesja `guest_live_registered_{courseId}` — ponowne wejście w tej samej przeglądarce bez formularza.
- Belka: materiały, ankieta, oferta. Bez „Rejestracja: lista obecności” i bez „Pobierz zaświadczenie”.
- Konta pnedu nie zakładamy.
- Etap C (lista „teraz” dla gości) — nie w tej paczce.

## Oferta kolejnego szkolenia (okienko na live)

Gdy operator włączy ofertę w ADM, na `/transmisja` i na `/live/{token}` pojawia się **wyśrodkowane okienko Bootstrap**: pełna grafika szkolenia (bez przycinania), temat, data, prowadzący, **cena / promocja** (z omnibusem gdy dotyczy), **skrócony opis** ze złamaniami wierszy oraz przycisk **Zamawiam szkolenie** → **formularz zamówienia** (`/courses/{id}/order-form`). Link **Opis szkolenia** otwiera pełny opis na stronie kursu w nowej karcie.

**Auto-ukrycie (opcjonalne, domyślnie ON):** checkbox **Ukryj ofertę po 2 minutach** w panelu live. Gdy zaznaczony — serwer trzyma `live_offer_enabled_at` i po **120 s** wyłącza ofertę (panel + belka). Gdy odznaczony — oferta zostaje do ręcznego „Ukryj ofertę” (`live_offer_enabled_at` = null). Zmiana checkboxa zapisuje się od razu (także przy już włączonej ofercie). Zamknięcie „X” / „Zamknij” u jednego uczestnika nie wyłącza oferty globalnie. Okienko oferty jest **bez przyciemnienia tła** (`data-bs-backdrop="false"`): poza kartą kliknięcia idą do pokoju/czatu CM; karta lekko przesunięta w lewo, żeby nie zasłaniać czatu.

## Na czat ClickMeeting

Publiczne API CM **nie wysyła** wiadomości na czat (`GET /v1/chats` to tylko archiwum). Osoby na bezpośrednim linku pokoju nie widzą belki pnedu.

W panelu live: karta **Na czat ClickMeeting** — gotowy tekst z aktualnie włączonych zasobów (plus lista obecności, gdy jest publiczny URL rejestracji) i przycisk **Kopiuj na czat**. Format jednej linii, np. `MATERIAŁY: https://…`, `ANKIETA: https://…`, `ZAŚWIADCZENIE: https://…`, `SZKOLENIE: Początek tematu … https://pnedu.pl/courses/{id}`. Działa także gdy radio jest na „Pokój na ClickMeeting” (bez embedu). Operator wkleja w czat publiczny pokoju.

## Później (nie teraz)

- Mail do dyrektora z linkiem `/live/{token}` (generacja + ewentualna wysyłka z ADM).
- Obecność gości na panelu live (etap C).
- Automatyczne okna: np. lista + materiały X minut po `start_date`, ankieta Y minut / % przed `end_date`.
- Belka sterująca dla instruktora na `/transmisja`, jeśli e-mail = `instructors.email`.
- Szerszy portal prowadzącego: [INSTRUCTOR_PORTAL.md](./INSTRUCTOR_PORTAL.md).