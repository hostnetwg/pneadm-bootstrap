# Szkolenia ClickMeeting → courses

Data utworzenia/aktualizacji: 2026-09-19  
Status: wdrożone lokalnie

## Cel

ClickMeeting jest źródłem prawdy dla płatnych szkoleń online: najpierw powstaje wydarzenie w ClickMeeting (i zaproszenie do trenera), dopiero potem rekord w `courses`.

Lista `/clickmeeting/trainings` pokazuje wydarzenia z API i oznacza, które mają już szkolenie w panelu. Z tej listy można otworzyć formularz `/courses/create` z częściowo uzupełnionymi danymi. Zapis nadal jest ręczny.

## Powiązanie

Powiązanie jest po `course_online_details.clickmeeting_event_id` = ID wydarzenia ClickMeeting.

- jeśli ID jest już w `courses` — na liście widać zieloną etykietę **W courses** i link do edycji; przycisk dodawania znika,
- jeśli ID nie występuje — przycisk **Dodaj do szkoleń** otwiera formularz w nowej karcie.

Wejście bezpośrednie na `/clickmeeting/trainings/{eventId}/create-course` przy istniejącym powiązaniu przekierowuje do edycji szkolenia.

## Prefill formularza

Źródło danych: `GET /v1/conferences/{id}` (nie lista, bo lista nie zawsze ma `ends_at` i `room_url`).

| Pole | Wartość |
|------|---------|
| Tytuł | `name` |
| Data rozpoczęcia | `starts_at` / `start_time` w `Europe/Warsaw`, format `datetime-local` |
| Data zakończenia | `ends_at` / `end_time`, jeśli jest i jest późniejsza niż start; **w przeciwnym razie puste** |
| Platforma | `ClickMeeting` |
| ID wydarzenia ClickMeeting | ID z API |
| Link do spotkania | `room_url` |
| Płatność | Płatne |
| Kategoria | Otwarte |
| Rodzaj kursu | Online |
| Wejście do pokoju | Osadzony pokój na pnedu.pl |
| Pokaż na stronie głównej pnedu.pl | odznaczone |

Pozostałe pola zostają domyślne. Operator uzupełnia trenera, opis, cenę, Sendy itd. i zapisuje ręcznie.

## Synchronizacja linku (etap 1)

Nazwa pokoju w ClickMeeting często się zmienia (limit znaków, doprecyzowanie). Wtedy CM generuje nowy `room_url`, a `course_online_details.meeting_link` zostaje stary.

**Synchronizujemy tylko link**, nie tytuł kursu (w `courses` jest publiczna pełna nazwa oferty).

Przycisk **Aktualizuj link z ClickMeeting**:

- na `/clickmeeting/trainings` tylko gdy `room_url` z API różni się od `meeting_link` (etykieta **Link nieaktualny** + przycisk),
- na `/courses/{id}/edit` obok pola „Link do spotkania”, też tylko przy rozjeździe.

Na liście, w kolumnie **Szkolenie w ADM**, widać też datę i godzinę startu z `courses.start_date`. Gdy różni się od terminu ClickMeeting (porównanie do minuty, CM w `Europe/Warsaw`, courses bez konwersji strefy) — etykieta **Termin się różni**. Bez automatycznej synchronizacji daty; operator poprawia w edycji szkolenia.

Kolumna **Dostęp CM** pokazuje aktualny `access_type` z ClickMeeting: **Dla wszystkich** (1), **Hasło** (2), **Tokeny** (3). Gdy lista nie ma pola, dla powiązanych szkoleń panel dociąga je z `GET /conferences/{id}`.

## Typ dostępu po powiązaniu szkolenia

Operator może zmienić dostęp w ClickMeeting już po wpisaniu `clickmeeting_event_id`. Panel **nie** zmienia ustawień w CM (brak automatycznego PUT).

Naprawa w tle (tylko snapshot u nas):

- lista `/clickmeeting/trainings` — dopasowuje `participant_live_access.access_type` do aktualnego CM,
- edycja szkolenia i sync linku / maile live — to samo po `GET conferences/{id}`,
- wejście na `/transmisja` (pnedu) — zapisuje aktualny `access_type` i `room_url` przed budową embedu. Przy „Dla wszystkich” nie wymaga tokenu.

## Szkolenia zamknięte (kategoria = Zamknięte)

Dla rad pedagogicznych i innych zamkniętych: dyrektor dostaje **jeden ogólny link** ClickMeeting i rozsyła nauczycielom. Listy uczestników na starcie nie ma (albo jest tylko dyrektor). Rejestracja obecności / zaświadczenia buduje listę dopiero w trakcie.

W ClickMeeting musi być dostęp **Dla wszystkich**. Tokeny albo hasło zablokują wejście osobom spoza listy. Panel pokazuje ostrzeżenie:

- na `/clickmeeting/trainings` (alert + etykieta **Zamknięte ≠ dla wszystkich**),
- na `/courses/{id}/edit` (żółty komunikat + podpis „Dostęp w ClickMeeting”).

Zmianę dostępu operator robi ręcznie w panelu ClickMeeting.

Osadzony pokój na pnedu.pl **bez logowania:** sekretny `/live/{token}` (formularz imię / nazwisko / e-mail → rekord uczestnika → pokój + belka). Link tylko w ADM (karta szkolenia + panel live). **Nie** w katalogu pnedu.pl, **nie** w sitemapie, **nie** w mailu z ADM (mail do dyrektora jeszcze nie). W ClickMeeting musi być **Dla wszystkich**. Kanon: [LIVE_EMBED_RESOURCE_BAR.md](./LIVE_EMBED_RESOURCE_BAR.md).

## Odświeżenie przy wysyłce maila (etap 2)

Akcja:

1. `GET /v1/conferences/{event_id}` → `room_url`,
2. zapis do `course_online_details.meeting_link`,
3. aktualizacja `participant_live_access.room_url` dla tego szkolenia,
4. synchronizacja Google Calendar, jeśli włączona.

Serwis: `App\Services\ClickMeetingCourseRoomUrlSyncService`.

## Odświeżenie przy wysyłce maila (etap 2)

Przy pierwszym provisionie ClickMeeting, przy ponownej wysyłce / podglądzie maila dostępu oraz przy „Wyślij link do live” panel pobiera aktualny `room_url` z API i zapisuje snapshot **zanim** zbuduje treść maila. Gdy API nie odpowie, zostaje poprzedni link — wysyłka nie pada.

Już wysłanych maili to nie poprawi. Maile z adresem pnedu.pl (`/transmisja`) nadal działają, bo pokój jest rozwiązywany w chwili wejścia.

## Deploy

Brak migracji. Po wrzuceniu kodu: `optimize:clear` na `pneadm`.

Smoke:

1. `/clickmeeting/trainings` — etykieta przy znanym szkoleniu, „Dodaj do szkoleń” przy nowym. Kolumna **Dostęp CM**.
2. Przy znanym szkoleniu widać **Start w courses**. Po zmianie daty w ClickMeeting — **Termin się różni**.
3. Szkolenie **Zamknięte** z tokenami w CM — alert **Zamknięte ≠ dla wszystkich**.
4. Zmień nazwę pokoju w ClickMeeting → na liście **Link nieaktualny** → aktualizacja.
5. To samo z `/courses/{id}/edit` (także ostrzeżenie zamknięte + podpis typu dostępu).
6. Provision / „Wyślij link do live” — w mailu nowy slug.

## Testy

```bash
sail test --filter=ClickMeetingTrainingAdminTest
sail test --filter=ClickMeetingServiceTest
sail test --filter=ParticipantLiveMeetingLinkMailServiceTest
```
